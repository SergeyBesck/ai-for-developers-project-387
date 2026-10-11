<?php

declare(strict_types=1);

namespace App;

use RuntimeException;

/**
 * Дубликат id-слага: наружу уходит как 409 problem+json.
 */
final class DuplicateEventTypeException extends RuntimeException
{
    public function __construct(public readonly string $eventTypeId)
    {
        parent::__construct(sprintf('Тип события с id "%s" уже существует', $eventTypeId));
    }
}
