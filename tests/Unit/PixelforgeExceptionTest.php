<?php

namespace Tests\Unit;

use App\Services\Pixelforge\PixelforgeException;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use Tests\TestCase;

class PixelforgeExceptionTest extends TestCase
{
    public function test_transient_errors_are_retryable_and_respect_retry_after(): void
    {
        $exception = PixelforgeException::fromResponse($this->response([
            'error' => [
                'type' => 'rate_limit_error',
                'code' => 'concurrency_limit_exceeded',
                'message' => 'Too many generations queued or processing.',
            ],
        ], 429, ['Retry-After' => '5']));

        $this->assertTrue($exception->retryable);
        $this->assertSame(5, $exception->retryAfter);
        $this->assertSame('concurrency_limit_exceeded', $exception->errorCode);
    }

    public function test_daily_limit_is_not_retried_today(): void
    {
        $exception = PixelforgeException::fromResponse($this->response([
            'error' => ['type' => 'rate_limit_error', 'code' => 'daily_limit_exceeded', 'message' => 'Daily quota used.'],
        ], 429));

        $this->assertFalse($exception->retryable);
    }

    public function test_content_policy_and_auth_errors_are_not_retryable(): void
    {
        $content = PixelforgeException::fromResponse($this->response([
            'error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'Rejected.'],
        ], 400));

        $auth = PixelforgeException::fromResponse($this->response([
            'error' => ['type' => 'authentication_error', 'code' => 'invalid_api_key', 'message' => 'Bad key.'],
        ], 401));

        $this->assertFalse($content->retryable);
        $this->assertFalse($auth->retryable);
        $this->assertStringContainsString('content policy', $content->userMessage());
    }

    public function test_overloads_and_server_errors_are_retryable(): void
    {
        $overloaded = PixelforgeException::fromResponse($this->response([
            'error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.'],
        ], 503, ['Retry-After' => '2']));

        $server = PixelforgeException::fromResponse($this->response('', 500));

        $this->assertTrue($overloaded->retryable);
        $this->assertSame(2, $overloaded->retryAfter);
        $this->assertTrue($server->retryable);
    }

    /**
     * @param  array<string, mixed>|string  $body
     * @param  array<string, string>  $headers
     */
    private function response(array|string $body, int $status, array $headers = []): Response
    {
        return new Response(new Psr7Response(
            $status,
            $headers,
            is_string($body) ? $body : json_encode($body),
        ));
    }
}
