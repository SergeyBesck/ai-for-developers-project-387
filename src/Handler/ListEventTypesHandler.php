<?php

declare(strict_types=1);

namespace App\Handler;

use App\EventTypeRepository;
use App\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * listEventTypes: GET /event-types — список типов событий.
 */
final class ListEventTypesHandler implements HandlerInterface
{
    public function __construct(private readonly EventTypeRepository $eventTypes)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        return Json::success(200, $this->eventTypes->all());
    }
}
