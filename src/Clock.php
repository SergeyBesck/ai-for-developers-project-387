<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;

/**
 * Источник «сейчас». В проде собирается как SystemClock, в тестах подменяется фиксированным.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
