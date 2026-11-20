See [the repository README](../README.md) for setup, verification, and Laravel Cloud deployment.

## Reviewed catalog provider assistance

Authenticated provider reads help fill editable concert forms. They never create catalog rows. Final Attendance saving validates signed selections and resolves Artist, Tour, Venue and performance references atomically, without provider HTTP calls. A setlist.fm performance identifies one Event; existing shared records remain authoritative. Search results are not persisted as catalog data. A conflict requires reviewing the saved Event or explicitly detaching the performance for manual creation.

MusicBrainz continues to supply Artist/Work suggestions and adds Place details (full address, aliases, precise Venue coordinates and explicit Venue cross-links). Its configured identifying User-Agent and shared 1,100 ms start-spacing gate cover Place reads too. MusicBrainz Series selections from older drafts remain saveable; setlist.fm supplies Tour names without stable IDs, so Tours chosen from setlist.fm are saved without an external Tour identity. Tour references use `external_provider_id`; other provider-reference tables retain `provider_resource_id`.

setlist.fm Tour, performance and Venue reads use only the official setlist.fm API and require `SETLIST_FM_API_KEY`; without a key, setlist.fm suggestions are unavailable and manual entry remains. The key is sent only in the `x-api-key` header. Artist scope comes from a MusicBrainz identity. Venue history accepts a validated canonical Venue URL, which travels through normalized suggestions/proofs and creation recovery, then persists as `venue_provider_ids.source_url` on confirmed Save. Provider data does not supply source songs, inferred local times, or city-centre map coordinates.

The defaults deliberately leave new sources unavailable. Before operator activation, configure quotas and obtain applicable permission for API use and retained reviewed prefills/references/local recovery. Form prefilling is not a licensing exemption.

| Setting | Default | Meaning |
| --- | --- | --- |
| `SETLISTFM_ENABLED` | `false` | Opt in to setlist.fm reads after permission/configuration. |
| `SETLISTFM_USER_AGENT` | empty | Identifying application/contact User-Agent; falls back to configured MusicBrainz identification. |
| `SETLIST_FM_API_KEY` | empty | setlist.fm API key; without it setlist.fm reads are unavailable. |
| `SETLISTFM_API_INTERVAL_MS` | `1000` | API request-start spacing (never below two requests per second). |
| `SETLISTFM_API_DAILY_LIMIT` | `100` | Configured API daily quota. |
| `SETLISTFM_CACHE_SECONDS` | `0` | Normalized public-data cache lifetime, capped at 900 seconds; use only a duration permitted by the agreement. |
| `VENUE_IMAGES_ENABLED` | `false` | Optional licensed Commons metadata discovery via explicit Place relationships. |
| `VENUE_IMAGES_WIKIDATA_INTERVAL_MS` | `1000` | Shared Wikidata request-start spacing. |
| `VENUE_IMAGES_COMMONS_INTERVAL_MS` | `1000` | Independent Commons request-start spacing. |
| `VENUE_IMAGES_WIKIDATA_DAILY_LIMIT` | `0` | Configured metadata quota; zero is unavailable. |
| `VENUE_IMAGES_COMMONS_DAILY_LIMIT` | `0` | Configured metadata quota; zero is unavailable. |
| `VENUE_IMAGES_CACHE_SECONDS` | `0` | Normalized image metadata cache lifetime, capped at 900 seconds. |

Use a shared production cache store for pacing/quota enforcement. Chained reads wait at most two seconds for configured spacing; longer waits and quota exhaustion return 429. Reads are bounded, do not automatically retry, return recoverable 429/503 responses, and issue fresh 24-hour public-identity proofs after cache lookup. Browsing is capped at ten upstream pages/100 scanned candidates and at most ten visible suggestions per request; `partial` means the displayed data is not a complete catalog. Exact cursors preserve unconsumed candidates. Text/date filters run over bounded source candidates, so result pages are marked partial. Website Tour IDs are not accepted as filters and return 422.

Optional image metadata retains its Commons source page, creator credit, license name and license URL. Only supported Wikimedia image hosts are accepted. Missing rights metadata or failed metadata reads leave a normal no-image Venue; no image bytes are downloaded or rehosted.

The test-only `tests/Fixtures/catalog-provider-http.php` router requires `APP_ENV=testing`, SQLite and a task-owned `/qa-output/catalog-providers-.../` database. It fakes all provider HTTP and blocks unmatched calls. It is never registered by application startup. Its local `fixture-state.json` supports deterministic provider/save/uncertain-response/expiry acceptance faults. See the dated verification report for actual checks and remaining environment limitations.
