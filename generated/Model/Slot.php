<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class Slot implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * Начало слота: ISO 8601 с обязательным смещением локального пояса приложения.
     *
     * @var \DateTime
     */
    public \DateTime $start;
    /**
     * @var bool
     */
    public bool $available;
    public function definedProperties(): array
    {
        return ['start' => 'start', 'available' => 'available'];
    }
}