<?php

declare(strict_types=1);

namespace App;

final class Health
{
    /** @return array{status: string, php: string} */
    public static function check(): array
    {
        return ['status' => 'ok', 'php' => PHP_VERSION];
    }
}
