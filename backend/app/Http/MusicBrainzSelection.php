<?php

namespace App\Http;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MusicBrainzSelection
{
    public function issue(string $kind, string $mbid): string
    {
        return Crypt::encryptString(json_encode(['v' => 1, 'kind' => $kind, 'mbid' => $mbid, 'expiresAt' => now()->addDay()->timestamp], JSON_THROW_ON_ERROR));
    }

    public function read(string $proof, string $kind, string $path): string
    {
        try {
            $value = json_decode(Crypt::decryptString($proof), true, 16, JSON_THROW_ON_ERROR);
            if (is_array($value) && ($value['v'] ?? null) === 1 && in_array($kind, ['artist', 'work', 'series'], true) && ($value['kind'] ?? null) === $kind && is_string($value['mbid'] ?? null) && Str::isUuid($value['mbid']) && is_int($value['expiresAt'] ?? null) && $value['expiresAt'] > now()->timestamp) {
                return strtolower($value['mbid']);
            }
        } catch (DecryptException|\JsonException $failure) {
        }
        throw ValidationException::withMessages([$path => 'Select this MusicBrainz result again or use manual creation.']);
    }
}
