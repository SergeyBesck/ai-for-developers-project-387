<?php

declare(strict_types=1);

namespace App\Handler;

use App\EventTypeRepository;
use App\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * getEventType: GET /event-types/{id} — 200 или 404 для неизвестного id.
 */
final class GetEventTypeHandler implements HandlerInterface
{
    public function __construct(private readonly EventTypeRepository $eventTypes)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $id = (string) $request->getAttribute('id');
        $eventType = $this->eventTypes->find($id);

        if ($eventType === null) {
            return Json::problem(404, 'Тип события не найден', sprintf('Тип события с id "%s" не найден', $id));
        }

        return Json::success(200, $eventType);
    }
}
