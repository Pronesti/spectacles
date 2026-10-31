<?php

namespace Tests\Feature;

use App\Http\CatalogProviderUnavailable;
use App\Http\SetlistFmHtml;
use App\Http\SetlistFmUrl;
use Tests\TestCase;

class SetlistFmHtmlTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/Fixtures/setlistfm/'.$name.'.html'));
    }

    public function test_captured_artist_html_proves_mbid_canonical_identity_and_tour_ids(): void
    {
        $parser = app(SetlistFmHtml::class);
        $url = 'https://www.setlist.fm/setlists/radiohead-bd6bd12.html';
        $artist = $parser->artist($this->fixture('real-artist'), $url, 'a74b1b7f-71a5-4011-9441-d0b5e4122711');
        $this->assertSame('Radiohead', $artist['values']['name']);
        $tours = $parser->tours($this->fixture('real-artist'), 'bd6bd12');
        $this->assertContains('2bdd20ea', array_column($tours, 'id'));
        $this->assertNotContains('No Tour', array_column($tours, 'name'));
        $search = $parser->search($this->fixture('real-artist'), $url);
        $this->assertSame('34cc517', $search['items'][0]['ref']['externalId']);
        $this->assertSame('bd6bd12', $search['items'][0]['artistId']);
        $upcoming = $parser->search($this->fixture('real-artist'), $url, true);
        $this->assertSame('437ed7ef', $upcoming['items'][0]['ref']['externalId']);
        $this->assertSame('bd6bd12', $upcoming['items'][0]['artistId']);
    }

    public function test_ambiguous_listing_cards_do_not_hide_verified_performances(): void
    {
        $html = $this->fixture('real-artist');
        $artist = '<a href="../setlists/radiohead-bd6bd12.html" title="View Radiohead setlists">Radiohead</a>';
        $html = preg_replace('~'.preg_quote($artist, '~').'~', 'Radiohead', $html, 1);
        $rows = app(SetlistFmHtml::class)->search($html, 'https://www.setlist.fm/setlists/radiohead-bd6bd12.html');
        $this->assertNotContains('34cc517', array_column(array_column($rows['items'], 'ref'), 'externalId'));
        $this->assertNotEmpty($rows['items']);
        $this->assertSame('bd6bd12', $rows['items'][0]['artistId']);
    }

    public function test_captured_venue_listing_retains_exact_venue_scope_for_past_and_upcoming(): void
    {
        $url = 'https://www.setlist.fm/venue/allstate-arena-rosemont-il-usa-73d6ca11.html';
        $rows = app(SetlistFmHtml::class)->search($this->fixture('real-venue-listing'), $url, true);
        $this->assertGreaterThan(10, count($rows['items']));
        foreach ($rows['items'] as $row) {
            $this->assertSame('73d6ca11', $row['venueId']);
            $this->assertSame($url, $row['venueUrl']);
        }
    }

    public function test_same_named_artist_with_different_mbid_is_rejected(): void
    {
        $this->expectException(CatalogProviderUnavailable::class);
        app(SetlistFmHtml::class)->artist($this->fixture('real-artist'), 'https://www.setlist.fm/setlists/radiohead-bd6bd12.html', '55441123-89a2-49ab-ae3f-df7a3e86835a');
    }

    public function test_captured_performance_and_venue_html_are_scoped_and_canonical(): void
    {
        $parser = app(SetlistFmHtml::class);
        $url = 'https://www.setlist.fm/setlist/joker-out/2025/malo-rimsko-kazaliste-pula-croatia-53586b85.html';
        $artistUrl = $parser->performanceArtistUrl($this->fixture('real-performance'), $url);
        $this->assertSame('https://www.setlist.fm/setlists/joker-out-73ff0659.html', $artistUrl);
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $artist = [...$f['artist'], 'url' => $artistUrl];
        $value = $parser->performance($this->fixture('real-performance'), $url, '53586b85', $artist);
        $this->assertSame('2bda3802', $value['tour']['ref']['externalId']);
        $this->assertSame(['year' => 2025, 'month' => 8, 'day' => 7], $value['date']);
        $venue = $parser->venue($this->fixture('real-venue'), '73d6ca11');
        $this->assertSame('Allstate Arena', $venue['values']['name']);
        $this->assertSame('US', $venue['values']['countryCode']);
        $this->assertContains('All State Arena', $venue['values']['aliases']);
    }

    public function test_tour_link_must_belong_to_the_primary_artist(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $this->expectException(CatalogProviderUnavailable::class);
        app(SetlistFmHtml::class)->performance(str_replace('artist=4bd7afb6', 'artist=another', $this->fixture('past-performance')), 'https://www.setlist.fm/setlist/mana/2014/allstate-arena-rosemont-il-6bc56e8a.html', '6bc56e8a', $f['artist']);
    }

    public function test_real_tour_ids_and_aggregate_exclusion(): void
    {
        $rows = app(SetlistFmHtml::class)->tours($this->fixture('artist-tours'), '4bd7afb6');
        $this->assertSame(['2bde703a', 'bdac5de'], array_column($rows, 'id'));
        $this->assertSame('Drama y luz', $rows[0]['name']);
        $this->assertSame($rows, app(SetlistFmHtml::class)->tours(str_replace('changing-generated-id', 'another-id', $this->fixture('artist-tours')), '4bd7afb6'));
    }

    public function test_relative_resource_links_and_website_page_query_resolve(): void
    {
        $urls = app(SetlistFmUrl::class);
        $base = 'https://www.setlist.fm/setlist/mana/2014/arena-6bc56e8a.html';
        $this->assertSame('https://www.setlist.fm/search?artist=4bd7afb6&tour=2bde703a', $urls->resolve('../../../search?artist=4bd7afb6&tour=2bde703a', $base));
        $this->assertSame('https://www.setlist.fm/search?page=2', $urls->resolve('?page=2', 'https://www.setlist.fm/search'));
        $this->assertSame('b4cc196', $urls->id($urls->resolve('?setlist=b4cc196', 'https://www.setlist.fm/show/2026-10-30/arena-2b893c8a.html'), 'setlist'));
    }

    public function test_upcoming_identity_is_not_the_show_id_and_sidebar_is_ignored(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $result = app(SetlistFmHtml::class)->performance($this->fixture('upcoming-performance'), $f['performance']['url'], 'b4cc196', $f['artist']);
        $this->assertSame('b4cc196', $result['ref']['externalId']);
        $this->assertSame('bdac5de', $result['tour']['ref']['externalId']);
        $this->assertNull($result['time']);
        $this->assertNull($result['venue']['values']['lat']);
        $this->assertArrayNotHasKey('sets', $result);
    }

    public function test_historical_fields_are_scoped_to_primary_performance(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $url = 'https://www.setlist.fm/setlist/mana/2014/allstate-arena-rosemont-il-6bc56e8a.html';
        $result = app(SetlistFmHtml::class)->performance($this->fixture('past-performance'), $url, '6bc56e8a', $f['artist']);
        $this->assertSame('2bde703a', $result['tour']['ref']['externalId']);
        $this->assertSame(['year' => 2014, 'month' => 2, 'day' => 14], $result['date']);
    }

    public function test_search_and_legitimate_empty_state_ignore_popular_setlists(): void
    {
        $parser = app(SetlistFmHtml::class);
        $rows = $parser->search($this->fixture('upcoming-search'), 'https://www.setlist.fm/search?artist=4bd7afb6&upcoming=true');
        $this->assertCount(1, $rows['items']);
        $this->assertSame('b4cc196', $rows['items'][0]['ref']['externalId']);
        $this->assertStringContainsString('page=2', $rows['nextUrl']);
        $this->assertSame(['items' => [], 'nextUrl' => null], $parser->search($this->fixture('empty'), 'https://www.setlist.fm/search'));
    }

    public function test_challenge_and_changed_templates_are_unavailable(): void
    {
        foreach ([$this->fixture('challenge'), '<div class="mainColumn">No expected structure</div>'] as $html) {
            try {
                app(SetlistFmHtml::class)->search($html, 'https://www.setlist.fm/search');
                $this->fail('Unexpected template treated as empty.');
            } catch (CatalogProviderUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_urls_without_performance_ids_or_with_credentials_are_rejected(): void
    {
        foreach (['https://www.setlist.fm/show/2026-10-30/arena-2b893c8a.html', 'https://name@www.setlist.fm/setlist/mana/2014/arena-6bc56e8a.html', 'https://evil.example/setlist/mana/2014/arena-6bc56e8a.html'] as $url) {
            try {
                app(SetlistFmUrl::class)->id($url, 'setlist');
                $this->fail('Invalid source URL accepted.');
            } catch (CatalogProviderUnavailable) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_conflicting_main_artist_identity_is_rejected(): void
    {
        $f = require base_path('tests/Fixtures/catalog-provider-inputs.php');
        $this->expectException(CatalogProviderUnavailable::class);
        app(SetlistFmHtml::class)->performance(str_replace('</h1>', '<a href="https://www.setlist.fm/setlists/mana-different.html">Maná</a></h1>', $this->fixture('upcoming-performance')), $f['performance']['url'], 'b4cc196', $f['artist']);
    }
}
