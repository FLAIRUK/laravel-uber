<?php

namespace FLAIRUK\Uber\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * An Uber API request failed. Uber's APIs use a few error shapes:
 *
 *   {"code": "...", "message": "...", "metadata": {...}}            Direct, Eats, Business
 *   {"errors": [{"status": 409, "code": "...", "title": "..."}]}    Riders
 *   {"error": "invalid_client", "error_description": "..."}         OAuth
 *
 * All of them end up in $errorCode, $metadata and $errors.
 */
class UberException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $errors
     */
    public function __construct(
        string $message,
        public readonly ?Response $response = null,
        public readonly ?string $errorCode = null,
        public readonly array $metadata = [],
        public readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $response?->status() ?? 0, $previous);
    }

    public static function fromResponse(Response $response): static
    {
        $body = (array) $response->json();
        $errors = array_values(array_filter((array) ($body['errors'] ?? []), 'is_array'));

        $code = $body['code'] ?? $body['error'] ?? $errors[0]['code'] ?? null;
        $detail = $body['message'] ?? $body['error_description'] ?? $errors[0]['title'] ?? $errors[0]['message'] ?? null;

        if (! is_string($detail) || $detail === '') {
            $detail = $response->reason();
        }

        $message = "Uber API request failed ({$response->status()})";
        $message .= is_string($code) && $code !== '' ? ": {$code}: {$detail}" : ": {$detail}";

        return new static(
            $message,
            $response,
            is_string($code) ? $code : null,
            is_array($body['metadata'] ?? null) ? $body['metadata'] : [],
            $errors,
        );
    }
}
