<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Расписание приёма записей: рабочие дни недели плюс одни и те же часы начала и конца
 * для всех рабочих дней. Одно на весь календарь; часовой пояс — пояс приложения (CALENDAR_TIMEZONE),
 * «сейчас» сюда не передаётся.
 */
final class Schedule
{
    /** Коды дней недели в ISO-порядке: индекс 0 — понедельник, 6 — воскресенье. */
    public const DAY_CODES = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Русские названия дней для сообщений об ошибках. */
    private const DAY_NAMES = [
        'mon' => 'понедельник',
        'tue' => 'вторник',
        'wed' => 'среда',
        'thu' => 'четверг',
        'fri' => 'пятница',
        'sat' => 'суббота',
        'sun' => 'воскресенье',
    ];

    private const TIME_PATTERN = '/^([01][0-9]|2[0-3]):(00|15|30|45)$/';

    /**
     * @param list<string> $workingDays коды дней mon..sun (ISO-порядок не обязателен)
     */
    private function __construct(
        public readonly array $workingDays,
        public readonly string $dayStart,
        public readonly string $dayEnd,
    ) {
    }

    /** Расписание по умолчанию до первого изменения Хостом: пн–пт, 10:00–18:00. */
    public static function default(): self
    {
        return new self(['mon', 'tue', 'wed', 'thu', 'fri'], '10:00', '18:00');
    }

    /**
     * @param list<string> $workingDays
     *
     * @throws InvalidArgumentException нет рабочих дней, неизвестный день, время не на сетке 15 минут
     *                                  или конец рабочего дня не позже начала
     */
    public static function create(array $workingDays, string $dayStart, string $dayEnd): self
    {
        if ($workingDays === []) {
            throw new InvalidArgumentException('Нужен хотя бы один рабочий день недели');
        }

        foreach ($workingDays as $code) {
            if (!in_array($code, self::DAY_CODES, true)) {
                throw new InvalidArgumentException(sprintf('Неизвестный день недели: %s', $code));
            }
        }

        foreach ([$dayStart, $dayEnd] as $time) {
            if (preg_match(self::TIME_PATTERN, $time) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Время должно быть в формате HH:MM и кратно 15 минутам: %s',
                    $time,
                ));
            }
        }

        if (self::minutes($dayEnd) <= self::minutes($dayStart)) {
            throw new InvalidArgumentException('Конец рабочего дня должен быть позже начала');
        }

        return new self($workingDays, $dayStart, $dayEnd);
    }

    /** Рабочий ли день недели, попадающий в указанную дату (день берётся из самой даты). */
    public function isWorkingDay(DateTimeInterface $date): bool
    {
        return in_array(self::DAY_CODES[(int) $date->format('N') - 1], $this->workingDays, true);
    }

    /** Начало рабочего дня в указанную дату, в поясе самой даты. */
    public function dayStartOn(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTime(self::hour($this->dayStart), self::minute($this->dayStart));
    }

    /** Конец рабочего дня в указанную дату, в поясе самой даты. */
    public function dayEndOn(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTime(self::hour($this->dayEnd), self::minute($this->dayEnd));
    }

    /** Название дня недели даты в родительном падеже: «суббота». */
    public static function dayName(DateTimeInterface $date): string
    {
        return self::DAY_NAMES[self::DAY_CODES[(int) $date->format('N') - 1]];
    }

    /**
     * Расписание в форме API (openapi-схема Schedule).
     *
     * @return array{workingDays: list<string>, dayStart: string, dayEnd: string}
     */
    public function toApi(): array
    {
        return [
            'workingDays' => $this->workingDays,
            'dayStart' => $this->dayStart,
            'dayEnd' => $this->dayEnd,
        ];
    }

    /** Минуты от полуночи для времени формата HH:MM (формат проверен create()). */
    private static function minutes(string $time): int
    {
        return self::hour($time) * 60 + self::minute($time);
    }

    private static function hour(string $time): int
    {
        return (int) substr($time, 0, 2);
    }

    private static function minute(string $time): int
    {
        return (int) substr($time, 3, 2);
    }
}
