<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class SlotsResponse implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * IANA-идентификатор часового пояса приложения, например Europe/Moscow.
     *
     * @var string
     */
    public string $timezone;
    /**
     * @var list<Slot>
     */
    public array $slots;
    public function definedProperties(): array
    {
        return ['timezone' => 'timezone', 'slots' => 'slots'];
    }
}