<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Системные часы в поясе приложения. Единственная реализация в проде: подмена кодом извне невозможна.
 */
final class SystemClock implements Clock
{
    public function __construct(private readonly string $timezone)
    {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone($this->timezone));
    }
}
