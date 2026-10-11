<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Чистые функции доменного расписания: сетка слотов и расчёт занятости ячейками.
 *
 * «сейчас» приходит извне (инжектируемые часы), системное время здесь не читается.
 * Список слотов (listSlots) и создание записи (createBooking) обязаны использовать
 * эти же функции, а не копировать логику.
 */
final class SlotGrid
{
    /** Шаг сетки в минутах (константа; смена шага — миграция). */
    public const STEP_MINUTES = 15;

    /** Окно записи: сегодня — день 1, последний день окна — сегодня + 13. */
    public const WINDOW_DAYS = 14;

    private const DAY_START_HOUR = 9;
    private const DAY_END_HOUR = 18;

    /**
     * Округление момента вверх до сетки 15 минут: 12:00 и 12:15 остаются, 12:07 → 12:15.
     * Подсекундный шум отбрасывается.
     */
    public static function ceilToStep(DateTimeImmutable $moment): DateTimeImmutable
    {
        $minute = (int) $moment->format('i');
        $remainder = $minute % self::STEP_MINUTES;
        $onGrid = $remainder === 0
            && (int) $moment->format('s') === 0
            && (int) $moment->format('u') === 0;

        if ($onGrid) {
            return $moment->setTime((int) $moment->format('G'), $minute, 0, 0);
        }

        $rounded = $moment->modify(sprintf('+%d minutes', self::STEP_MINUTES - $remainder));

        return $rounded->setTime((int) $rounded->format('G'), (int) $rounded->format('i'), 0, 0);
    }

    /**
     * Длительность, округлённая вверх до кратной шагу: 20 → 30.
     */
    public static function roundedDurationMinutes(int $durationMinutes): int
    {
        $remainder = $durationMinutes % self::STEP_MINUTES;

        return $remainder === 0 ? $durationMinutes : $durationMinutes + (self::STEP_MINUTES - $remainder);
    }

    /**
     * Ключи ячеек сетки, которые занимает интервал [start; start + округлённая длительность).
     * Ячейка — шаг сетки; ключ — Unix-секунды начала ячейки. Начало интервала ловится на сетке.
     *
     * @return list<int>
     */
    public static function cells(DateTimeImmutable $start, int $durationMinutes): array
    {
        $count = intdiv(self::roundedDurationMinutes($durationMinutes), self::STEP_MINUTES);
        $cells = [];

        for ($index = 0; $index < $count; $index++) {
            $cells[] = $start
                ->modify(sprintf('+%d minutes', $index * self::STEP_MINUTES))
                ->getTimestamp();
        }

        return $cells;
    }

    /**
     * Причина, по которой начало не может быть началом слота, или null, если начало допустимо.
     * Правила те же, что в slotStarts: «сейчас» — из инжектируемых часов, начало на сетке,
     * внутри рабочего дня 09:00–18:00 (с округлённой вверх длительностью), в окне 14 дней
     * и не в прошлом.
     */
    public static function rejectStart(
        DateTimeImmutable $now,
        string $timezone,
        int $durationMinutes,
        DateTimeImmutable $start,
    ): ?string {
        $appTimezone = new DateTimeZone($timezone);
        $now = $now->setTimezone($appTimezone);
        $start = $start->setTimezone($appTimezone);

        if ($start < $now) {
            return 'Время записи уже прошло';
        }

        $minute = (int) $start->format('i');

        if (
            $minute % self::STEP_MINUTES !== 0
            || (int) $start->format('s') !== 0
            || (int) $start->format('u') !== 0
        ) {
            return sprintf('Начало должно стоять на сетке %d минут', self::STEP_MINUTES);
        }

        $today = self::ceilToStep($now)->setTime(0, 0, 0);
        $windowEnd = $today->modify(sprintf('+%d days', self::WINDOW_DAYS));

        if ($start >= $windowEnd) {
            return sprintf('Время записи дальше окна в %d дней', self::WINDOW_DAYS);
        }

        // Границы рабочего дня проверяются для дня, в который попадает начало.
        if ($start < $start->setTime(self::DAY_START_HOUR, 0, 0)) {
            return sprintf('Запись раньше начала рабочего дня (%02d:00)', self::DAY_START_HOUR);
        }

        $workdayEnd = $start->setTime(self::DAY_END_HOUR, 0, 0);
        $duration = self::roundedDurationMinutes($durationMinutes);

        if ($start->modify(sprintf('+%d minutes', $duration)) > $workdayEnd) {
            return sprintf('Запись не укладывается до конца рабочего дня (%02d:00)', self::DAY_END_HOUR);
        }

        return null;
    }

    /**
     * Начала слотов на окно записи в поясе приложения: от «сейчас», округлённого вверх до сетки,
     * до конца 14-го дня (сегодня — день 1). Каждый день — 09:00–18:00; начало строго на сетке;
     * слот есть, только если округлённая вверх длительность укладывается до 18:00.
     *
     * @return list<DateTimeImmutable>
     */
    public static function slotStarts(DateTimeImmutable $now, string $timezone, int $durationMinutes): array
    {
        $windowStart = self::ceilToStep($now->setTimezone(new DateTimeZone($timezone)));
        $duration = self::roundedDurationMinutes($durationMinutes);
        $today = $windowStart->setTime(0, 0, 0);
        $starts = [];

        for ($day = 0; $day < self::WINDOW_DAYS; $day++) {
            $date = $today->modify(sprintf('+%d days', $day));
            $workdayStart = $date->setTime(self::DAY_START_HOUR, 0, 0);
            $workdayEnd = $date->setTime(self::DAY_END_HOUR, 0, 0);
            $from = ($day === 0 && $windowStart > $workdayStart) ? $windowStart : $workdayStart;

            for (
                $start = $from;
                $start->modify(sprintf('+%d minutes', $duration)) <= $workdayEnd;
                $start = $start->modify(sprintf('+%d minutes', self::STEP_MINUTES))
            ) {
                $starts[] = $start;
            }
        }

        return $starts;
    }

    /**
     * Занятость по ячейкам всех типов событий: слот доступен, пока ни одна из его ячеек
     * не занята записью. Интервалы half-open: запись до 10:30 не занимает ячейку 10:30.
     *
     * @param list<DateTimeImmutable>                              $starts
     * @param list<array{start: DateTimeImmutable, durationMinutes: int}> $bookings
     *
     * @return list<array{start: DateTimeImmutable, available: bool}>
     */
    public static function availability(array $starts, int $durationMinutes, array $bookings): array
    {
        $occupied = [];
        foreach ($bookings as $booking) {
            foreach (self::cells($booking['start'], $booking['durationMinutes']) as $cell) {
                $occupied[$cell] = true;
            }
        }

        $slots = [];
        foreach ($starts as $start) {
            $available = true;

            foreach (self::cells($start, $durationMinutes) as $cell) {
                if (isset($occupied[$cell])) {
                    $available = false;
                    break;
                }
            }

            $slots[] = ['start' => $start, 'available' => $available];
        }

        return $slots;
    }
}
