<?php

declare(strict_types=1);

namespace App;

use App\Handler\CreateBookingHandler;
use App\Handler\HandlerInterface;
use App\Handler\ListBookingsHandler;
use App\Handler\ListSlotsHandler;
use App\Http\Json;
use League\OpenAPIValidation\PSR7\Exception\NoOperation;
use League\OpenAPIValidation\PSR7\Exception\NoResponseCode;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\Route\Http\Exception\MethodNotAllowedException;
use League\Route\Http\Exception\NotFoundException;
use League\Route\Router;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Ядро приложения: схема SQLite на старте, роутинг по generated/routes.php,
 * валидация запросов и ответов против openapi.json, ошибки в виде problem+json.
 */
final class Kernel
{
    /** Базовый путь API из блока servers контракта. */
    public const BASE_PATH = '/api';

    private const SPEC_PATH = __DIR__ . '/../openapi.json';

    private readonly EventTypeRepository $eventTypes;
    private readonly BookingRepository $bookings;
    private readonly Clock $clock;
    private readonly string $timezone;
    private readonly Router $router;
    private readonly ServerRequestValidator $requestValidator;
    private readonly ResponseValidator $responseValidator;

    public function __construct(Config $config, Clock $clock)
    {
        $database = new Database($config->databasePath);
        $database->migrate();
        $this->eventTypes = new EventTypeRepository($database, $clock);
        $this->bookings = new BookingRepository($database, $clock);
        $this->clock = $clock;
        $this->timezone = $config->timezone;

        $builder = (new ValidatorBuilder())->fromJsonFile(self::SPEC_PATH);
        $this->requestValidator = $builder->getServerRequestValidator();
        // Схема парсится один раз и переиспользуется для проверки ответов.
        $this->responseValidator = new ResponseValidator($this->requestValidator->getSchema());

        $this->router = $this->buildRouter();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            try {
                $operation = $this->requestValidator->validate($request);
            } catch (NoOperation) {
                return Json::problem(
                    404,
                    'Маршрут не найден',
                    sprintf(
                        '%s %s не описан в контракте',
                        $request->getMethod(),
                        $request->getUri()->getPath(),
                    ),
                );
            } catch (ValidationFailed $exception) {
                return Json::problem(400, 'Ошибка валидации запроса', self::detail($exception));
            }

            return $this->dispatch($operation, $request);
        } catch (Throwable $exception) {
            return Json::problem(500, 'Внутренняя ошибка сервера', $exception->getMessage());
        }
    }

    private function dispatch(OperationAddress $operation, ServerRequestInterface $request): ResponseInterface
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (NotFoundException | MethodNotAllowedException) {
            // Запрос прошёл валидацию, значит операция описана в контракте, но обработчик
            // не подключён: путь может быть закреплён за другим методом — это не 405.
            return Json::problem(
                501,
                'Операция не реализована',
                sprintf('%s %s ещё не реализован', strtoupper($operation->method()), $operation->path()),
            );
        }

        try {
            $this->responseValidator->validate($operation, $response);
        } catch (NoResponseCode) {
            // Контракт не описывает такой статус для операции — проверять нечего.
        } catch (ValidationFailed $exception) {
            return Json::problem(500, 'Ответ не соответствует контракту', self::detail($exception));
        }

        return $response;
    }

    private function buildRouter(): Router
    {
        /** @var list<array{method: string, path: string, operationId: string, handler: string}> $routes */
        $routes = require dirname(__DIR__) . '/generated/routes.php';

        $router = new Router();

        foreach ($routes as $route) {
            $handlerClass = $route['handler'];

            if (!class_exists($handlerClass)) {
                // Операция есть в контракте, обработчик ещё не написан: отдаётся как 501.
                continue;
            }

            if (!is_subclass_of($handlerClass, HandlerInterface::class)) {
                throw new LogicException(sprintf(
                    'Обработчик %s должен реализовывать %s',
                    $handlerClass,
                    HandlerInterface::class,
                ));
            }

            $router->map($route['method'], self::BASE_PATH . $route['path'], $this->makeHandler($handlerClass));
        }

        return $router;
    }

    /**
     * Сборка обработчика с зависимостями, которые ему нужны.
     *
     * @param class-string<HandlerInterface> $handlerClass
     */
    private function makeHandler(string $handlerClass): HandlerInterface
    {
        return match ($handlerClass) {
            ListSlotsHandler::class => new ListSlotsHandler(
                $this->eventTypes,
                $this->bookings,
                $this->clock,
                $this->timezone,
            ),
            CreateBookingHandler::class => new CreateBookingHandler(
                $this->eventTypes,
                $this->bookings,
                $this->clock,
                $this->timezone,
            ),
            ListBookingsHandler::class => new ListBookingsHandler(
                $this->bookings,
                $this->clock,
                $this->timezone,
            ),
            default => new $handlerClass($this->eventTypes),
        };
    }

    /**
     * Текст ошибки валидации: сообщение библиотеки плюс причину из исходного исключения схемы.
     */
    private static function detail(Throwable $exception): string
    {
        $detail = $exception->getMessage();
        $previous = $exception->getPrevious();

        if ($previous !== null && $previous->getMessage() !== '') {
            $detail .= ': ' . $previous->getMessage();
        }

        return $detail;
    }
}
