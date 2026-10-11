<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class BookingsResponse implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * IANA-идентификатор часового пояса приложения.
     *
     * @var string
     */
    public string $timezone;
    /**
     * @var list<Booking>
     */
    public array $bookings;
    public function definedProperties(): array
    {
        return ['timezone' => 'timezone', 'bookings' => 'bookings'];
    }
}