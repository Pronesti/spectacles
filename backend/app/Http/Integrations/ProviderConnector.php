<?php

namespace App\Http\Integrations;

use GuzzleHttp\RequestOptions;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\HasTimeout;

/**
 * Shared transport rules for every public catalog provider: short timeouts,
 * no redirects, and streamed bodies that are read with a hard size cap.
 */
abstract class ProviderConnector extends Connector
{
    use HasTimeout;

    public const int MAX_BODY_BYTES = 2097152;

    protected int $connectTimeout = 2;

    protected int $requestTimeout = 5;

    public function __construct(protected string $userAgent) {}

    /**
     * @return array<string, string>
     */
    protected function defaultHeaders(): array
    {
        return ['User-Agent' => $this->userAgent];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultConfig(): array
    {
        return [RequestOptions::ALLOW_REDIRECTS => false, RequestOptions::STREAM => true];
    }

    /**
     * Whether the declared Content-Length already exceeds the body cap.
     */
    public static function declaresOversizedBody(Response $response): bool
    {
        $length = $response->header('Content-Length');

        return (int) (is_array($length) ? reset($length) : $length) > self::MAX_BODY_BYTES;
    }

    /**
     * Read the streamed body, returning null once it grows past the cap.
     */
    public static function boundedBody(Response $response): ?string
    {
        $stream = $response->stream();
        $body = '';
        while (! $stream->eof() && strlen($body) <= self::MAX_BODY_BYTES) {
            $body .= $stream->read(min(8192, self::MAX_BODY_BYTES + 1 - strlen($body)));
        }

        return strlen($body) > self::MAX_BODY_BYTES ? null : $body;
    }

    /**
     * Read the Retry-After header as whole seconds within the given bounds.
     */
    public static function retryAfter(Response $response, int $maximum): int
    {
        $value = $response->header('Retry-After');

        return max(1, min($maximum, (int) (is_array($value) ? reset($value) : $value) ?: 2));
    }
}
