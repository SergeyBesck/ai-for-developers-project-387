<?php

declare(strict_types=1);

use App\Schedule;
use PHPUnit\Framework\TestCase;

/**
 * Доменное расписание: значения по умолчанию, проверки create() и границы рабочего дня.
 */
final class ScheduleTest extends TestCase
{
    public function testDefaultIsWeekdaysFromTenToEighteen(): void
    {
        $schedule = Schedule::default();

        self::assertSame(['mon', 'tue', 'wed', 'thu', 'fri'], $schedule->workingDays);
        self::assertSame('10:00', $schedule->dayStart);
        self::assertSame('18:00', $schedule->dayEnd);
    }

    public function testIsWorkingDayFollowsTheIsoWeek(): void
    {
        $schedule = Schedule::create(['tue', 'thu'], '10:00', '18:00');

        self::assertFalse($schedule->isWorkingDay(new DateTimeImmutable('2026-10-12 10:00:00')));
        self::assertTrue($schedule->isWorkingDay(new DateTimeImmutable('2026-10-13 10:00:00')));
        self::assertFalse($schedule->isWorkingDay(new DateTimeImmutable('2026-10-14 10:00:00')));
        self::assertTrue($schedule->isWorkingDay(new DateTimeImmutable('2026-10-15 10:00:00')));
    }

    public function testDayBoundsKeepTheDateAndTimezone(): void
    {
        $schedule = Schedule::create(['mon'], '09:30', '14:45');
        $date = new DateTimeImmutable('2026-10-12 00:00:00', new DateTimeZone('Europe/Moscow'));

        self::assertSame('2026-10-12 09:30:00', $schedule->dayStartOn($date)->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-12 14:45:00', $schedule->dayEndOn($date)->format('Y-m-d H:i:s'));
        self::assertSame('Europe/Moscow', $schedule->dayStartOn($date)->getTimezone()->getName());
    }

    public function testToApiShapeMatchesTheContract(): void
    {
        self::assertSame(
            ['workingDays' => ['sat', 'sun'], 'dayStart' => '12:00', 'dayEnd' => '14:00'],
            Schedule::create(['sat', 'sun'], '12:00', '14:00')->toApi(),
        );
    }

    public function testCreateDeduplicatesWorkingDays(): void
    {
        $schedule = Schedule::create(['mon', 'tue', 'mon', 'wed', 'tue'], '10:00', '18:00');

        self::assertSame(['mon', 'tue', 'wed'], $schedule->workingDays);
        self::assertSame(['mon', 'tue', 'wed'], $schedule->toApi()['workingDays']);
    }

    public function testCreateRejectsInvalidSchedules(): void
    {
        $cases = [
            'нет рабочих дней' => [[], '10:00', '18:00'],
            'неизвестный день' => [['mon', 'someday'], '10:00', '18:00'],
            'время без ведущего нуля' => [['mon'], '9:00', '18:00'],
            'минуты не кратны 15' => [['mon'], '10:07', '18:00'],
            'конец раньше начала' => [['mon'], '18:00', '10:00'],
            'конец равен началу' => [['mon'], '10:00', '10:00'],
        ];

        foreach ($cases as $label => [$days, $start, $end]) {
            try {
                Schedule::create($days, $start, $end);
                self::fail($label);
            } catch (InvalidArgumentException $exception) {
                self::assertNotEmpty($exception->getMessage(), $label);
            }
        }
    }
}
