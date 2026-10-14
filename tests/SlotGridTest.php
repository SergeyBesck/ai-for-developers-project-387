<?php

declare(strict_types=1);

use App\Schedule;
use App\SlotGrid;
use PHPUnit\Framework\TestCase;

/**
 * Шов «чистые функции расчёта»: сетка слотов и занятость ячейками с инжектируемыми часами
 * и расписанием. Граничные случаи из Testing Decisions: округление вверх, граница окна,
 * граница рабочего дня, «сейчас» на границе ячейки, half-open интервалы.
 */
final class SlotGridTest extends TestCase
{
    private const TZ = 'Europe/Moscow';

    public function testCeilToStepKeepsMomentsOnTheGrid(): void
    {
        $onGrid = self::moment('2026-10-11 12:00:00');

        self::assertSame('2026-10-11 12:00:00', SlotGrid::ceilToStep($onGrid)->format('Y-m-d H:i:s'));
    }

    public function testCeilToStepRoundsUpToTheNextStep(): void
    {
        $cases = [
            '2026-10-11 12:01:00' => '2026-10-11 12:15:00',
            '2026-10-11 12:14:59' => '2026-10-11 12:15:00',
            '2026-10-11 12:15:30' => '2026-10-11 12:30:00',
            '2026-10-11 08:59:59' => '2026-10-11 09:00:00',
        ];

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, SlotGrid::ceilToStep(self::moment($input))->format('Y-m-d H:i:s'), $input);
        }
    }

    public function testRoundedDurationRoundsUpToStep(): void
    {
        self::assertSame(15, SlotGrid::roundedDurationMinutes(15));
        self::assertSame(45, SlotGrid::roundedDurationMinutes(45));
        self::assertSame(30, SlotGrid::roundedDurationMinutes(20));
        self::assertSame(30, SlotGrid::roundedDurationMinutes(16));
    }

    public function testCellsCoverRoundedUpInterval(): void
    {
        // 20 минут → 2 ячейки сетки.
        $start = self::moment('2026-10-11 10:00:00');

        self::assertSame(
            [$start->getTimestamp(), $start->modify('+15 minutes')->getTimestamp()],
            SlotGrid::cells($start, 20),
        );
    }

    public function testSlotStartsRunFromCeilOfNowToFourteenthDay(): void
    {
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 12:07:00'), self::TZ, 30, self::everyDaySchedule());

        self::assertSame('2026-10-11 12:15:00', $starts[0]->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-24 17:30:00', end($starts)->format('Y-m-d H:i:s'));
        // Сегодня с 12:15 до 17:30 — 22 слота, ещё 13 полных дней по 35 слотов.
        self::assertCount(477, $starts);
    }

    public function testNowOnGridBoundaryIncludesCurrentSlot(): void
    {
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 12:00:00'), self::TZ, 30, self::everyDaySchedule());

        self::assertSame('2026-10-11 12:00:00', $starts[0]->format('Y-m-d H:i:s'));
        self::assertCount(478, $starts);
    }

    public function testNowIsConvertedToApplicationTimezone(): void
    {
        // 09:30 UTC — это 12:30 в Europe/Moscow.
        $now = new DateTimeImmutable('2026-10-11 09:30:00', new DateTimeZone('UTC'));

        $starts = SlotGrid::slotStarts($now, self::TZ, 30, self::everyDaySchedule());

        self::assertSame('2026-10-11 12:30:00', $starts[0]->format('Y-m-d H:i:s'));
    }

    public function testBeforeWorkdayStartsAtScheduleStart(): void
    {
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 08:07:00'), self::TZ, 30, self::everyDaySchedule());

        self::assertSame('2026-10-11 09:00:00', $starts[0]->format('Y-m-d H:i:s'));
    }

    public function testAfterWorkdayHidesTodayAndStartsTomorrow(): void
    {
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 19:00:00'), self::TZ, 30, self::everyDaySchedule());

        self::assertSame('2026-10-12 09:00:00', $starts[0]->format('Y-m-d H:i:s'));
    }

    public function testLastSlotOfDayFitsBeforeEnd(): void
    {
        // 15 минут укладываются до 18:00 с 17:45; округлённые 20 минут (30) — только с 17:30.
        $fifteen = SlotGrid::slotStarts(self::moment('2026-10-11 12:00:00'), self::TZ, 15, self::everyDaySchedule());
        self::assertSame('2026-10-11 17:45:00', $fifteen[23]->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-12 09:00:00', $fifteen[24]->format('Y-m-d H:i:s'));

        $rounded = SlotGrid::slotStarts(self::moment('2026-10-11 12:00:00'), self::TZ, 20, self::everyDaySchedule());
        self::assertSame('2026-10-11 17:30:00', $rounded[22]->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-12 09:00:00', $rounded[23]->format('Y-m-d H:i:s'));
    }

    public function testSlotEndingExactlyAtEndOfLastWindowDayIsIncluded(): void
    {
        // Последний день окна — сегодня + 13; слот 17:30 на30 минут оканчивается ровно в 18:00.
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 12:00:00'), self::TZ, 30, self::everyDaySchedule());

        self::assertSame('2026-10-24 17:30:00', end($starts)->format('Y-m-d H:i:s'));
    }

    public function testFullDayDurationLeavesOnlyTheFirstSlot(): void
    {
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 08:00:00'), self::TZ, 540, self::everyDaySchedule());

        self::assertSame('2026-10-11 09:00:00', $starts[0]->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-12 09:00:00', $starts[1]->format('Y-m-d H:i:s'));
        self::assertCount(14, $starts);
    }

    public function testDefaultScheduleSkipsWeekendsAndRunsFromTenToEighteen(): void
    {
        // «Сейчас» — воскресенье 11-го: сегодня нерабочий день, окно начинается с понедельника.
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 12:00:00'), self::TZ, 30, self::defaultSchedule());

        self::assertSame('2026-10-12 10:00:00', $starts[0]->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-23 17:30:00', end($starts)->format('Y-m-d H:i:s'));
        // В окне 10 рабочих дней (12–16 и 19–23 октября) по 31 слоту (10:00–17:30).
        self::assertCount(310, $starts);

        foreach ($starts as $start) {
            self::assertNotContains((int) $start->format('N'), [6, 7], $start->format('Y-m-d H:i:s'));
        }
    }

    public function testCustomScheduleMovesWorkIntoTheWeekendAndShortensTheDay(): void
    {
        // Только суббота с 12:00 до 14:00: 30 минут укладываются с 12:00 до 13:30.
        $schedule = Schedule::create(['sat'], '12:00', '14:00');
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 08:00:00'), self::TZ, 30, $schedule);

        self::assertSame('2026-10-17 12:00:00', $starts[0]->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-24 13:30:00', end($starts)->format('Y-m-d H:i:s'));
        // Две субботы в окне по 7 слотов: 12:00–13:30 с шагом 15 минут.
        self::assertCount(14, $starts);

        foreach ($starts as $start) {
            self::assertSame(6, (int) $start->format('N'), $start->format('Y-m-d H:i:s'));
        }
    }

    public function testBookingBlocksOverlappingSlotsOfAnyDuration(): void
    {
        // Запись 10:00 на30 минут (ячейки 10:00 и 10:15) блокирует слоты 09:45, 10:00 и 10:15.
        $starts = array_map(
            fn (string $time): DateTimeImmutable => self::moment('2026-10-11 ' . $time),
            ['09:45:00', '10:00:00', '10:15:00', '10:30:00'],
        );

        $slots = SlotGrid::availability($starts, 30, [
            ['start' => self::moment('2026-10-11 10:00:00'), 'durationMinutes' => 30],
        ]);

        self::assertSame([false, false, false, true], array_column($slots, 'available'));
    }

    public function testAvailabilityIsHalfOpen(): void
    {
        // Запись до 10:30 не занимает ячейку 10:30; запись с 10:30 не занимает ячейку 10:15.
        $starts = [
            self::moment('2026-10-11 10:30:00'),
            self::moment('2026-10-11 10:15:00'),
        ];

        $slots = SlotGrid::availability($starts, 15, [
            ['start' => self::moment('2026-10-11 09:00:00'), 'durationMinutes' => 90],
            ['start' => self::moment('2026-10-11 10:45:00'), 'durationMinutes' => 15],
        ]);

        self::assertSame([true, false], array_column($slots, 'available'));
    }

    public function testSlotsAreAvailableWhileThereAreNoBookings(): void
    {
        $starts = SlotGrid::slotStarts(self::moment('2026-10-11 12:00:00'), self::TZ, 30, self::defaultSchedule());

        $slots = SlotGrid::availability($starts, 30, []);

        self::assertCount(count($starts), $slots);
        self::assertContains(true, array_column($slots, 'available'));
        self::assertCount(0, array_filter($slots, static fn (array $slot): bool => !$slot['available']));
    }

    public function testRejectStartAcceptsEverySlotStart(): void
    {
        // Согласованность по построению: любое начало из slotStarts допустимо для записи —
        // для полного расписания и для расписания по умолчанию.
        $now = self::moment('2026-10-11 12:00:00');

        foreach ([self::everyDaySchedule(), self::defaultSchedule()] as $schedule) {
            foreach (SlotGrid::slotStarts($now, self::TZ, 30, $schedule) as $start) {
                self::assertNull(
                    SlotGrid::rejectStart($now, self::TZ, 30, $start, $schedule),
                    $start->format('Y-m-d H:i:s'),
                );
            }
        }
    }

    public function testRejectStartExplainsEachRule(): void
    {
        $now = self::moment('2026-10-11 12:00:00');
        $cases = [
            '2026-10-11 11:45:00' => 'Время записи уже прошло',
            '2026-10-11 12:07:00' => 'Начало должно стоять на сетке 15 минут',
            '2026-10-11 12:15:00.500000' => 'Начало должно стоять на сетке 15 минут',
            '2026-10-12 08:45:00' => 'Запись раньше начала рабочего дня (09:00)',
            '2026-10-12 17:45:00' => 'Запись не укладывается до конца рабочего дня (18:00)',
            '2026-10-12 18:00:00' => 'Запись не укладывается до конца рабочего дня (18:00)',
            '2026-10-25 10:00:00' => 'Время записи дальше окна в 14 дней',
            '2026-10-24 17:30:00' => null,
        ];

        foreach ($cases as $start => $expected) {
            self::assertSame(
                $expected,
                SlotGrid::rejectStart($now, self::TZ, 30, self::moment($start), self::everyDaySchedule()),
                $start,
            );
        }
    }

    public function testRejectStartWithDefaultScheduleExplainsScheduleRules(): void
    {
        $now = self::moment('2026-10-11 12:00:00');
        $cases = [
            '2026-10-11 13:00:00' => 'Нерабочий день недели: воскресенье',
            '2026-10-17 10:00:00' => 'Нерабочий день недели: суббота',
            '2026-10-12 09:45:00' => 'Запись раньше начала рабочего дня (10:00)',
            '2026-10-12 10:00:00' => null,
            '2026-10-12 17:30:00' => null,
            '2026-10-12 17:45:00' => 'Запись не укладывается до конца рабочего дня (18:00)',
        ];

        foreach ($cases as $start => $expected) {
            self::assertSame(
                $expected,
                SlotGrid::rejectStart($now, self::TZ, 30, self::moment($start), self::defaultSchedule()),
                $start,
            );
        }
    }

    /** Полное расписание «все дни, 09:00–18:00»: сетка и окно проверяются отдельно от расписания. */
    private static function everyDaySchedule(): Schedule
    {
        return Schedule::create(Schedule::DAY_CODES, '09:00', '18:00');
    }

    /** Расписание по умолчанию: пн–пт, 10:00–18:00. */
    private static function defaultSchedule(): Schedule
    {
        return Schedule::default();
    }

    private static function moment(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone(self::TZ));
    }
}
