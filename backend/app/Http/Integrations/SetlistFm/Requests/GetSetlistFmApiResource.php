<?php

namespace App\Http\Integrations\SetlistFm\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetSetlistFmApiResource extends Request
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
