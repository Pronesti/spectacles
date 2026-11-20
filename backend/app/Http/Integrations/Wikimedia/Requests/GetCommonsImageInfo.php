<?php

declare(strict_types=1);

namespace App\Http\Integrations\Wikimedia\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCommonsImageInfo extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected string $file) {}

    public function resolveEndpoint(): string
    {
        return '/w/api.php';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultQuery(): array
    {
        return ['action' => 'query', 'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata', 'iiurlwidth' => 800, 'titles' => 'File:'.$this->file, 'format' => 'json'];
    }
}
