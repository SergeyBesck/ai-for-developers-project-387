<?php

declare(strict_types=1);

namespace App;

use DateTimeInterface;
use PDO;
use PDOException;

/**
 * Хранилище типов событий в SQLite. Момент создания берётся из инжектируемых часов.
 */
final class EventTypeRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<array{id: string, name: string, description: string|null, durationMinutes: int}>
     */
    public function all(): array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, name, description, duration_minutes FROM event_types ORDER BY created_at, rowid',
        );
        $statement->execute();
        $rows = $statement->fetchAll();

        $eventTypes = [];
        foreach ($rows as $row) {
            $eventTypes[] = $this->toResource($row);
        }

        return $eventTypes;
    }

    /**
     * @return array{id: string, name: string, description: string|null, durationMinutes: int}|null
     */
    public function find(string $id): ?array
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT id, name, description, duration_minutes FROM event_types WHERE id = :id',
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $this->toResource($row) : null;
    }

    /**
     * @return array{id: string, name: string, description: string|null, durationMinutes: int}
     *
     * @throws DuplicateEventTypeException
     */
    public function create(string $id, string $name, ?string $description, int $durationMinutes): array
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO event_types (id, name, description, duration_minutes, created_at)
             VALUES (:id, :name, :description, :durationMinutes, :createdAt)',
        );

        try {
            $statement->bindValue(':id', $id);
            $statement->bindValue(':name', $name);
            $statement->bindValue(':description', $description);
            $statement->bindValue(':durationMinutes', $durationMinutes, PDO::PARAM_INT);
            $statement->bindValue(':createdAt', $this->clock->now()->format(DateTimeInterface::ATOM));
            $statement->execute();
        } catch (PDOException $exception) {
            $errorInfo = $exception->errorInfo;

            if (is_array($errorInfo) && ($errorInfo[0] ?? null) === '23000') {
                throw new DuplicateEventTypeException($id);
            }

            throw $exception;
        }

        return [
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'durationMinutes' => $durationMinutes,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, name: string, description: string|null, durationMinutes: int}
     */
    private function toResource(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'description' => $row['description'] === null ? null : (string) $row['description'],
            'durationMinutes' => (int) $row['duration_minutes'],
        ];
    }
}
