<?php

declare(strict_types=1);

namespace App\Http;

class CatalogProviderUnavailable extends \RuntimeException
{
    public function __construct(public int $status = 503, public int $retryAfter = 1, public ?string $reason = null)
    {
        parent::__construct('Provider suggestions are unavailable. Try again or enter the details manually.');
    }
}
