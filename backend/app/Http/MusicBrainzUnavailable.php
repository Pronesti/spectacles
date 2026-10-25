<?php

namespace App\Http;

class MusicBrainzUnavailable extends \RuntimeException
{
    public function __construct(public readonly int $status = 503, public readonly int $retryAfter = 2)
    {
        parent::__construct('MusicBrainz suggestions are unavailable. Manual entry is still available.');
    }
}
