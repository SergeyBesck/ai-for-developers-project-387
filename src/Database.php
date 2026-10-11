<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

/**
 * SQLite-соединение. Схема создаётся на старте приложения (migrate() идемпотентен).
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private readonly string $path)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->ensureDirectoryExists();
            $this->pdo = new PDO('sqlite:' . $this->path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }

        return $this->pdo;
    }

    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->path);

        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Не удалось создать каталог для базы данных: %s', $directory));
        }
    }

    public function migrate(): void
    {
        $this->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS event_types (
                id TEXT NOT NULL PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                duration_minutes INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )',
        );

        $this->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS bookings (
                id TEXT NOT NULL PRIMARY KEY,
                event_type_id TEXT NOT NULL,
                start TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                note TEXT,
                created_at TEXT NOT NULL
            )',
        );

        // Ячейки записи: одна строка — шаг сетки 15 минут. Уникальный индекс по ячейке
        // гарантирует на уровне БД, что пересекающиеся записи не могут ужиться.
        $this->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS booking_cells (
                booking_id TEXT NOT NULL,
                cell INTEGER NOT NULL,
                PRIMARY KEY (booking_id, cell)
            )',
        );

        $this->pdo()->exec(
            'CREATE UNIQUE INDEX IF NOT EXISTS booking_cells_cell_unique ON booking_cells (cell)',
        );
    }
}
