<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Обработчик операции контракта: связь с operationId — через generated/routes.php.
 */
interface HandlerInterface
{
    public function __invoke(ServerRequestInterface $request): ResponseInterface;
}
