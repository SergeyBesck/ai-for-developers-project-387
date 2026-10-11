<?php

declare(strict_types=1);

// @generated из openapi.json скриптом scripts/generate-routes.mjs. Руками не править.

return [
    ['method' => 'GET', 'path' => '/bookings', 'operationId' => 'listBookings', 'handler' => 'App\Handler\ListBookingsHandler'],
    ['method' => 'POST', 'path' => '/bookings', 'operationId' => 'createBooking', 'handler' => 'App\Handler\CreateBookingHandler'],
    ['method' => 'GET', 'path' => '/event-types', 'operationId' => 'listEventTypes', 'handler' => 'App\Handler\ListEventTypesHandler'],
    ['method' => 'POST', 'path' => '/event-types', 'operationId' => 'createEventType', 'handler' => 'App\Handler\CreateEventTypeHandler'],
    ['method' => 'GET', 'path' => '/event-types/{id}', 'operationId' => 'getEventType', 'handler' => 'App\Handler\GetEventTypeHandler'],
    ['method' => 'GET', 'path' => '/event-types/{id}/slots', 'operationId' => 'listSlots', 'handler' => 'App\Handler\ListSlotsHandler'],
];
