<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class Booking implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * @var string
     */
    public string $id;
    /**
     * @var string
     */
    public string $eventTypeId;
    /**
     * @var string
     */
    public string $eventTypeName;
    /**
     * @var \DateTime
     */
    public \DateTime $start;
    /**
     * @var int
     */
    public int $durationMinutes;
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
        return ['id' => 'id', 'eventTypeId' => 'eventTypeId', 'eventTypeName' => 'eventTypeName', 'start' => 'start', 'durationMinutes' => 'durationMinutes', 'name' => 'name', 'email' => 'email', 'note' => 'note'];
    }
}