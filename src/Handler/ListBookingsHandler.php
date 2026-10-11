<?php

declare(strict_types=1);

namespace App\Handler;

use App\BookingRepository;
use App\Clock;
use App\Http\Json;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * listBookings: GET /bookings → { timezone, bookings } — предстоящие записи всех типов
 * событий, отсортированы по start. «Сейчас» берётся из инжектируемых часов, пояс приложения —
 * из конфигурации. Схема записи совпадает с createBooking (включая eventTypeName).
 */
final class ListBookingsHandler implements HandlerInterface
{
    public function __construct(
        private readonly BookingRepository $bookings,
        private readonly Clock $clock,
        private readonly string $timezone,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $bookings = array_map(
            static fn (array $booking): array => [
                'id' => $booking['id'],
                'eventTypeId' => $booking['eventTypeId'],
                'eventTypeName' => $booking['eventTypeName'],
                'start' => $booking['start']->format(DateTimeInterface::ATOM),
                'durationMinutes' => $booking['durationMinutes'],
                'name' => $booking['name'],
                'email' => $booking['email'],
                'note' => $booking['note'],
            ],
            $this->bookings->upcoming($this->clock->now()),
        );

        return Json::success(200, [
            'timezone' => $this->timezone,
            'bookings' => $bookings,
        ]);
    }
}
