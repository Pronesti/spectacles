<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request as RecordedRequest;
use Saloon\Http\Faking\MockResponse;

/**
 * Answers every request with the next pushed response, failing once exhausted.
 */
class ResponseSequence
{
    /** @var list<MockResponse> */
    private array $responses = [];

    /**
     * @param  array<mixed>|string|null  $body
     * @param  array<string, string>  $headers
     */
    public function push(array|string|null $body = null, int $status = 200, array $headers = []): self
    {
        $this->responses[] = ProviderHttp::response($body, $status, $headers);

        return $this;
    }

    public function __invoke(RecordedRequest $request): MockResponse
    {
        if ($this->responses === []) {
            throw new \OutOfBoundsException('A request was made, but the response sequence is empty.');
        }

        return array_shift($this->responses);
    }
}
