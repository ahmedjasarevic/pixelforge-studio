<?php

namespace App\Services\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class PixelforgeException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 0,
        public readonly ?int $retryAfter = null,
        public readonly bool $retryable = false,
        public readonly ?string $param = null,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $error = is_array($body) && is_array($body['error'] ?? null) ? $body['error'] : [];

        $code = (string) ($error['code'] ?? 'http_'.$response->status());
        $message = (string) ($error['message'] ?? 'Pixelforge returned HTTP '.$response->status().'.');
        $retryAfterHeader = $response->header('Retry-After');
        $retryAfter = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : null;

        $retryable = match ($code) {
            'rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded' => true,
            // Retrying today will not help; surface it to the user instead.
            'daily_limit_exceeded' => false,
            default => $response->serverError(),
        };

        return new self(
            errorCode: $code,
            message: $message,
            status: $response->status(),
            retryAfter: $retryAfter,
            retryable: $retryable,
            param: isset($error['param']) ? (string) $error['param'] : null,
            errors: is_array($error['errors'] ?? null) ? $error['errors'] : [],
        );
    }

    public static function connectionFailure(Throwable $exception): self
    {
        $message = $exception instanceof ConnectionException
            ? 'Could not reach Pixelforge. The request may or may not have been accepted; it will be retried safely with the same idempotency key.'
            : 'Unexpected error while talking to Pixelforge: '.$exception->getMessage();

        return new self(
            errorCode: 'connection_error',
            message: $message,
            retryable: true,
        );
    }

    /**
     * A message that is safe and useful to show in the user's UI.
     */
    public function userMessage(): string
    {
        return match ($this->errorCode) {
            'content_policy_violation' => 'Pixelforge rejected this prompt under its content policy. Nothing was created and nothing was charged.',
            'daily_limit_exceeded' => "Pixelforge's daily generation limit has been reached. Please try again after midnight UTC. Nothing was charged.",
            'overloaded' => 'Pixelforge is overloaded right now, so the request was not accepted. Please try again in a minute. Nothing was charged.',
            'concurrency_limit_exceeded' => 'You already have too many generations in progress (Pixelforge allows 3). Wait for one to finish and try again. Nothing was charged.',
            'rate_limit_exceeded' => 'Pixelforge is rate limiting requests right now. Please wait a moment and try again. Nothing was charged.',
            'invalid_api_key' => 'The Pixelforge API key is missing or invalid. Please contact the operator.',
            'validation_failed' => 'Pixelforge rejected the request: '.$this->getMessage(),
            'idempotency_key_reused' => 'Pixelforge rejected the request because its idempotency key was reused with different parameters. Nothing was charged.',
            default => 'Pixelforge could not accept this generation. Nothing was charged. You can try again.',
        };
    }
}
