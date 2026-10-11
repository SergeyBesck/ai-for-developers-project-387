<?php

declare(strict_types=1);

use App\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(Config::DB_PATH_ENV);
        putenv(Config::TIMEZONE_ENV);
    }

    public function testDatabasePathAndTimezoneComeFromEnvironment(): void
    {
        $databasePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'calendar-from-env.sqlite';
        putenv(Config::DB_PATH_ENV . '=' . $databasePath);
        putenv(Config::TIMEZONE_ENV . '=UTC');

        $config = Config::fromEnv();

        self::assertSame($databasePath, $config->databasePath);
        self::assertSame('UTC', $config->timezone);
    }

    public function testDefaultsAreUsedWhenEnvironmentIsEmpty(): void
    {
        putenv(Config::DB_PATH_ENV);
        putenv(Config::TIMEZONE_ENV);

        $config = Config::fromEnv();

        self::assertStringEndsWith(
            'var/calendar.sqlite',
            str_replace('\\', '/', $config->databasePath),
        );
        self::assertSame('Europe/Moscow', $config->timezone);
    }

    public function testUnknownTimezoneIsRejected(): void
    {
        putenv(Config::TIMEZONE_ENV . '=Mars/Olympus_Mons');

        $this->expectException(InvalidArgumentException::class);

        Config::fromEnv();
    }
}
