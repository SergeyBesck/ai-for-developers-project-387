<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class EventType implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * Slug-идентификатор, уникален.
     *
     * @var string
     */
    public string $id;
    /**
     * @var string
     */
    public string $name;
    /**
     * Описание; null, если не задано.
     *
     * @var string|null
     */
    public ?string $description;
    /**
     * Длительность в минутах, 1..540 (длина рабочего дня).
     *
     * @var int
     */
    public int $durationMinutes;
    public function definedProperties(): array
    {
        return ['id' => 'id', 'name' => 'name', 'description' => 'description', 'durationMinutes' => 'durationMinutes'];
    }
}