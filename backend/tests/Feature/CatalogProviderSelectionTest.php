<?php

namespace Tests\Feature;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderSelection;
use App\Http\CatalogProviderUnavailable;
use App\Http\Data\ProviderEntityInputData;
use App\Http\Data\ProviderLocatorInputData;
use App\Http\Data\ProviderPerformanceInputData;
use App\Http\Integrations\UpstreamPayload;
use App\Http\MusicBrainzSelection;
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
        $proof = $service->issue('performance', ProviderPerformanceInputData::from($f['performance']));
        $value = $service->read($proof, 'performance', 'pendingCatalog.event.selection');
        $this->assertSame('b4cc196', $value->ref->externalId);
        $this->assertSame($f['performance']['date'], $value->date->toArray());
        $this->assertArrayNotHasKey('localEvent', $value->toArray());
        $this->assertArrayNotHasKey('selection', $value->artist->toArray());
        ProviderHttp::assertNothingSent();
        $this->travel(24)->hours();
        $this->expectException(ValidationException::class);
        $service->read($proof, 'performance', 'pendingCatalog.event.selection');
    }

    public function test_absent_and_null_tour_names_survive_issue_and_read(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $service = app(CatalogProviderSelection::class);
        $absent = $service->read($service->issue('performance', ProviderPerformanceInputData::from($f['performance'])), 'performance', 'selection');
        $named = $service->read($service->issue('performance', ProviderPerformanceInputData::from([...$f['performance'], 'tourName' => null])), 'performance', 'selection');
        $this->assertArrayNotHasKey('tourName', json_decode(Crypt::decryptString($service->issue('performance', $absent)), true)['payload']);
        $reissued = json_decode(Crypt::decryptString($service->issue('performance', $named)), true)['payload'];
        $this->assertArrayHasKey('tourName', $reissued);
        $this->assertNull($reissued['tourName']);
    }

    public function test_misshapen_proof_envelopes_are_field_errors(): void
    {
        $expiresAt = now()->addHour()->timestamp;
        $reads = [
            fn () => app(MusicBrainzSelection::class)->read(Crypt::encryptString(json_encode(['v' => 1, 'kind' => 'artist', 'mbid' => ['not' => 'a string'], 'expiresAt' => $expiresAt])), 'artist', 'selection'),
            fn () => app(CatalogProviderSelection::class)->read(Crypt::encryptString(json_encode(['v' => 2, 'kind' => 'venue', 'payload' => 'not an object', 'expiresAt' => $expiresAt])), 'venue', 'selection'),
            fn () => app(CatalogProviderSelection::class)->tour(Crypt::encryptString(json_encode(['v' => ['not' => 'a version'], 'kind' => 'tour'])), 'selection'),
        ];
        foreach ($reads as $read) {
            try {
                $read();
                $this->fail('Misshapen proof accepted.');
            } catch (ValidationException $error) {
                $this->assertSame(['selection'], array_keys($error->errors()));
            }
        }
    }

    public function test_corrupted_wrong_kind_and_oversized_proofs_are_field_specific(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $service = app(CatalogProviderSelection::class);
        foreach (['corrupt', str_repeat('a', 32769), $service->issue('locator', ProviderLocatorInputData::from(['ref' => $f['performance']['ref'], 'url' => $f['performance']['url'], 'period' => 'upcoming']))] as $proof) {
            try {
                $service->read($proof, 'performance', 'pendingCatalog.event.selection');
                $this->fail('Invalid proof accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('pendingCatalog.event.selection', $error->errors());
            }
        }
    }

    public function test_proofs_in_the_current_format_keep_their_stored_payload_and_read_back_unchanged(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $proofs = require base_path('tests/Fixtures/catalog-provider-proofs.php');
        $service = app(CatalogProviderSelection::class);
        $issued = [
            'performance' => [...$f['performance'], 'tourName' => 'Drama y luz'],
            'venue' => $f['venue'],
            'tour' => $f['tour'],
            'locator' => ['ref' => $f['performance']['ref'], 'url' => $f['performance']['url'], 'period' => 'past', 'source' => 'api', 'manual' => ['artist' => $f['artist'], 'venue' => $f['venue'], 'date' => $f['performance']['date'], 'tourName' => 'Drama y luz']],
        ];
        foreach ($proofs as $kind => $payload) {
            $stored = json_decode(Crypt::decryptString($service->issue($kind, match ($kind) {
                'performance' => ProviderPerformanceInputData::from($issued[$kind]),
                'locator' => ProviderLocatorInputData::from($issued[$kind]),
                default => ProviderEntityInputData::from($issued[$kind]),
            })), true);
            $this->assertSame($payload, $stored['payload'], $kind);
            $handMade = Crypt::encryptString(json_encode(['v' => 2, 'kind' => $kind, 'payload' => $payload, 'expiresAt' => now()->addHour()->timestamp]));
            $read = $service->read($handMade, $kind, 'selection');
            $this->assertSame($payload, $read->toArray(), $kind);
        }
    }

    public function test_legacy_series_survives_but_invalid_v2_is_not_downgraded(): void
    {
        $id = fake()->uuid();
        $service = app(CatalogProviderSelection::class);
        $this->assertSame($id, $service->tour(app(MusicBrainzSelection::class)->issue('series', $id), 'tour')->externalId);
        $this->expectException(ValidationException::class);
        $service->tour(Crypt::encryptString(json_encode(['v' => 2, 'kind' => 'series', 'mbid' => $id])), 'tour');
    }

    public function test_normalization_rejects_wrong_ids_dates_hosts_and_duplicate_refs(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $variants = [];
        $v = $f['performance'];
        $v['ref']['externalId'] = 'another';
        $variants[] = $v;
        $v = $f['performance'];
        $v['date']['day'] = 32;
        $variants[] = $v;
        $v = $f['performance'];
        $v['venue']['url'] = 'https://evil.example/venue/wd-73d6ca11.html';
        $variants[] = $v;
        $v = $f['performance'];
        $v['venue']['linkedRefs'] = [$v['venue']['ref']];
        $variants[] = $v;
        $v = $f['performance'];
        $v['venue']['values']['lat'] = 1;
        $variants[] = $v;
        $v = $f['performance'];
        $v['artist']['ref']['resourceType'] = 'place';
        $variants[] = $v;
        foreach ($variants as $value) {
            try {
                CatalogProviderData::performance(UpstreamPayload::parse(ProviderPerformanceInputData::class, $value, fn (): CatalogProviderUnavailable => new CatalogProviderUnavailable));
                $this->fail('Malformed group accepted.');
            } catch (CatalogProviderUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
