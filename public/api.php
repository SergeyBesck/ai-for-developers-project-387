<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Config;
use App\Health;
use App\Kernel;
use App\SystemClock;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) && $path !== '' ? $path : '/';

// Точка проверки бэкенда живёт вне контракта: /api и /api.php отвечают health-статусом.
if ($path === '/api' || $path === '/api.php') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(Health::check(), JSON_UNESCAPED_UNICODE);

    return;
}

$config = Config::fromEnv();
$kernel = new Kernel($config, new SystemClock($config->timezone));

$factory = new Psr17Factory();
$creator = new ServerRequestCreator($factory, $factory, $factory, $factory);

$response = $kernel->handle($creator->fromGlobals());

http_response_code($response->getStatusCode());

foreach ($response->getHeaders() as $name => $values) {
    header($name . ': ' . implode(', ', $values), true);
}

echo (string) $response->getBody();
