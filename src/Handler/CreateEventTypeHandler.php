<?php

declare(strict_types=1);

namespace App\Handler;

use App\DuplicateEventTypeException;
use App\EventTypeRepository;
use App\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * createEventType: POST /event-types — 201 с ресурсом, 409 при дубликате id-слага.
 */
final class CreateEventTypeHandler implements HandlerInterface
{
    public function __construct(private readonly EventTypeRepository $eventTypes)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);

        if (!is_array($body)) {
            return Json::problem(400, 'Ошибка валидации запроса', 'Тело запроса должно быть JSON-объектом');
        }

        $id = trim((string) ($body['id'] ?? ''));
        $name = trim((string) ($body['name'] ?? ''));
        $durationMinutes = $body['durationMinutes'] ?? null;
        $description = $body['description'] ?? null;

        if ($name === '') {
            return Json::problem(400, 'Ошибка валидации запроса', 'Поле name обязательно и не может быть пустым');
        }

        if (!is_int($durationMinutes)) {
            return Json::problem(400, 'Ошибка валидации запроса', 'Поле durationMinutes должно быть числом минут');
        }

        try {
            $eventType = $this->eventTypes->create(
                $id,
                $name,
                is_string($description) ? $description : null,
                $durationMinutes,
            );
        } catch (DuplicateEventTypeException $exception) {
            return Json::problem(409, 'Конфликт', $exception->getMessage());
        }

        return Json::success(201, $eventType);
    }
}
