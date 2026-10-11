<?php

declare(strict_types=1);

namespace App\Http;

use JsonException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Ответы API: JSON-успешные и ошибки в формате RFC 9457 (application/problem+json).
 */
final class Json
{
    /** @var array<int, string> */
    private const PROBLEM_TYPES = [
        400 => 'bad-request',
        404 => 'not-found',
        405 => 'method-not-allowed',
        409 => 'conflict',
        422 => 'unprocessable-entity',
        500 => 'internal-server-error',
        501 => 'not-implemented',
    ];

    /**
     * @throws JsonException
     */
    public static function success(int $status, mixed $data): ResponseInterface
    {
        return self::response($status, $data, 'application/json; charset=utf-8');
    }

    /**
     * @throws JsonException
     */
    public static function problem(int $status, string $title, ?string $detail = null): ResponseInterface
    {
        $problem = [
            'type' => '/problems/' . (self::PROBLEM_TYPES[$status] ?? 'error'),
            'title' => $title,
            'status' => $status,
        ];

        if ($detail !== null) {
            $problem['detail'] = $detail;
        }

        return self::response($status, $problem, 'application/problem+json');
    }

    /**
     * @throws JsonException
     */
    private static function response(int $status, mixed $payload, string $contentType): ResponseInterface
    {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
        );

        return new Response($status, ['Content-Type' => $contentType], $body);
    }
}
