<?php

declare(strict_types=1);

use App\Clock;
use App\Config;
use App\Kernel;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP-тесты GET/PUT /schedule и влияния расписания на слоты и записи: временная SQLite,
 * «сейчас» фиксируется инжектированными часами, ответы сверяются с openapi.json.
 */
final class ScheduleApiTest extends TestCase
{
    /** «Сейчас» по умолчанию: воскресенье, полдень в поясе приложения. */
    private const NOW = '2026-10-11 12:00:00';

    private string $databasePath = '';

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'calendar-test-');
        self::assertIsString($path);
        $this->databasePath = $path;

        putenv(Config::DB_PATH_ENV . '=' . $this->databasePath);
        putenv(Config::TIMEZONE_ENV . '=Europe/Moscow');

        // Схема создаётся сразу: тесты ходят в базу напрямую и через ядро.
        (new Kernel(Config::fromEnv(), self::clockAt(self::NOW)))->handle(
            new ServerRequest('GET', 'http://localhost/api/schedule'),
        );
    }

    protected function tearDown(): void
    {
        putenv(Config::DB_PATH_ENV);
        putenv(Config::TIMEZONE_ENV);

        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testGetScheduleReturnsDefaultBeforeAnyChange(): void
    {
        $response = $this->request('GET', '/api/schedule');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertMatchesContract($response, 'get');

        self::assertSame(
            ['workingDays' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'dayStart' => '10:00', 'dayEnd' => '18:00'],
            $this->decode($response),
        );
    }

    public function testPutScheduleIsEchoedAndSurvivesReload(): void
    {
        $schedule = ['workingDays' => ['mon', 'wed', 'fri'], 'dayStart' => '09:15', 'dayEnd' => '13:30'];

        $response = $this->request('PUT', '/api/schedule', $schedule);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertMatchesContract($response, 'put');
        self::assertSame($schedule, $this->decode($response));

        // Новое ядро над той же базой читает сохранённое расписание, а не дефолт.
        self::assertSame($schedule, $this->decode($this->request('GET', '/api/schedule')));
    }

    public function testPutRejectsEndNotAfterStartWith422(): void
    {
        $cases = [
            'конец раньше начала' => ['workingDays' => ['mon'], 'dayStart' => '18:00', 'dayEnd' => '10:00'],
            'конец равен началу' => ['workingDays' => ['mon'], 'dayStart' => '10:00', 'dayEnd' => '10:00'],
        ];

        foreach ($cases as $label => $schedule) {
            $response = $this->request('PUT', '/api/schedule', $schedule);

            self::assertSame(422, $response->getStatusCode(), $label);
            $this->assertProblem($response, 422);
            $this->assertMatchesContract($response, 'put');
        }

        // Ни одна попытка не сохранилась: расписание осталось дефолтным.
        self::assertSame(
            ['workingDays' => ['mon', 'tue', 'wed', 'thu', 'fri'], 'dayStart' => '10:00', 'dayEnd' => '18:00'],
            $this->decode($this->request('GET', '/api/schedule')),
        );
    }

    public function testSchemaViolationsReturn400ProblemJson(): void
    {
        $cases = [
            'минуты не кратны 15' => ['dayStart' => '10:07'],
            'время без ведущего нуля' => ['dayStart' => '9:00'],
            'время за сутками' => ['dayEnd' => '24:00'],
            'неизвестный день' => ['workingDays' => ['mon', 'someday']],
            'дубликаты дней' => ['workingDays' => ['mon', 'mon']],
            'пустой список дней' => ['workingDays' => []],
            'нет dayEnd' => null,
        ];

        $valid = ['workingDays' => ['mon'], 'dayStart' => '10:00', 'dayEnd' => '18:00'];

        foreach ($cases as $label => $override) {
            $payload = $override === null
                ? array_diff_key($valid, ['dayEnd' => true])
                : array_merge($valid, $override);

            $response = $this->request('PUT', '/api/schedule', $payload);

            self::assertSame(400, $response->getStatusCode(), $label);
            $this->assertProblem($response, 400);
            $this->assertMatchesContract($response, 'put');
        }
    }

    public function testUpdatedScheduleDrivesSlotsAndBookings(): void
    {
        $this->createEventType('intro-call', 30);

        $response = $this->request('PUT', '/api/schedule', [
            'workingDays' => ['sat', 'sun'],
            'dayStart' => '12:00',
            'dayEnd' => '14:00',
        ]);
        self::assertSame(200, $response->getStatusCode());

        $slots = $this->decode($this->request('GET', '/api/event-types/intro-call/slots'))['slots'];
        $starts = array_column($slots, 'start');

        // «Сейчас» — воскресенье полдень: слоты есть только в выходные с 12:00 до 13:30.
        self::assertSame('2026-10-11T12:00:00+03:00', $starts[0]);
        self::assertContains('2026-10-17T12:00:00+03:00', $starts);
        self::assertCount(0, array_filter($starts, static fn (string $start): bool => str_starts_with($start, '2026-10-12')));

        // Запись в субботу проходит, в понедельник (нерабочий по новому расписанию) — нет.
        $saturday = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'intro-call',
            'start' => '2026-10-17T12:00:00+03:00',
            'name' => 'Анна',
            'email' => 'anna@example.com',
        ]);
        self::assertSame(201, $saturday->getStatusCode());

        $monday = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'intro-call',
            'start' => '2026-10-12T12:00:00+03:00',
            'name' => 'Иван',
            'email' => 'ivan@example.com',
        ]);
        self::assertSame(422, $monday->getStatusCode());
        $this->assertProblem($monday, 422);
    }

    public function testExistingBookingsSurviveScheduleChange(): void
    {
        $this->createEventType('intro-call', 30);

        // Запись в понедельник по расписанию по умолчанию.
        $booking = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'intro-call',
            'start' => '2026-10-12T12:00:00+03:00',
            'name' => 'Анна',
            'email' => 'anna@example.com',
        ]);
        self::assertSame(201, $booking->getStatusCode());

        // Хост переводит приём в выходные: понедельник становится нерабочим.
        $this->request('PUT', '/api/schedule', [
            'workingDays' => ['sat', 'sun'],
            'dayStart' => '12:00',
            'dayEnd' => '14:00',
        ]);

        // Уже созданная запись не тронута: видна в GET /bookings.
        $bookings = $this->decode($this->request('GET', '/api/bookings'))['bookings'];
        self::assertSame(['2026-10-12T12:00:00+03:00'], array_column($bookings, 'start'));

        // А новые слоты в понедельник больше не предлагаются.
        $slots = $this->decode($this->request('GET', '/api/event-types/intro-call/slots'))['slots'];
        $starts = array_column($slots, 'start');
        self::assertCount(0, array_filter($starts, static fn (string $start): bool => str_starts_with($start, '2026-10-12')));
    }

    private function createEventType(string $id, int $durationMinutes): void
    {
        $response = $this->request('POST', '/api/event-types', [
            'id' => $id,
            'name' => 'Звонок',
            'durationMinutes' => $durationMinutes,
        ]);

        self::assertSame(201, $response->getStatusCode());
    }

    /** @param array<string, mixed>|null $payload */
    private function request(string $method, string $path, ?array $payload = null): ResponseInterface
    {
        // Новое ядро на каждом запросе: изменения расписания видны сразу, часы — фиксированные.
        $kernel = new Kernel(Config::fromEnv(), self::clockAt(self::NOW));
        $headers = $payload === null ? [] : ['Content-Type' => 'application/json'];
        $body = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR);

        return $kernel->handle(new ServerRequest($method, 'http://localhost' . $path, $headers, $body));
    }

    private static function clockAt(string $now): Clock
    {
        return new class ($now) implements Clock {
            public function __construct(private readonly string $now)
            {
            }

            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable($this->now, new DateTimeZone('Europe/Moscow'));
            }
        };
    }

    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }

    private function assertProblem(ResponseInterface $response, int $status): void
    {
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));

        $problem = $this->decode($response);

        self::assertSame($status, $problem['status']);
        self::assertNotEmpty($problem['type']);
        self::assertNotEmpty($problem['title']);
    }

    /**
     * Ответ обязан удовлетворять контракту из openapi.json (league/openapi-psr7-validator).
     */
    private function assertMatchesContract(ResponseInterface $response, string $method): void
    {
        self::responseValidator()->validate(new OperationAddress('/schedule', $method), $response);
    }

    private static function responseValidator(): ResponseValidator
    {
        static $validator = null;

        if ($validator === null) {
            $validator = (new ValidatorBuilder())
                ->fromJsonFile(dirname(__DIR__) . '/openapi.json')
                ->getResponseValidator();
        }

        return $validator;
    }
}
