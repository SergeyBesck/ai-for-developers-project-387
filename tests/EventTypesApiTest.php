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
 * HTTP-тесты API типов событий на временной SQLite: путь БД задаётся переменной окружения,
 * «сейчас» фиксируется инжектированными часами, каждый ответ проверяется против openapi.json.
 */
final class EventTypesApiTest extends TestCase
{
    private string $databasePath = '';
    private ?Kernel $kernel = null;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'calendar-test-');
        self::assertIsString($path);
        $this->databasePath = $path;

        putenv(Config::DB_PATH_ENV . '=' . $this->databasePath);
        putenv(Config::TIMEZONE_ENV . '=Europe/Moscow');

        $clock = new class () implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-11 12:00:00', new DateTimeZone('Europe/Moscow'));
            }
        };

        $this->kernel = new Kernel(Config::fromEnv(), $clock);
    }

    protected function tearDown(): void
    {
        // SQLite-файл удаляется только после закрытия соединения.
        unset($this->kernel);
        gc_collect_cycles();

        putenv(Config::DB_PATH_ENV);
        putenv(Config::TIMEZONE_ENV);

        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    public function testCreateReturns201WithFullResource(): void
    {
        $response = $this->request('POST', '/api/event-types', [
            'id' => 'intro-call',
            'name' => 'Знакомство',
            'description' => 'Короткий созвон',
            'durationMinutes' => 15,
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertJsonContentType($response);
        self::assertSame(
            [
                'id' => 'intro-call',
                'name' => 'Знакомство',
                'description' => 'Короткий созвон',
                'durationMinutes' => 15,
            ],
            $this->decode($response),
        );
        $this->assertMatchesContract($response, '/event-types', 'post');
    }

    public function testCreateWithoutDescriptionReturnsNullDescription(): void
    {
        $response = $this->request('POST', '/api/event-types', [
            'id' => 'quick-call',
            'name' => 'Быстрый созвон',
            'durationMinutes' => 10,
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertNull($this->decode($response)['description']);
        $this->assertMatchesContract($response, '/event-types', 'post');
    }

    public function testDuplicateIdReturns409ProblemJson(): void
    {
        $this->request('POST', '/api/event-types', [
            'id' => 'consult',
            'name' => 'Консультация',
            'durationMinutes' => 30,
        ]);

        $response = $this->request('POST', '/api/event-types', [
            'id' => 'consult',
            'name' => 'Ещё консультация',
            'durationMinutes' => 45,
        ]);

        self::assertSame(409, $response->getStatusCode());
        $this->assertProblem($response, 409);
        $this->assertMatchesContract($response, '/event-types', 'post');
    }

    public function testSchemaViolationsReturn400ProblemJson(): void
    {
        $violations = [
            'slug не соответствует паттерну' => [
                'id' => 'Intro Call',
                'name' => 'Звонок',
                'durationMinutes' => 30,
            ],
            'длительность больше 540' => [
                'id' => 'long-call',
                'name' => 'Долгий звонок',
                'durationMinutes' => 541,
            ],
            'пустое обязательное поле' => [
                'id' => 'empty-name',
                'name' => '',
                'durationMinutes' => 30,
            ],
            'обязательное поле name отсутствует' => [
                'id' => 'no-name',
                'durationMinutes' => 30,
            ],
            'обязательное поле id отсутствует' => [
                'name' => 'Звонок',
                'durationMinutes' => 30,
            ],
            'обязательное поле durationMinutes отсутствует' => [
                'id' => 'no-duration',
                'name' => 'Звонок',
            ],
        ];

        foreach ($violations as $label => $payload) {
            $response = $this->request('POST', '/api/event-types', $payload);

            self::assertSame(400, $response->getStatusCode(), $label);
            $this->assertProblem($response, 400, $label);
            $this->assertMatchesContract($response, '/event-types', 'post');
        }
    }

    public function testListReturnsCreatedEventTypesInCreationOrder(): void
    {
        $this->request('POST', '/api/event-types', [
            'id' => 'intro-call',
            'name' => 'Знакомство',
            'durationMinutes' => 15,
        ]);
        $this->request('POST', '/api/event-types', [
            'id' => 'deep-dive',
            'name' => 'Разбор',
            'description' => 'Подробный разбор вопроса',
            'durationMinutes' => 60,
        ]);

        $response = $this->request('GET', '/api/event-types');

        self::assertSame(200, $response->getStatusCode());
        self::assertJsonContentType($response);
        self::assertSame(
            ['intro-call', 'deep-dive'],
            array_column($this->decode($response), 'id'),
        );
        $this->assertMatchesContract($response, '/event-types', 'get');
    }

    public function testGetReturns200ForKnownAnd404ForUnknownId(): void
    {
        $this->request('POST', '/api/event-types', [
            'id' => 'consult',
            'name' => 'Консультация',
            'durationMinutes' => 30,
        ]);

        $known = $this->request('GET', '/api/event-types/consult');

        self::assertSame(200, $known->getStatusCode());
        self::assertSame('consult', $this->decode($known)['id']);
        $this->assertMatchesContract($known, '/event-types/{id}', 'get');

        $unknown = $this->request('GET', '/api/event-types/nope');

        self::assertSame(404, $unknown->getStatusCode());
        $this->assertProblem($unknown, 404);
        $this->assertMatchesContract($unknown, '/event-types/{id}', 'get');
    }

    public function testUnknownPathReturns404ProblemJson(): void
    {
        $response = $this->request('GET', '/api/unknown');

        self::assertSame(404, $response->getStatusCode());
        $this->assertProblem($response, 404);
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

    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true);
        self::assertIsArray($data);

        return $data;
    }

    private function assertProblem(ResponseInterface $response, int $status, string $message = ''): void
    {
        self::assertSame(
            'application/problem+json',
            $response->getHeaderLine('Content-Type'),
            $message,
        );

        $problem = $this->decode($response);

        self::assertSame($status, $problem['status'], $message);
        self::assertNotEmpty($problem['type'], $message);
        self::assertNotEmpty($problem['title'], $message);
    }

    private static function assertJsonContentType(ResponseInterface $response): void
    {
        self::assertStringStartsWith('application/json', $response->getHeaderLine('Content-Type'));
    }

    /**
     * Ответ обязан удовлетворять контракту из openapi.json (league/openapi-psr7-validator):
     * валидатор бросает исключение, если ответ контракту не соответствует.
     */
    private function assertMatchesContract(ResponseInterface $response, string $path, string $method): void
    {
        self::responseValidator()->validate(new OperationAddress($path, strtolower($method)), $response);
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
