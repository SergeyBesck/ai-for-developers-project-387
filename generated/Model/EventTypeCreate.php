<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class EventTypeCreate implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * @var string
     */
    public string $id;
    /**
     * @var string
     */
    public string $name;
    /**
     * @var string|null
     */
    public ?string $description;
    /**
     * @var int
     */
    public int $durationMinutes;
    public function definedProperties(): array
    {
        return ['id' => 'id', 'name' => 'name', 'description' => 'description', 'durationMinutes' => 'durationMinutes'];
    }
}