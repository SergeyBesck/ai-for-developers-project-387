<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class Schedule implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * Рабочие дни недели; в нерабочие дни слоты не предлагаются, записи не принимаются.
     *
     * @var list<string>
     */
    public array $workingDays;
    /**
     * Начало рабочего дня: HH:MM, кратно 15 минутам, в поясе приложения.
     *
     * @var string
     */
    public string $dayStart;
    /**
     * Конец рабочего дня: HH:MM, кратно 15 минутам, строго позже начала.
     *
     * @var string
     */
    public string $dayEnd;
    public function definedProperties(): array
    {
        return ['workingDays' => 'workingDays', 'dayStart' => 'dayStart', 'dayEnd' => 'dayEnd'];
    }
}