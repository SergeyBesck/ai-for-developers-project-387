<?php

declare(strict_types=1);

use App\BookingRepository;
use App\Clock;
use App\Config;
use App\Database;
use App\Kernel;
use App\SlotConflictException;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP-тесты POST /bookings, GET /bookings и согласованности записей со списком слотов: временная SQLite,
 * «сейчас» фиксируется инжектированными часами, ответы сверяются с openapi.json.
 */
final class BookingsApiTest extends TestCase
{
    /** «Сейчас» по умолчанию: воскресенье, полдень в поясе приложения. */
    private const NOW = '2026-10-11 12:00:00';

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

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

    public function testCreateBookingReturnsFullResourceWithUuid(): void
    {
        $this->createEventType('intro-call', 'Звонок знакомства', 30);

        $response = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'intro-call',
            'start' => '2026-10-12T10:00:00+03:00',
            'name' => 'Анна',
            'email' => 'anna@example.com',
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertMatchesContract($response, 'post');

        $booking = $this->decode($response);
        self::assertMatchesRegularExpression(self::UUID_PATTERN, $booking['id']);
        self::assertSame('intro-call', $booking['eventTypeId']);
        self::assertSame('Звонок знакомства', $booking['eventTypeName']);
        self::assertSame('2026-10-12T10:00:00+03:00', $booking['start']);
        self::assertSame(30, $booking['durationMinutes']);
        self::assertSame('Анна', $booking['name']);
        self::assertSame('anna@example.com', $booking['email']);
        self::assertNull($booking['note']);
    }

    public function testCreateBookingKeepsNoteAndNormalizesStartToAppTimezone(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);

        // 07:00 по UTC — это 10:00 в поясе приложения.
        $response = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'intro-call',
            'start' => '2026-10-12T07:00:00+00:00',
            'name' => 'Анна',
            'email' => 'anna@example.com',
            'note' => 'Обсудим запуск продукта',
        ]);

        self::assertSame(201, $response->getStatusCode());

        $booking = $this->decode($response);
        self::assertSame('2026-10-12T10:00:00+03:00', $booking['start']);
        self::assertSame('Обсудим запуск продукта', $booking['note']);
    }

    public function testEveryAvailableSlotCanBeBooked(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);

        $slots = $this->availableSlots('intro-call');
        self::assertNotEmpty($slots);

        // Каждый слот с available: true бронируется: состояние очищается между попытками.
        foreach ($slots as $start) {
            $this->clearBookings();

            $response = $this->request('POST', '/api/bookings', [
                'eventTypeId' => 'intro-call',
                'start' => $start,
                'name' => 'Анна',
                'email' => 'anna@example.com',
            ]);

            self::assertSame(201, $response->getStatusCode(), 'Слот должен бронироваться: ' . $start);
        }
    }

    public function testBookingMakesOverlappingSlotsUnavailable(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);

        $this->book('intro-call', '2026-10-12T10:00:00+03:00');

        $slots = $this->slotsByStart('intro-call');
        self::assertFalse($slots['2026-10-12T09:45:00+03:00'], '09:45 пересекается с записью 10:00–10:30');
        self::assertFalse($slots['2026-10-12T10:00:00+03:00']);
        self::assertFalse($slots['2026-10-12T10:15:00+03:00'], '10:15 требует ячейку 10:15, занятую записью');
        self::assertTrue($slots['2026-10-12T10:30:00+03:00'], 'Интервалы half-open: запись 10:00–10:30 не занимает ячейку 10:30');
    }

    public function testBookingOfOneEventTypeBlocksSlotsOfAnother(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);
        $this->createEventType('quick-call', 'Быстрый созвон', 15);

        $this->book('intro-call', '2026-10-12T10:00:00+03:00');

        $slots = $this->slotsByStart('quick-call');
        // Слот 09:45 у 15-минутного типа занимает только ячейку 09:45 — она свободна.
        self::assertTrue($slots['2026-10-12T09:45:00+03:00']);
        self::assertFalse($slots['2026-10-12T10:00:00+03:00'], 'Ячейка 10:00 занята записью другого типа');
        self::assertFalse($slots['2026-10-12T10:15:00+03:00'], 'Ячейка 10:15 занята записью другого типа');
        self::assertTrue($slots['2026-10-12T10:30:00+03:00']);
    }

    public function testUnknownEventTypeReturns404ProblemJson(): void
    {
        $response = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'nope',
            'start' => '2026-10-12T10:00:00+03:00',
            'name' => 'Анна',
            'email' => 'anna@example.com',
        ]);

        self::assertSame(404, $response->getStatusCode());
        $this->assertProblem($response, 404);
        $this->assertMatchesContract($response, 'post');
    }

    public function testOverlappingBookingReturns409AndLeavesNoTrace(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);
        $this->book('intro-call', '2026-10-12T10:00:00+03:00');

        // 10:15 требует ячейку 10:15, занятую записью 10:00–10:30.
        $conflict = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'intro-call',
            'start' => '2026-10-12T10:15:00+03:00',
            'name' => 'Иван',
            'email' => 'ivan@example.com',
        ]);

        self::assertSame(409, $conflict->getStatusCode());
        $this->assertProblem($conflict, 409);
        $this->assertMatchesContract($conflict, 'post');

        // Откат полный: не осталось ни ячеек, ни записи отклонённой попытки.
        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM bookings'));
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM booking_cells'));

        // Свободный промежуток после записи бронируется.
        $this->book('intro-call', '2026-10-12T10:30:00+03:00');
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM bookings'));
        self::assertSame(4, $this->scalar('SELECT COUNT(*) FROM booking_cells'));
    }

    public function testOverlappingBookingOfAnotherEventTypeReturns409(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);
        $this->createEventType('quick-call', 'Быстрый созвон', 15);
        $this->book('intro-call', '2026-10-12T10:00:00+03:00');

        $conflict = $this->request('POST', '/api/bookings', [
            'eventTypeId' => 'quick-call',
            'start' => '2026-10-12T10:00:00+03:00',
            'name' => 'Иван',
            'email' => 'ivan@example.com',
        ]);

        self::assertSame(409, $conflict->getStatusCode());
        $this->assertProblem($conflict, 409);
        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM bookings'));
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM booking_cells'));
    }

    public function testInvalidStartReturns422ProblemJson(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);

        $cases = [
            'вне окна 14 дней' => '2026-10-25T10:00:00+03:00',
            'не на сетке 15 минут' => '2026-10-12T10:07:00+03:00',
            'с секундами на сетке' => '2026-10-12T10:00:30+03:00',
            'до 09:00' => '2026-10-12T08:45:00+03:00',
            'не укладывается до 18:00' => '2026-10-12T17:45:00+03:00',
            'в прошлом' => '2026-10-11T11:45:00+03:00',
        ];

        foreach ($cases as $label => $start) {
            $response = $this->request('POST', '/api/bookings', [
                'eventTypeId' => 'intro-call',
                'start' => $start,
                'name' => 'Анна',
                'email' => 'anna@example.com',
            ]);

            self::assertSame(422, $response->getStatusCode(), $label);
            $this->assertProblem($response, 422);
            $this->assertMatchesContract($response, 'post');
        }

        // Ни одна отклонённая попытка не оставила записей.
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM bookings'));
        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM booking_cells'));
    }

    public function testSchemaViolationReturns400ProblemJson(): void
    {
        $this->createEventType('intro-call', 'Звонок', 30);

        $valid = [
            'eventTypeId' => 'intro-call',
            'start' => '2026-10-12T10:00:00+03:00',
            'name' => 'Анна',
            'email' => 'anna@example.com',
        ];

        $cases = [
            'start без смещения' => ['start' => '2026-10-12T10:00:00'],
            'пустое имя' => ['name' => ''],
            'имя длиннее 100' => ['name' => str_repeat('а', 101)],
            'неверный email' => ['email' => 'anna@'],
            'заметка длиннее 500' => ['note' => str_repeat('а', 501)],
            'нет имени' => null,
        ];

        foreach ($cases as $label => $override) {
            $payload = $override === null
                ? array_diff_key($valid, ['name' => true])
                : array_merge($valid, $override);

            $response = $this->request('POST', '/api/bookings', $payload);

            self::assertSame(400, $response->getStatusCode(), $label);
            $this->assertProblem($response, 400);
            $this->assertMatchesContract($response, 'post');
        }

        self::assertSame(0, $this->scalar('SELECT COUNT(*) FROM bookings'));
    }

    public function testSecondOverlappingInsertFailsEntirelyAtStorageLevel(): void
    {
        // Уровень хранилища, без HTTP: две попытки занять пересекающиеся ячейки.
        $database = new Database($this->databasePath);
        $repository = new BookingRepository($database, self::clockAt(self::NOW));

        $repository->create(
            self::uuid('1'),
            'intro-call',
            new DateTimeImmutable('2026-10-12 10:00:00+03:00', new DateTimeZone('Europe/Moscow')),
            30,
            'Анна',
            'anna@example.com',
            null,
        );

        try {
            $repository->create(
                self::uuid('2'),
                'quick-call',
                new DateTimeImmutable('2026-10-12 10:15:00+03:00', new DateTimeZone('Europe/Moscow')),
                15,
                'Иван',
                'ivan@example.com',
                null,
            );
            self::fail('Ожидалось пересечение ячеек');
        } catch (SlotConflictException) {
            // Ожидаемый конфликт: ячейка 10:15 уже занята.
        }

        // Вторая попытка падает целиком: ни одной ячейки и ни одной записи.
        self::assertSame(1, $this->scalar('SELECT COUNT(*) FROM bookings'));
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM booking_cells'));
        self::assertSame(0, $this->scalar(
            'SELECT COUNT(*) FROM booking_cells WHERE booking_id = :id',
            ['id' => self::uuid('2')],
        ));
    }

    public function testListBookingsReturnsUpcomingSortedByStartAcrossEventTypes(): void
    {
        $this->createEventType('intro-call', 'Звонок знакомства', 30);
        $this->createEventType('quick-call', 'Быстрый созвон', 15);

        // Записи создаются не по порядку: список обязан отсортироваться по start на сервере.
        $this->book('quick-call', '2026-10-13T11:00:00+03:00');
        $this->book('quick-call', '2026-10-12T15:00:00+03:00');
        $this->book('intro-call', '2026-10-12T10:00:00+03:00', [
            'name' => 'Анна',
            'email' => 'anna@example.com',
            'note' => 'Обсудим запуск продукта',
        ]);

        $response = $this->request('GET', '/api/bookings');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertMatchesContract($response, 'get');

        $data = $this->decode($response);
        self::assertSame('Europe/Moscow', $data['timezone']);
        self::assertSame(
            ['2026-10-12T10:00:00+03:00', '2026-10-12T15:00:00+03:00', '2026-10-13T11:00:00+03:00'],
            array_column($data['bookings'], 'start'),
        );

        // Записи разных типов в одном списке, схема — та же, что у createBooking.
        self::assertSame(['intro-call', 'quick-call', 'quick-call'], array_column($data['bookings'], 'eventTypeId'));
        self::assertSame(
            ['Звонок знакомства', 'Быстрый созвон', 'Быстрый созвон'],
            array_column($data['bookings'], 'eventTypeName'),
        );

        foreach ($data['bookings'] as $booking) {
            self::assertSame(
                ['id', 'eventTypeId', 'eventTypeName', 'start', 'durationMinutes', 'name', 'email', 'note'],
                array_keys($booking),
            );
            self::assertMatchesRegularExpression(self::UUID_PATTERN, $booking['id']);
        }

        $first = $data['bookings'][0];
        self::assertSame(30, $first['durationMinutes']);
        self::assertSame('Анна', $first['name']);
        self::assertSame('anna@example.com', $first['email']);
        self::assertSame('Обсудим запуск продукта', $first['note']);
    }

    public function testListBookingsExcludesPastBookingsAndKeepsCurrentMoment(): void
    {
        $this->createEventType('intro-call', 'Звонок знакомства', 30);
        $this->book('intro-call', '2026-10-12T10:00:00+03:00');
        $this->book('intro-call', '2026-10-12T13:00:00+03:00');

        // «Сейчас» равно началу первой записи: начало не раньше текущего момента — предстоящая.
        $this->advanceClockTo('2026-10-12 10:00:00');
        $atStart = $this->decode($this->request('GET', '/api/bookings'));
        self::assertSame(
            ['2026-10-12T10:00:00+03:00', '2026-10-12T13:00:00+03:00'],
            array_column($atStart['bookings'], 'start'),
        );

        // Начало прошло — запись исчезает из списка, вторая остаётся.
        $this->advanceClockTo('2026-10-12 10:00:01');
        $past = $this->decode($this->request('GET', '/api/bookings'));
        self::assertSame(['2026-10-12T13:00:00+03:00'], array_column($past['bookings'], 'start'));

        // Прошедшая запись отфильтрована, а не удалена.
        self::assertSame(2, $this->scalar('SELECT COUNT(*) FROM bookings'));
    }

    public function testListBookingsReturnsEmptyListWhenNothingIsUpcoming(): void
    {
        $this->createEventType('intro-call', 'Звонок знакомства', 30);

        $response = $this->request('GET', '/api/bookings');

        self::assertSame(200, $response->getStatusCode());
        $this->assertMatchesContract($response, 'get');

        $data = $this->decode($response);
        self::assertSame('Europe/Moscow', $data['timezone']);
        self::assertSame([], $data['bookings']);
    }

    private function createEventType(string $id, string $name, int $durationMinutes): void
    {
        $response = $this->request('POST', '/api/event-types', [
            'id' => $id,
            'name' => $name,
            'durationMinutes' => $durationMinutes,
        ]);

        self::assertSame(201, $response->getStatusCode());
    }

    /**
     * Создаёт запись через API и проверяет, что слот действительно занялся.
     *
     * @param array<string, string> $overrides поля гостя, переопределяющие значения по умолчанию
     */
    private function book(string $eventTypeId, string $start, array $overrides = []): void
    {
        $response = $this->request('POST', '/api/bookings', array_merge([
            'eventTypeId' => $eventTypeId,
            'start' => $start,
            'name' => 'Анна',
            'email' => 'anna@example.com',
        ], $overrides));

        self::assertSame(201, $response->getStatusCode(), 'Слот должен был записаться: ' . $start);
    }

    /** Пересобирает ядро с более поздним «сейчас»: прошедшие записи становятся неактуальными. */
    private function advanceClockTo(string $now): void
    {
        unset($this->kernel);
        $this->kernel = new Kernel(Config::fromEnv(), self::clockAt($now));
    }

    /** @return list<string> начала доступных слотов */
    private function availableSlots(string $eventTypeId): array
    {
        $slots = $this->slots($eventTypeId);
        $available = array_filter($slots, static fn (array $slot): bool => $slot['available']);

        return array_values(array_column($available, 'start'));
    }

    /** @return array<string, bool> доступность слота по началу */
    private function slotsByStart(string $eventTypeId): array
    {
        $byStart = [];

        foreach ($this->slots($eventTypeId) as $slot) {
            $byStart[$slot['start']] = $slot['available'];
        }

        return $byStart;
    }

    /** @return list<array{start: string, available: bool}> */
    private function slots(string $eventTypeId): array
    {
        $data = $this->decode($this->request('GET', sprintf('/api/event-types/%s/slots', $eventTypeId)));

        return $data['slots'];
    }

    /** Очищает записи, чтобы каждый доступный слот проверялся в изолированном состоянии. */
    private function clearBookings(): void
    {
        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->exec('DELETE FROM booking_cells');
        $pdo->exec('DELETE FROM bookings');
    }

    /** @param array<string, string> $bindings */
    private function scalar(string $sql, array $bindings = []): int
    {
        $pdo = new PDO('sqlite:' . $this->databasePath);
        $statement = $pdo->prepare($sql);
        $statement->execute($bindings);
        $value = $statement->fetchColumn();
        self::assertIsNumeric($value);

        return (int) $value;
    }

    /** Детерминированный UUID v4 для проверки сохранённых строк. */
    private static function uuid(string $seed): string
    {
        return str_pad($seed, 8, '0') . '-0000-4000-8000-000000000000';
    }

    private function request(string $method, string $path, ?array $payload = null): ResponseInterface
    {
        $headers = $payload === null ? [] : ['Content-Type' => 'application/json'];
        $body = $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->kernel()->handle(new ServerRequest($method, 'http://localhost' . $path, $headers, $body));
    }

    private function kernel(): Kernel
    {
        self::assertNotNull($this->kernel);

        return $this->kernel;
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
        self::responseValidator()->validate(new OperationAddress('/bookings', $method), $response);
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
