<?php

declare(strict_types=1);

namespace App\Handler;

use App\BookingRepository;
use App\Clock;
use App\EventTypeRepository;
use App\Http\Json;
use App\SlotConflictException;
use App\SlotGrid;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * createBooking: POST /bookings → 201 с ресурсом целиком; запись и её ячейки создаются
 * одной транзакцией. Неизвестный тип события → 404, время вне сетки/окна/рабочего дня
 * или в прошлом → 422, пересечение с другой записью (в том числе другого типа события)
 * → 409 problem+json с полным откатом.
 */
final class CreateBookingHandler implements HandlerInterface
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
        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body)) {
            return Json::problem(400, 'Ошибка валидации запроса', 'Тело запроса должно быть объектом JSON');
        }

        $eventTypeId = (string) ($body['eventTypeId'] ?? '');
        $eventType = $this->eventTypes->find($eventTypeId);

        if ($eventType === null) {
            return Json::problem(
                404,
                'Тип события не найден',
                sprintf('Тип события с id "%s" не существует', $eventTypeId),
            );
        }

        // Начало приходит со смещением; в поясе приложения оно и хранится, и отдаётся.
        $start = (new DateTimeImmutable((string) ($body['start'] ?? '')))
            ->setTimezone(new DateTimeZone($this->timezone));
        $name = (string) ($body['name'] ?? '');
        $email = (string) ($body['email'] ?? '');
        $note = isset($body['note']) && is_string($body['note']) ? $body['note'] : null;
        $id = self::uuid4();

        // Правила сетки и окна — те же чистые функции, что рисуют список слотов.
        $rejection = SlotGrid::rejectStart(
            $this->clock->now(),
            $this->timezone,
            $eventType['durationMinutes'],
            $start,
        );

        if ($rejection !== null) {
            return Json::problem(422, 'Недопустимое время записи', $rejection);
        }

        try {
            $this->bookings->create(
                $id,
                $eventTypeId,
                $start,
                $eventType['durationMinutes'],
                $name,
                $email,
                $note,
            );
        } catch (SlotConflictException $exception) {
            return Json::problem(409, 'Конфликт', $exception->getMessage());
        }

        return Json::success(201, [
            'id' => $id,
            'eventTypeId' => $eventTypeId,
            'eventTypeName' => $eventType['name'],
            'start' => $start->format(DateTimeInterface::ATOM),
            'durationMinutes' => $eventType['durationMinutes'],
            'name' => $name,
            'email' => $email,
            'note' => $note,
        ]);
    }

    /** UUID v4 (RFC 9562): 122 случайных бита плюс фиксированные версия и варианты. */
    private static function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
