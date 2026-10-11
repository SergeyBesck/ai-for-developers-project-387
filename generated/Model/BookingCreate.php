<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class BookingCreate implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * Slug выбранного типа события.
     *
     * @var string
     */
    public string $eventTypeId;
    /**
     * Начало слота: ISO 8601 с обязательным смещением; без смещения — 400.
     *
     * @var \DateTime
     */
    public \DateTime $start;
    /**
     * @var string
     */
    public string $name;
    /**
     * @var string
     */
    public string $email;
    /**
     * @var string|null
     */
    public ?string $note;
    public function definedProperties(): array
    {
        return ['eventTypeId' => 'eventTypeId', 'start' => 'start', 'name' => 'name', 'email' => 'email', 'note' => 'note'];
    }
}