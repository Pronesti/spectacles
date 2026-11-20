<?php

namespace App\Http\Integrations\MusicBrainz\Requests;

use App\Http\Data\MusicBrainzQueryData;
use Saloon\Enums\Method;
use Saloon\Http\Request;

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
        return $this->parameters->toArray();
    }
}
