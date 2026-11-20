<?php

namespace App\Http\Integrations\MusicBrainz\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetMusicBrainzResource extends Request
{
    protected Method $method = Method::GET;

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(protected string $path, protected array $parameters = []) {}

    public function resolveEndpoint(): string
    {
        return '/'.$this->path;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return $this->parameters;
    }
}
