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
 * HTTP-тесты GET /event-types/{id}/slots на временной SQLite: путь БД — переменная окружения,
 * «сейчас» фиксируется инжектированными часами, ответы проверяются против openapi.json.
 */
final class SlotsApiTest extends TestCase
{
    /** «Сейчас» по умолчанию: воскресенье, полдень в поясе приложения. */
    private const NOW = '2026-10-11 12:00:00';

    private string $databasePath = '';
    private ?Kernel $kernel = null;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'calendar-test-');
        self::assertIsString($path);
        $this->databasePath = $path;

        putenv(Config::DB_PATH_ENV . '=' . $this->databasePath);
        putenv(Config::TIMEZONE_ENV . '=Europe/Moscow');

        $this->kernel = new Kernel(Config::fromEnv(), self::clockAt(self::NOW));
    }

    protected function tearDown(): void
    {
        unset($this->kernel);
        gc_collect_cycles();

        putenv(Config::DB_PATH_ENV);
        putenv(Config::TIMEZONE_ENV);

        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testResponseShapeTimezoneAndAllAvailable(): void
    {
        $this->createEventType('intro-call', 30);

        $response = $this->request('GET', '/api/event-types/intro-call/slots');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertMatchesContract($response);

        $data = $this->decode($response);
        self::assertSame('Europe/Moscow', $data['timezone']);
        self::assertNotEmpty($data['slots']);

        // Записей ещё нет: каждый слот доступен.
        self::assertCount(0, array_filter($data['slots'], static fn (array $slot): bool => !$slot['available']));
    }

    public function testSlotsAreOnFifteenMinuteGridWithMandatoryOffset(): void
    {
        $this->createEventType('intro-call', 30);

        $slots = $this->decode($this->request('GET', '/api/event-types/intro-call/slots'))['slots'];

        foreach ($slots as $slot) {
            self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:00\+03:00$/', $slot['start']);
        }
    }

    public function testPastOfTodayIsHiddenAndWindowStartsAtCeilOfNow(): void
    {
        $this->createEventType('intro-call', 30);

        // «Сейчас» 12:07 → первый слот 12:15;12:00 и всё раньше скрыты.
        $response = $this->requestWithClock('GET', '/api/event-types/intro-call/slots', null, '2026-10-11 12:07:00');

        $slots = $this->decode($response)['slots'];
        self::assertSame('2026-10-11T12:15:00+03:00', $slots[0]['start']);
    }

    public function testNowOnGridBoundaryKeepsCurrentSlot(): void
    {
        $this->createEventType('intro-call', 30);

        $slots = $this->decode($this->request('GET', '/api/event-types/intro-call/slots'))['slots'];

        self::assertSame('2026-10-11T12:00:00+03:00', $slots[0]['start']);
    }

    public function testWindowEndsOnFourteenthDayAt1800(): void
    {
        $this->createEventType('intro-call', 30);

        $slots = $this->decode($this->request('GET', '/api/event-types/intro-call/slots'))['slots'];
        $starts = array_column($slots, 'start');

        // Сегодня — день 1, последний день — сегодня + 13; последний слот укладывается до 18:00.
        self::assertSame('2026-10-11T12:00:00+03:00', $starts[0]);
        self::assertSame('2026-10-24T17:30:00+03:00', end($starts));
        // Сегодня23 слота (12:00–17:30), ещё 13 полных дней по 35 слотов (09:00–17:30).
        self::assertCount(478, $starts);
    }

    public function testDurationRoundsUpToGridForLastSlot(): void
    {
        // 20 минут округляются вверх до 30: последний слот дня — 17:30, а не 17:45.
        $this->createEventType('long-call', 20);

        $slots = $this->decode($this->request('GET', '/api/event-types/long-call/slots'))['slots'];
        $today = array_values(array_filter(
            $slots,
            static fn (array $slot): bool => str_starts_with($slot['start'], '2026-10-11'),
        ));

        self::assertSame('2026-10-11T17:30:00+03:00', end($today)['start']);
    }

    public function testUnknownEventTypeReturns404ProblemJson(): void
    {
        $response = $this->request('GET', '/api/event-types/nope/slots');

        self::assertSame(404, $response->getStatusCode());
        $this->assertProblem($response, 404);
        $this->assertMatchesContract($response);
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

    private function request(string $method, string $path, ?array $payload = null): ResponseInterface
    {
        return $this->requestWithClock($method, $path, $payload, self::NOW);
    }

    /**
     * Запрос через ядро с отдельными часами: «сейчас» фиксируется конфигом теста.
     */
    private function requestWithClock(string $method, string $path, ?array $payload, string $now): ResponseInterface
    {
        $kernel = new Kernel(Config::fromEnv(), self::clockAt($now));
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
    private function assertMatchesContract(ResponseInterface $response): void
    {
        self::responseValidator()->validate(new OperationAddress('/event-types/{id}/slots', 'get'), $response);
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
