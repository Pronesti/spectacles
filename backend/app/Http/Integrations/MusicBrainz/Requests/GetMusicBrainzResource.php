<?php

namespace App\Http\Integrations\MusicBrainz\Requests;

use App\Http\Data\MusicBrainzQueryData;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Spatie\LaravelData\Optional;

class GetMusicBrainzResource extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $path, protected MusicBrainzQueryData $parameters) {}

    public function resolveEndpoint(): string
    {
        return '/'.$this->path;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        $parameters = $this->parameters;

        return array_filter(
            ['query' => $parameters->query, 'inc' => $parameters->inc, 'fmt' => $parameters->fmt, 'limit' => $parameters->limit],
            fn (Optional|int|string $value): bool => ! $value instanceof Optional,
        );
    }
}
