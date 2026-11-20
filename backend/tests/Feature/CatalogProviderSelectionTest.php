<?php

namespace Tests\Feature;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderSelection;
use App\Http\CatalogProviderUnavailable;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderLocatorInputData;
use App\Http\Data\ProviderPerformanceInputData;
use App\Http\MusicBrainzSelection;
use App\Http\UntrustedPayload;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Tests\Support\ProviderHttp;
use Tests\TestCase;

class CatalogProviderSelectionTest extends TestCase
{
    public function test_proof_binds_kind_identity_group_and_expiry(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $service = app(CatalogProviderSelection::class);
        $proof = $service->issue('performance', ProviderPerformanceInputData::from($this->arrayAt($f, 'performance')));
        $value = $service->read($proof, 'performance', 'pendingCatalog.event.selection');
        expect($value->ref->externalId)->toBe('b4cc196')
            ->and($value->date->toArray())->toBe($this->arrayAt($f, 'performance.date'))
            ->and($value->toArray())->not->toHaveKey('localEvent')
            ->and($value->artist->toArray())->not->toHaveKey('selection');
        ProviderHttp::assertNothingSent();
        $this->travel(24)->hours();
        expect(fn () => $service->read($proof, 'performance', 'pendingCatalog.event.selection'))->toThrow(ValidationException::class);
    }

    public function test_absent_and_null_tour_names_survive_issue_and_read(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $service = app(CatalogProviderSelection::class);
        $absent = $service->read($service->issue('performance', ProviderPerformanceInputData::from($this->arrayAt($f, 'performance'))), 'performance', 'selection');
        $named = $service->read($service->issue('performance', ProviderPerformanceInputData::from([...$this->arrayAt($f, 'performance'), 'tourName' => null])), 'performance', 'selection');
        expect($this->arrayAt(json_decode(Crypt::decryptString($service->issue('performance', $absent)), true), 'payload'))->not->toHaveKey('tourName');
        $reissued = $this->arrayAt(json_decode(Crypt::decryptString($service->issue('performance', $named)), true), 'payload');
        expect($reissued)->toHaveKey('tourName')
            ->and($reissued['tourName'])->toBeNull();
    }

    public function test_misshapen_proof_envelopes_are_field_errors(): void
    {
        $expiresAt = now()->addHour()->timestamp;
        $reads = [
            fn () => app(MusicBrainzSelection::class)->read(Crypt::encryptString(json_encode(['v' => 1, 'kind' => 'artist', 'mbid' => ['not' => 'a string'], 'expiresAt' => $expiresAt], JSON_THROW_ON_ERROR)), 'artist', 'selection'),
            fn () => app(CatalogProviderSelection::class)->read(Crypt::encryptString(json_encode(['v' => 2, 'kind' => 'venue', 'payload' => 'not an object', 'expiresAt' => $expiresAt], JSON_THROW_ON_ERROR)), 'venue', 'selection'),
            fn () => app(CatalogProviderSelection::class)->tour(Crypt::encryptString(json_encode(['v' => ['not' => 'a version'], 'kind' => 'tour'], JSON_THROW_ON_ERROR)), 'selection'),
        ];
        foreach ($reads as $read) {
            try {
                $read();
                $this->fail('Misshapen proof accepted.');
            } catch (ValidationException $error) {
                expect(array_keys($error->errors()))->toBe(['selection']);
            }
        }
    }

    public function test_corrupted_wrong_kind_and_oversized_proofs_are_field_specific(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $service = app(CatalogProviderSelection::class);
        foreach (['corrupt', str_repeat('a', 32769), $service->issue('locator', ProviderLocatorInputData::from(['ref' => $this->arrayAt($f, 'performance.ref'), 'url' => $this->stringAt($f, 'performance.url'), 'period' => 'upcoming']))] as $proof) {
            try {
                $service->read($proof, 'performance', 'pendingCatalog.event.selection');
                $this->fail('Invalid proof accepted.');
            } catch (ValidationException $error) {
                expect($error->errors())->toHaveKey('pendingCatalog.event.selection');
            }
        }
    }

    public function test_proofs_in_the_current_format_keep_their_stored_payload_and_read_back_unchanged(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $proofs = require base_path('tests/Fixtures/catalog-provider-proofs.php');
        $service = app(CatalogProviderSelection::class);
        $issued = [
            'performance' => [...$this->arrayAt($f, 'performance'), 'tourName' => 'Drama y luz'],
            'venue' => $this->arrayAt($f, 'venue'),
            'tour' => $this->arrayAt($f, 'tour'),
            'locator' => ['ref' => $this->arrayAt($f, 'performance.ref'), 'url' => $this->stringAt($f, 'performance.url'), 'period' => 'past', 'source' => 'api', 'manual' => ['artist' => $this->arrayAt($f, 'artist'), 'venue' => $this->arrayAt($f, 'venue'), 'date' => $this->arrayAt($f, 'performance.date'), 'tourName' => 'Drama y luz']],
        ];
        foreach ($this->arrayAt($proofs) as $kind => $payload) {
            expect($kind)->toBeString();
            $stored = json_decode(Crypt::decryptString($service->issue($kind, match ($kind) {
                'performance' => ProviderPerformanceInputData::from($issued[$kind]),
                'locator' => ProviderLocatorInputData::from($issued[$kind]),
                default => ProviderEntityInputData::from($issued[$kind]),
            })), true);
            expect($this->arrayAt($stored, 'payload'))->toBe($payload);
            $handMade = Crypt::encryptString(json_encode(['v' => 2, 'kind' => $kind, 'payload' => $payload, 'expiresAt' => now()->addHour()->timestamp], JSON_THROW_ON_ERROR));
            $read = $service->read($handMade, $kind, 'selection');
            expect($read->toArray())->toBe($payload);
        }
    }

    public function test_legacy_series_survives_but_invalid_v2_is_not_downgraded(): void
    {
        $id = fake()->uuid();
        $service = app(CatalogProviderSelection::class);
        expect($service->tour(app(MusicBrainzSelection::class)->issue('series', $id), 'tour')->externalId)->toBe($id)
            ->and(fn () => $service->tour(Crypt::encryptString(json_encode(['v' => 2, 'kind' => 'series', 'mbid' => $id], JSON_THROW_ON_ERROR)), 'tour'))->toThrow(ValidationException::class);
    }

    public function test_normalization_rejects_wrong_ids_dates_hosts_and_duplicate_refs(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $performance = $this->arrayAt($f, 'performance');
        $variants = [];
        $v = $performance;
        data_set($v, 'ref.externalId', 'another');
        $variants[] = $v;
        $v = $performance;
        data_set($v, 'date.day', 32);
        $variants[] = $v;
        $v = $performance;
        data_set($v, 'venue.url', 'https://evil.example/venue/wd-73d6ca11.html');
        $variants[] = $v;
        $v = $performance;
        data_set($v, 'venue.linkedRefs', [$this->arrayAt($v, 'venue.ref')]);
        $variants[] = $v;
        $v = $performance;
        data_set($v, 'venue.values.lat', 1);
        $variants[] = $v;
        $v = $performance;
        data_set($v, 'artist.ref.resourceType', 'place');
        $variants[] = $v;
        foreach ($variants as $value) {
            try {
                CatalogProviderData::performance(UntrustedPayload::parse(ProviderPerformanceInputData::class, $value, fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable));
                $this->fail('Malformed group accepted.');
            } catch (CatalogProviderUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
