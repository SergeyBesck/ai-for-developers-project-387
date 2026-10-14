<?php

declare(strict_types=1);

namespace App\Handler;

use App\Http\Json;
use App\Schedule;
use App\ScheduleRepository;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * updateSchedule: PUT /schedule → 200 с расписанием целиком. Схема тела уже проверена
 * по контракту (дни mon..sun, HH:MM кратно 15 минутам); 422 — конец рабочего дня
 * не позже начала или нет рабочих дней. Уже созданные записи не изменяются.
 */
final class UpdateScheduleHandler implements HandlerInterface
{
    public function __construct(private readonly ScheduleRepository $schedules)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body)) {
            return Json::problem(400, 'Ошибка валидации запроса', 'Тело запроса должно быть объектом JSON');
        }

        $workingDays = [];
        $rawDays = $body['workingDays'] ?? [];

        if (is_array($rawDays)) {
            foreach ($rawDays as $code) {
                $workingDays[] = (string) $code;
            }
        }

        try {
            $schedule = Schedule::create(
                $workingDays,
                (string) ($body['dayStart'] ?? ''),
                (string) ($body['dayEnd'] ?? ''),
            );
        } catch (InvalidArgumentException $exception) {
            return Json::problem(422, 'Недопустимое расписание', $exception->getMessage());
        }

        $this->schedules->save($schedule);

        return Json::success(200, $schedule->toApi());
    }
}
