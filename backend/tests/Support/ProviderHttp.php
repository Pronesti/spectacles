<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request as RecordedRequest;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

/**
 * Fakes outbound provider requests through Saloon's global mock client.
 *
 * Stubs match the full request URL, query string included, with `*` wildcards.
 * Callable stubs and assertion callbacks receive the outgoing request as an
 * `Illuminate\Http\Client\Request`, so they can read `url()`, headers and query
 * parameters. The first stub that returns a response wins.
 */
class ProviderHttp
{
    /** @var list<callable(RecordedRequest): ?MockResponse> */
    private static array $stubs = [];

    /** @var list<array{0: RecordedRequest, 1: MockResponse}> */
    private static array $recorded = [];

    /**
     * @param  array<string, MockResponse|callable|array<mixed>|string>|callable|null  $stubs
     */
    public static function fake(array|callable|null $stubs = null): void
    {
        if ($stubs === null) {
            $stubs = fn (): MockResponse => self::response();
        }
        if (is_callable($stubs)) {
            self::$stubs[] = $stubs;
        } else {
            foreach ($stubs as $pattern => $response) {
                self::$stubs[] = fn (RecordedRequest $request): ?MockResponse => Str::is(Str::start($pattern, '*'), $request->url())
                    ? self::toResponse(is_callable($response) ? $response($request) : $response)
                    : null;
            }
        }
        MockClient::global(['*' => fn (PendingRequest $pending): MockResponse => self::resolve($pending)]);
    }

    public static function fakeSequence(): ResponseSequence
    {
        $sequence = new ResponseSequence;
        self::fake($sequence);

        return $sequence;
    }

    /**
     * @param  array<mixed>|string|null  $body
     * @param  array<string, string>  $headers
     */
    public static function response(array|string|null $body = null, int $status = 200, array $headers = []): MockResponse
    {
        return MockResponse::make($body ?? '', $status, is_array($body) ? ['Content-Type' => 'application/json', ...$headers] : $headers);
    }

    public static function reset(): void
    {
        self::$stubs = [];
        self::$recorded = [];
        MockClient::destroyGlobal();
    }

    /**
     * @return list<array{0: RecordedRequest, 1: MockResponse}>
     */
    public static function recorded(?callable $filter = null): array
    {
        return $filter === null ? self::$recorded : array_values(array_filter(self::$recorded, fn (array $pair): bool => (bool) $filter(...$pair)));
    }

    public static function assertSent(callable $filter): void
    {
        Assert::assertNotEmpty(self::recorded($filter), 'An expected request was not recorded.');
    }

    public static function assertNotSent(callable $filter): void
    {
        Assert::assertEmpty(self::recorded($filter), 'An unexpected request was recorded.');
    }

    public static function assertSentCount(int $count): void
    {
        Assert::assertCount($count, self::$recorded);
    }

    public static function assertNothingSent(): void
    {
        Assert::assertEmpty(self::$recorded, 'Requests were recorded unexpectedly.');
    }

    private static function resolve(PendingRequest $pending): MockResponse
    {
        $psr = $pending->createPsrRequest();
        parse_str($psr->getUri()->getQuery(), $query);
        $request = (new RecordedRequest($psr))->withData($query);
        foreach (self::$stubs as $stub) {
            $response = self::toResponse($stub($request));
            if ($response !== null) {
                self::$recorded[] = [$request, $response];

                return $response;
            }
        }

        throw new \RuntimeException('No fake provider response matches ['.$request->url().'].');
    }

    private static function toResponse(mixed $response): ?MockResponse
    {
        return match (true) {
            $response === null, $response instanceof MockResponse => $response,
            is_array($response), is_string($response) => self::response($response),
            default => throw new \InvalidArgumentException('Unsupported fake provider response.'),
        };
    }
}
