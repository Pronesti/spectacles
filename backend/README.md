See [the repository README](../README.md) for setup, verification, and Laravel Cloud deployment.

## Reviewed catalog provider assistance

Authenticated provider reads help fill editable concert forms. They never create catalog rows. Final Attendance saving validates signed selections and resolves Artist, Tour, Venue and performance references atomically, without provider HTTP calls. A setlist.fm performance identifies one Event; existing shared records remain authoritative. Search results and HTML are not persisted as catalog data. A conflict requires reviewing the saved Event or explicitly detaching the performance for manual creation.

MusicBrainz continues to supply Artist/Work suggestions and adds Place details (full address, aliases, precise Venue coordinates and explicit Venue cross-links). Its configured identifying User-Agent and shared 1,100 ms start-spacing gate cover Place reads too. MusicBrainz Series selections from older drafts remain saveable; new external Tour choices come from setlist.fm's website IDs. Tour references use `external_provider_id`; other provider-reference tables retain `provider_resource_id`.

setlist.fm Artist, Tour, performance and Venue reads use bounded public HTML without an API key. Artist resolution starts from an explicit MusicBrainz `setlistfm` URL relationship and independently checks the website canonical ID and MBID; a missing or conflicting relationship leaves manual entry available. MusicBrainz remains API based. Artist-scoped performances use the verified canonical Artist listing and its Tour, country, Venue and year form filters. Venue-only history uses a validated canonical Venue URL; lookup never constructs a slug from the ID. Exact URLs travel through normalized suggestions/proofs and creation recovery, then persist as `venue_provider_ids.source_url` on confirmed Save. Older saved references without a URL require a fresh provider selection. Upcoming show-page IDs are never performance identities: a selected `setlist` query parameter supplies the performance ID. Provider data does not supply source songs, inferred local times, or city-centre map coordinates. HTML templates and source availability may change; failures retain manual entry.

The defaults deliberately leave new sources unavailable. Before operator activation, configure quotas and obtain applicable permission for website retrieval and retained reviewed prefills/references/local recovery. Form prefilling is not a licensing exemption.

| Setting | Default | Meaning |
| --- | --- | --- |
| `SETLISTFM_ENABLED` | `false` | Opt in to setlist.fm reads after permission/configuration. |
| `SETLISTFM_USER_AGENT` | empty | Identifying application/contact User-Agent; falls back to configured MusicBrainz identification. |
| `SETLISTFM_HTML_INTERVAL_MS` | `2000` | Independent website request-start spacing. |
| `SETLISTFM_HTML_DAILY_LIMIT` | `0` | Configured website daily quota; zero is unavailable. |
| `SETLISTFM_CACHE_SECONDS` | `0` | Normalized public-data cache lifetime, capped at 900 seconds; use only a duration permitted by the agreement. |
| `VENUE_IMAGES_ENABLED` | `false` | Optional licensed Commons metadata discovery via explicit Place relationships. |
| `VENUE_IMAGES_WIKIDATA_INTERVAL_MS` | `1000` | Shared Wikidata request-start spacing. |
| `VENUE_IMAGES_COMMONS_INTERVAL_MS` | `1000` | Independent Commons request-start spacing. |
| `VENUE_IMAGES_WIKIDATA_DAILY_LIMIT` | `0` | Configured metadata quota; zero is unavailable. |
| `VENUE_IMAGES_COMMONS_DAILY_LIMIT` | `0` | Configured metadata quota; zero is unavailable. |
| `VENUE_IMAGES_CACHE_SECONDS` | `0` | Normalized image metadata cache lifetime, capped at 900 seconds. |

Use a shared production cache store for pacing/quota enforcement. Website reads share one pacing/quota gate. Chained reads wait at most two seconds for configured spacing; longer waits and quota exhaustion return 429. Reads are bounded, do not automatically retry, return recoverable 429/503 responses, and issue fresh 24-hour public-identity proofs after cache lookup. Browsing is capped at ten upstream pages/100 scanned candidates and at most ten visible suggestions per request; `partial` means the displayed data is not a complete catalog. Exact cursors preserve unconsumed candidates. Text/date filters run over bounded source candidates, so all website result pages are marked partial. Upcoming widgets are not paginated through unrelated historical pages; legacy upcoming links without explicit performance identity are omitted. Venue-only Tour/country filter combinations require an identified Artist and return 422 otherwise. Unsupported templates or access challenges remain recoverable errors.

Optional image metadata retains its Commons source page, creator credit, license name and license URL. Only supported Wikimedia image hosts are accepted. Missing rights metadata or failed metadata reads leave a normal no-image Venue; no image bytes are downloaded or rehosted.

The test-only `tests/Fixtures/catalog-provider-http.php` router requires `APP_ENV=testing`, SQLite and a task-owned `/qa-output/catalog-providers-.../` database. It fakes all provider HTTP and blocks unmatched calls. It is never registered by application startup. Its local `fixture-state.json` supports deterministic provider/save/uncertain-response/expiry acceptance faults. See the dated verification report for actual checks and remaining environment limitations.
