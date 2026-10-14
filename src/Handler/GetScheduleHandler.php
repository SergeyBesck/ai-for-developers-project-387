<?php

declare(strict_types=1);

namespace App\Handler;

use App\Http\Json;
use App\ScheduleRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * getSchedule: GET /schedule — текущее расписание приёма записей.
 *
 * Пока Хост его не менял, отдаётся расписание по умолчанию (пн–пт, 10:00–18:00).
 */
final class GetScheduleHandler implements HandlerInterface
{
    public function __construct(private readonly ScheduleRepository $schedules)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return Json::success(200, $this->schedules->get()->toApi());
    }
}
