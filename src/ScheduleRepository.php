<?php

declare(strict_types=1);

namespace App;

use JsonException;
use RuntimeException;

/**
 * Хранилище расписания в SQLite: одна строка (id = 1).
 *
 * Пока Хост ни разу не менял расписание, строки нет — отдаётся расписание по умолчанию
 * (App\Schedule::default), оно нигде не дублируется в базе.
 */
final class ScheduleRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    public function get(): Schedule
    {
        $statement = $this->database->pdo()->prepare(
            'SELECT working_days, day_start, day_end FROM schedule WHERE id = 1',
        );
        $statement->execute();
        $row = $statement->fetch();

        if (!is_array($row)) {
            return Schedule::default();
        }

        try {
            $workingDays = json_decode((string) $row['working_days'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Расписание в базе повреждено: working_days не JSON', 0, $exception);
        }

        if (!is_array($workingDays)) {
            throw new RuntimeException('Расписание в базе повреждено: working_days не массив');
        }

        $codes = [];
        foreach ($workingDays as $code) {
            if (!is_string($code)) {
                throw new RuntimeException('Расписание в базе повреждено: день недели не строка');
            }

            $codes[] = $code;
        }

        return Schedule::create($codes, (string) $row['day_start'], (string) $row['day_end']);
    }

    public function save(Schedule $schedule): void
    {
        $statement = $this->database->pdo()->prepare(
            'INSERT INTO schedule (id, working_days, day_start, day_end)
             VALUES (1, :workingDays, :dayStart, :dayEnd)
             ON CONFLICT(id) DO UPDATE SET working_days = excluded.working_days,
                                           day_start = excluded.day_start,
                                           day_end = excluded.day_end',
        );

        $statement->bindValue(':workingDays', self::encodeDays($schedule));
        $statement->bindValue(':dayStart', $schedule->dayStart);
        $statement->bindValue(':dayEnd', $schedule->dayEnd);
        $statement->execute();
    }

    private static function encodeDays(Schedule $schedule): string
    {
        try {
            return json_encode($schedule->workingDays, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Не удалось сериализовать расписание', 0, $exception);
        }
    }
}
