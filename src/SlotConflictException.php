<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Пересечение с существующей записью: хотя одна ячейка уже занята.
 * Транзакция создания откатывается целиком, обработчик отвечает 409 problem+json.
 */
final class SlotConflictException extends RuntimeException
{
    /** @param list<int> $occupiedUnixSeconds занятые ячейки (Unix-секунды начала) */
    public function __construct(public readonly array $occupiedUnixSeconds)
    {
        parent::__construct('Хотя бы одна ячейка выбранного времени уже занята другой записью');
    }
}
