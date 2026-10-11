<?php

declare(strict_types=1);

use App\Clock;
use App\Database;
use App\DuplicateEventTypeException;
use App\EventTypeRepository;
use PHPUnit\Framework\TestCase;

/**
 * Шов «сейчас»: репозиторий берёт момент создания из инжектируемых часов.
 */
final class EventTypeRepositoryTest extends TestCase
{
    private Database $database;
    private EventTypeRepository $eventTypes;

    protected function setUp(): void
    {
        $this->database = new Database(':memory:');
        $this->database->migrate();

        $clock = new class () implements Clock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-10-11 12:00:00', new DateTimeZone('Europe/Moscow'));
            }
        };

        $this->eventTypes = new EventTypeRepository($this->database, $clock);
    }

    public function testCreatedAtComesFromInjectedClock(): void
    {
        $this->eventTypes->create('intro-call', 'Знакомство', null, 15);

        $statement = $this->database->pdo()->prepare('SELECT created_at FROM event_types WHERE id = :id');
        $statement->execute(['id' => 'intro-call']);
        $row = $statement->fetch();

        self::assertIsArray($row);
        self::assertSame('2026-10-11T12:00:00+03:00', $row['created_at']);
    }

    public function testDuplicateIdIsReportedAsDuplicate(): void
    {
        $this->eventTypes->create('consult', 'Консультация', 'Разбор вопроса', 30);

        $this->expectException(DuplicateEventTypeException::class);

        $this->eventTypes->create('consult', 'Ещё консультация', null, 45);
    }

    public function testStoredResourceIsReturnedAsContractShapedArray(): void
    {
        $eventType = $this->eventTypes->create('deep-dive', 'Разбор', 'Подробный разбор', 60);

        self::assertSame(
            [
                'id' => 'deep-dive',
                'name' => 'Разбор',
                'description' => 'Подробный разбор',
                'durationMinutes' => 60,
            ],
            $eventType,
        );
        self::assertSame($eventType, $this->eventTypes->find('deep-dive'));
        self::assertSame([$eventType], $this->eventTypes->all());
        self::assertNull($this->eventTypes->find('missing'));
    }
}
