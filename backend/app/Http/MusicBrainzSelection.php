<?php

namespace App\Http;

use App\Http\Data\MusicBrainzProofData;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MusicBrainzSelection
{
    public function issue(string $kind, string $mbid): string
    {
        return Crypt::encryptString(json_encode(new MusicBrainzProofData(v: 1, kind: $kind, mbid: $mbid, expiresAt: now()->addDay()->getTimestamp()), JSON_THROW_ON_ERROR));
    }

    public function read(string $proof, string $kind, string $path): string
    {
        try {
            $value = MusicBrainzProofData::from(json_decode(Crypt::decryptString($proof), true, 16, JSON_THROW_ON_ERROR));
            if ($value->v === 1 && in_array($kind, ['artist', 'work', 'series'], true) && $value->kind === $kind && is_string($value->mbid) && Str::isUuid($value->mbid) && is_int($value->expiresAt) && $value->expiresAt > now()->timestamp) {
                return strtolower($value->mbid);
            }
        } catch (\Throwable $failure) {
        }
        throw ValidationException::withMessages([$path => 'Select this MusicBrainz result again or use manual creation.']);
    }
}
