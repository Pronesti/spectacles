<?php

namespace Tests\Feature;

use App\Http\CatalogProviderData;
use App\Http\CatalogProviderSelection;
use App\Http\CatalogProviderUnavailable;
use App\Http\MusicBrainzSelection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogProviderSelectionTest extends TestCase
{
    public function test_proof_binds_kind_identity_group_and_expiry(): void
    {
        Http::preventStrayRequests();
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $service = app(CatalogProviderSelection::class);
        $proof = $service->issue('performance', $f['performance']);
        $value = $service->read($proof, 'performance', 'pendingCatalog.event.selection');
        $this->assertSame('b4cc196', $value['ref']['externalId']);
        $this->assertSame($f['performance']['date'], $value['date']);
        $this->assertArrayNotHasKey('localEvent', $value);
        $this->assertArrayNotHasKey('selection', $value['artist']);
        Http::assertNothingSent();
        $this->travel(24)->hours();
        $this->expectException(ValidationException::class);
        $service->read($proof, 'performance', 'pendingCatalog.event.selection');
    }

    public function test_corrupted_wrong_kind_and_oversized_proofs_are_field_specific(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $service = app(CatalogProviderSelection::class);
        foreach (['corrupt', str_repeat('a', 32769), $service->issue('locator', ['ref' => $f['performance']['ref'], 'url' => $f['performance']['url'], 'period' => 'upcoming'])] as $proof) {
            try {
                $service->read($proof, 'performance', 'pendingCatalog.event.selection');
                $this->fail('Invalid proof accepted.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('pendingCatalog.event.selection', $error->errors());
            }
        }
    }

    public function test_legacy_series_survives_but_invalid_v2_is_not_downgraded(): void
    {
        $id = fake()->uuid();
        $service = app(CatalogProviderSelection::class);
        $this->assertSame($id, $service->tour(app(MusicBrainzSelection::class)->issue('series', $id), 'tour')['ref']['externalId']);
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
                CatalogProviderData::performance($value);
                $this->fail('Malformed group accepted.');
            } catch (CatalogProviderUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
