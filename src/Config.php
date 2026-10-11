<?php

declare(strict_types=1);

namespace App;

use DateTimeZone;
use InvalidArgumentException;

/**
 * Конфигурация приложения из окружения: путь к SQLite-файлу и часовой пояс.
 */
final class Config
{
    public const DB_PATH_ENV = 'CALENDAR_DB_PATH';
    public const TIMEZONE_ENV = 'CALENDAR_TIMEZONE';

    private const DEFAULT_TIMEZONE = 'Europe/Moscow';

    public function __construct(
        public readonly string $databasePath,
        public readonly string $timezone,
    ) {
    }

    public static function fromEnv(): self
    {
        $timezone = self::env(self::TIMEZONE_ENV) ?? self::DEFAULT_TIMEZONE;

        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException(sprintf('Неизвестный часовой пояс: %s', $timezone));
        }

        return new self(
            self::env(self::DB_PATH_ENV) ?? dirname(__DIR__) . '/var/calendar.sqlite',
            $timezone,
        );
    }

    private static function env(string $name): ?string
    {
        $value = getenv($name);

        return ($value === false || $value === '') ? null : $value;
    }
}
