<?php

declare(strict_types=1);

// Router для `php -S localhost:8000 -t public public/router.php`:
// запросы /api/* уходят в api.php, остальное отдаёт статик-сервер.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (is_string($path) && str_starts_with($path, '/api')) {
    require __DIR__ . '/api.php';

    return true;
}

return false;
