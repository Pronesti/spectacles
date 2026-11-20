<?php

namespace App\Http;

use App\Http\Data\ManualPerformanceData;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderLocatorData;
use App\Http\Data\ProviderLocatorInputData;
use App\Http\Data\ProviderPerformanceData;
use App\Http\Data\ProviderPerformanceInputData;
use App\Http\Data\ProviderRefData;
use App\Http\Data\ProviderTourData;
use App\Http\Data\ProviderVenueData;
use App\Http\Integrations\UpstreamPayload;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Issues and reads the encrypted proofs that bind a provider selection to what the server offered.
 */
class CatalogProviderSelection
{
    /**
     * Signs a payload after checking it against the rules its kind allows.
     */
    public function issue(string $kind, Data $payload): string
    {
        $payload = $this->payload($kind, $payload);
        $proof = Crypt::encryptString(json_encode(['v' => 2, 'kind' => $kind, 'payload' => $payload->toArray(), 'expiresAt' => now()->addHours(24)->timestamp], JSON_THROW_ON_ERROR));
        if (strlen($proof) > 32768) {
            throw new CatalogProviderUnavailable;
        }

        return $proof;
    }

    public function read(string $proof, string $kind, string $path): ProviderPerformanceData|ProviderLocatorData|ProviderTourData|ProviderVenueData
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

    public function performance(string $proof, string $path): ProviderPerformanceData
    {
        return $this->read($proof, 'performance', $path);
    }

    public function locator(string $proof, string $path): ProviderLocatorData
    {
        return $this->read($proof, 'locator', $path);
    }

    public function venue(string $proof, string $path): ProviderVenueData
    {
        return $this->read($proof, 'venue', $path);
    }

    /**
     * Reads a Tour proof, including legacy MusicBrainz series proofs, and returns the Tour identity.
     */
    public function tour(string $proof, string $path): ProviderRefData
    {
        try {
            $value = json_decode(Crypt::decryptString($proof), true, 32, JSON_THROW_ON_ERROR);
        } catch (\Throwable $failure) {
            throw ValidationException::withMessages([$path => 'Select this Tour again or use manual creation.']);
        }
        if (($value['v'] ?? null) === 1) {
            return new ProviderRefData('musicbrainz', 'series', app(MusicBrainzSelection::class)->read($proof, 'series', $path));
        }

        return $this->read($proof, 'tour', $path)->ref;
    }

    /**
     * Normalizes an untrusted payload to the Data class its kind allows.
     *
     * A decrypted payload arrives as decoded JSON; one about to be signed arrives as Data
     * and is checked by the same rules.
     *
     * @param  Data|array<string, mixed>  $payload
     */
    private function payload(string $kind, Data|array $payload): ProviderPerformanceData|ProviderLocatorData|ProviderTourData|ProviderVenueData
    {
        $class = match ($kind) {
            'performance' => ProviderPerformanceInputData::class,
            'locator' => ProviderLocatorInputData::class,
            'tour', 'venue' => ProviderEntityInputData::class,
            default => throw new \InvalidArgumentException('Unsupported provider selection kind.'),
        };
        $input = $payload instanceof $class ? $payload : UpstreamPayload::parse($class, $payload, fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable);

        return match ($kind) {
            'performance' => CatalogProviderData::performance($input),
            'locator' => $this->locatorPayload($input),
            'tour' => CatalogProviderData::tour($input),
            'venue' => CatalogProviderData::venue($input),
        };
    }

    private function locatorPayload(ProviderLocatorInputData $payload): ProviderLocatorData
    {
        $ref = CatalogProviderData::ref($payload->ref);
        if ($ref->provider !== 'setlist_fm' || $ref->resourceType !== 'setlist' || app(SetlistFmUrl::class)->id($payload->url ?? '', 'setlist') !== $ref->externalId || ! in_array($payload->period, ['past', 'upcoming'], true)) {
            throw new CatalogProviderUnavailable;
        }
        if ($payload->source !== null && ! in_array($payload->source, ['api', 'html'], true)) {
            throw new CatalogProviderUnavailable;
        }
        $manual = new Optional;
        if ($payload->manual !== null) {
            $details = $payload->manual;
            $normalized = CatalogProviderData::performance(new ProviderPerformanceInputData(ref: $payload->ref, url: $payload->url, date: $details->date, artist: $details->artist, venue: $details->venue));
            $manual = new ManualPerformanceData($normalized->artist, $normalized->venue, $normalized->date, CatalogProviderData::text($details->tourName, 255, true), null, null);
        }

        return new ProviderLocatorData($ref, $payload->url, $payload->period, $payload->source ?? new Optional, $manual);
    }
}
