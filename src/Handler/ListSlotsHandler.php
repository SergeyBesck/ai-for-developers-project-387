<?php

declare(strict_types=1);

namespace App\Handler;

use App\BookingRepository;
use App\Clock;
use App\EventTypeRepository;
use App\Http\Json;
use App\SlotGrid;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * listSlots: GET /event-types/{id}/slots — сетка слотов на окно записи; 404 для неизвестного типа.
 *
 * Слоты и занятость считаются чистыми функциями App\SlotGrid; источник занятости — реальные
 * записи из хранилища (BookingRepository), копии логики нет.
 */
final class ListSlotsHandler implements HandlerInterface
{
    public function __construct(
        private readonly EventTypeRepository $eventTypes,
        private readonly BookingRepository $bookings,
        private readonly Clock $clock,
        private readonly string $timezone,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');
        $eventType = $this->eventTypes->find($id);

        if ($eventType === null) {
            return Json::problem(404, 'Тип события не найден', sprintf('Тип события с id "%s" не найден', $id));
        }

        $starts = SlotGrid::slotStarts($this->clock->now(), $this->timezone, $eventType['durationMinutes']);
        $slots = SlotGrid::availability($starts, $eventType['durationMinutes'], $this->bookings->allIntervals());

        return Json::success(200, [
            'timezone' => $this->timezone,
            'slots' => array_map(static fn (array $slot): array => [
                'start' => $slot['start']->format(DateTimeInterface::ATOM),
                'available' => $slot['available'],
            ], $slots),
        ]);
    }
}
