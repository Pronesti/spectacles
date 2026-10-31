<?php

namespace App\Http;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class CatalogProviderSelection
{
    public function issue(string $kind, array $payload): string
    {
        $proof = Crypt::encryptString(json_encode(['v' => 2, 'kind' => $kind, 'payload' => $this->payload($kind, $payload), 'expiresAt' => now()->addHours(24)->timestamp], JSON_THROW_ON_ERROR));
        if (strlen($proof) > 32768) {
            throw new CatalogProviderUnavailable;
        }

        return $proof;
    }

    public function read(string $proof, string $kind, string $path): array
    {
        try {
            if (strlen($proof) > 32768) {
                throw new CatalogProviderUnavailable;
            }
            $value = json_decode(Crypt::decryptString($proof), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($value) || ($value['v'] ?? null) !== 2 || ($value['kind'] ?? null) !== $kind || ! is_int($value['expiresAt'] ?? null) || $value['expiresAt'] <= now()->timestamp || ! is_array($value['payload'] ?? null)) {
                throw new CatalogProviderUnavailable;
            }

            return $this->payload($kind, $value['payload']);
        } catch (\Throwable $failure) {
            throw ValidationException::withMessages([$path => 'Select these provider details again or use manual creation.']);
        }
    }

    public function tour(string $proof, string $path): array
    {
        try {
            $value = json_decode(Crypt::decryptString($proof), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $failure) {
            throw ValidationException::withMessages([$path => 'Select this Tour again or use manual creation.']);
        }
        if (($value['v'] ?? null) === 1) {
            $id = app(MusicBrainzSelection::class)->read($proof, 'series', $path);

            return ['ref' => ['provider' => 'musicbrainz', 'resourceType' => 'series', 'externalId' => $id]];
        }

        return $this->read($proof, 'tour', $path);
    }

    private function payload(string $kind, array $payload): array
    {
        if ($kind === 'performance') {
            return CatalogProviderData::performance($payload);
        }
        if ($kind === 'locator') {
            $ref = CatalogProviderData::ref($payload['ref'] ?? []);
            if ($ref['provider'] !== 'setlist_fm' || $ref['resourceType'] !== 'setlist' || app(SetlistFmUrl::class)->id($payload['url'] ?? '', 'setlist') !== $ref['externalId'] || ! in_array($payload['period'] ?? null, ['past', 'upcoming'], true)) {
                throw new CatalogProviderUnavailable;
            }

            $result = ['ref' => $ref, 'url' => $payload['url'], 'period' => $payload['period']];
            if (isset($payload['manual'])) {
                $manual = $payload['manual'];
                $normalized = CatalogProviderData::performance(['ref' => $ref, 'url' => $payload['url'], 'artist' => $manual['artist'] ?? [], 'venue' => $manual['venue'] ?? [], 'date' => $manual['date'] ?? [], 'tour' => null, 'time' => null, 'timezone' => null]);
                $result['manual'] = ['artist' => $normalized['artist'], 'venue' => $normalized['venue'], 'date' => $normalized['date'], 'tourName' => CatalogProviderData::text($manual['tourName'] ?? null, 255, true), 'time' => null, 'timezone' => null];
            }

            return $result;
        }
        if (! in_array($kind, ['tour', 'venue'], true)) {
            throw new \InvalidArgumentException('Unsupported provider selection kind.');
        }
        $entity = CatalogProviderData::entity($payload);
        if (! in_array($entity['ref']['resourceType'], $kind === 'tour' ? ['tour', 'series'] : ['venue', 'place'], true)) {
            throw new CatalogProviderUnavailable;
        }

        return $entity;
    }
}
