# MusicBrainz and setlist.fm catalog form assistance

Date: 2026-10-25

Status: Implemented locally on 2026-10-25 in the user-selected current checkout, with the requested GPT-6.1 Sol subagent. Includes the requested update and verification of local concert creation draft recovery. See `docs/superpowers/plans/2026-10-25-setlistfm-catalog-form-assistance.md` and the companion verification report for executed checks and remaining environment/manual proof. Changes are uncommitted; integrations remain disabled by default.

## Purpose and agreed scope

Help a user fill Spectacles' concert form using reviewed provider suggestions. MusicBrainz supplies Artist and Song identity and Venue metadata. setlist.fm complements Artists with performance discovery, replaces MusicBrainz Tour suggestions, and supplies historical and upcoming performance suggestions. Selecting a performance previews and applies its Artist, Tour, Venue and date as one group. The form remains manually usable when provider data is absent or unavailable.

Provider responses assist form entry. Search, preview and selection do not create catalog records. Final Save persists reviewed form values and selected provider identities through the existing Attendance transaction. There is no background ingestion, synchronization, provider change comparison or replacement of later manual edits.

Use API plus HTML: the setlist.fm API supplies historical performance data; a separate HTML adapter supplies Tour identifiers and upcoming performance data. MusicBrainz continues to use its API. Automatic Tour ID retrieval is required; pasting a Tour URL is not a prerequisite.

An Event selected from setlist.fm represents exactly one artist performance, identified by its setlist ID. Separate artists at the same physical concert produce separate source-linked Events. Do not group them by date, Venue, festival or show-page identifier. Manual Events can continue to have multiple Artists and support nonmusic event types.

Existing local Events remain authoritative. Selecting a matching local Event reuses its saved facts and relationships. Shared-field changes use the existing explicit **Create a new Event using these details** action, leaving the original Event unchanged.

Venue previews include available address, aliases, map location, attributed image and historical/upcoming performances. Existing local creation-draft recovery must be updated and verified for every new form state introduced here.

The Setlist step retains its existing local Song/MusicBrainz Work and manual editing behavior. Selecting a performance preserves existing setlist entries; this feature does not import the performance's song list. Personal notes, membership flags and seats retain their current ownership and behavior.

## Existing implementation and continuity

The inspected baseline is `f1e218e` on `feat/live-event-data-layer`, plus uncommitted local creation-draft recovery and related wizard changes. Preserve that work; it is an integration dependency for this feature, not code to replace from the older committed wizard.

- `frontend/src/components/ConcertWizard.tsx`, `concertWizard.ts` and `concertDraft.ts` own the four steps, pending catalog selections, Event adoption/detachment and final payload.
- `frontend/src/components/EntityAutocomplete.tsx`, `autocomplete.ts`, `catalogApi.ts` and `musicbrainzApi.ts` supply local/provider reads, cancellation and identity selection. Current provider fallback runs only after a successful empty local result.
- `frontend/src/components/WizardEventStep.tsx` owns local Event selection and Venue fields. There is no standalone Venue catalog page in the inspected frontend. Extend this step with the agreed Venue preview.
- `frontend/src/concertRecovery.ts` and `components/ConcertRecovery.tsx` own account-scoped local snapshots, strict decoding, editor/query recovery, synchronous writes, clear-on-success and explicit discard.
- `backend/app/Http/MusicBrainzClient.php`, `MusicBrainzSelection.php`, `CatalogDraftResolver.php`, request validation and `CollectionController.php` supply authenticated provider reads, signed/encrypted public-identity proofs and transactional catalog resolution.
- Provider-reference tables already separate Spectacles UUIDs from Artist, Tour and Venue provider IDs and Event source references. The existing partial unique index permits a setlist.fm performance to identify only one Event.

This specification supersedes the earlier MusicBrainz-only release restrictions for the provider/form features named here. It also supersedes the earlier domain specification's grouping of multiple source performances into one imported Event. Other domain, privacy, localization, collaboration and wizard requirements continue to apply. Plan and implement on the existing feature branch with its same-release dependencies; do not merge or rebase `dev` into it.

## Provider boundaries

Keep provider HTTP access in the backend, behind authenticated, throttled reads. Use focused clients and normalizers within the current application structure. Share a small normalized suggestion contract across frontend consumers without building a general plugin framework. Stored catalog reads remain JSON:API resources; external suggestions remain transient DTOs.

### MusicBrainz

Retain Artist and Work searches, selection validation and the shared outbound pacing gate. Add Place search/detail reads for names, aliases, full address, precise coordinates and relevant URL relationships. Place and Artist MBIDs are independent resource kinds and must not be interchangeable in save proofs.

Keep the identifying configured User-Agent and application-wide minimum 1,100 ms request-start spacing. Extend the existing gate across the new Place requests. Return only normalized fields used by the form and provider matching. Work writer/cover credits never establish performance Artists.

### setlist.fm API

Use the configured server-side API key for historical search/detail. Filter by known Artist MBID, optional Tour name, date/year, country and Venue ID as appropriate. The website Artist ID is distinct from its MusicBrainz MBID; keep both kinds explicit. Extract a website Artist ID from a validated setlist.fm artist URL where available, rather than sending it as an API MBID.

The documented API Tour object supplies a name. Resolve its actual website Tour ID through HTML. When filtering API performances by a selected Tour name, verify each offered result against the selected website Tour ID; a name filter alone cannot prove membership in the selected Tour. Search suggestions can show a pending enrichment state, but cannot issue a confirmed setlist.fm Tour identity until the real ID is known. Do not hash a name or substitute a performance ID. If enrichment fails, preserve the name for explicit manual creation without a provider identity.

Tours remain optional. Discover them within the selected Artists' scope, deduplicate by provider identity and preserve same-name, different-ID choices. Retain local Tour selection and manual creation. MusicBrainz Series ceases to supply new Tour suggestions; existing linked MusicBrainz Tours and valid recovery selections remain readable and saveable.

### setlist.fm HTML

Fetch artist-scoped search pages for Tour choices and performance pages for exact Tour associations. Parse semantic links and select names/option values; generated element IDs are not a contract. Exclude the All Tours, No Tour and disabled/aggregate Other filter options from Tour entities. Treat source IDs as bounded opaque strings, including observed seven- and eight-character values. A bounded page provides observed choices, not a claim that every Tour is discoverable; never label a truncated result as a complete catalog.

Use the website's upcoming-search option and linked upcoming detail pages for future discovery. Upcoming pages can have a show ID in the path and a separate performance ID in the `setlist` query parameter. Only an explicitly associated performance ID may become an Event reference. An ambiguous multi-artist page or a page without a performance identity must not be guessed into a candidate.

Read only the selected performance's own fields and links. Sidebar timelines, advertisements, average setlists, other performers and unrelated upcoming widgets are not facts about the selected performance. Future songs and average songs are never prefilled.

The adapter must recognize the expected page structure, including legitimate empty results. HTTP 200 with a challenge/login/error page is unavailable, not an empty result. A page lacking the required identity structure is rejected. No CAPTCHA handling, browser automation or challenge bypass is part of the integration.

### Request and response behavior

Use fixed HTTPS provider hosts and server-constructed paths/query parameters. Do not proxy arbitrary caller-supplied URLs. Validate extracted links against the provider host and supported resource shape before following them. Bound query lengths, response sizes, page counts and candidate counts. Suggested reads return at most ten candidates; additional browsing uses an explicit next-page action, never an unbounded crawl.

Apply separate shared pacing and configured account quota limits to setlist.fm API and website requests. Do not infer production quotas from a public example. Cache only normalized public suggestions for the short duration permitted by the provider agreement; keys include source, operation, filters, page and locale. Reattach current local matches and issue fresh selection proofs after cache reads. Credentials, raw HTML and complete response payloads do not enter persistent catalog or browser storage.

Use bounded timeouts and no automatic retry loops. A busy/rate-limited read returns a recoverable 429 with Retry-After; unavailable configuration, transport, parsing or upstream service returns a recoverable 503. Provider failures do not erase successful local results or block manual creation. API and HTML states remain independent; an API outage does not silently start a website crawl.

## Identity and schema

Every record retains its immutable Spectacles UUID. Provider references include provider, resource type and provider ID. Names are display and search text, never automatic identity keys.

| Entity | Provider identity |
| --- | --- |
| Artist | MusicBrainz Artist MBID; a setlist.fm website Artist ID can be retained as an additional explicitly linked reference. |
| Tour | setlist.fm Tour ID stored as `external_provider_id`, with provider `setlist_fm` and resource type `tour`. |
| Event | setlist.fm performance/setlist ID, resource type `setlist`; never a version ID or show-page ID. |
| Venue | MusicBrainz Place MBID and/or setlist.fm Venue ID, each independently identified. |
| Song | Existing local/MusicBrainz Work identity rules. |

Reuse the current provider-reference tables and uniqueness boundaries. Rename the Tour provider-reference column currently called `provider_resource_id` to `external_provider_id`, updating its unique constraint, model, resource, validation and consumers together. Do not keep duplicate ID columns. Other entity provider-reference column names need not change for this feature. Preserve existing MusicBrainz Tour references when updating that contract.

Support normalized full Venue address, alias strings and one optional attributed image descriptor. A full MusicBrainz address must not be guessed into street/house-number/postcode components; retain it in a nullable `address` field alongside existing structured fields. Add bounded normalized `aliases` and nullable `image` metadata fields to the Venue persistence/resource/browser contracts. Image metadata contains file/thumbnail URLs, source page, creator/credit, license name and license URL; no raw upstream object or image bytes. The plan selects the exact physical representation while preserving these public semantics.

The user reports there is no deployed data requiring migration. Schema and code can change together, but existing provider-linked fixtures, manual records, collection contracts and local browser drafts remain acceptance cases. Do not erase local data or edit unrelated files as a shortcut.

## Search and form behavior

Keep the Artist → Tour → Event → Setlist wizard. Retain accessible combobox interaction, IME handling, stale-response cancellation, session isolation and explicit child-editor confirmation before navigation.

Local choices remain first. Add an explicit external-search action even when local results exist, so a partial local catalog cannot hide useful provider results. Label source groups and distinguish loading, empty, unavailable and rate-limited states. Restore query text without automatically opening old result lists or replaying searches.

Artist discovery keeps MusicBrainz as the identity/metadata source and uses setlist.fm for associated performances and Tour context. Multiple manual/local Artists remain possible. Known UUIDs and MBIDs are passed in their own validated fields; unresolved names do not become identities.

### Performance preview and application

Preview the selected Artist, Tour or known absence, Venue, exact calendar date, source link and local-match status. Do not interpret `lastUpdated` as performance time. Missing start time stays unknown; do not infer a timezone or precise UTC instant from city-centre coordinates.

Apply is one atomic wizard-state transition, replacing the prior Artist lineup with the selected performance Artist, replacing prior Tours with that performance's Tour or an empty Tour choice, and applying its Venue and date. Derive the default title from the selected Artist when no source title exists. Clear any previous performance's time/timezone values that the new selection cannot establish. Preserve personal notes, flags and existing setlist entries. Show the replacement in the preview before the user applies it.

Discard abandoned pending Artist/Tour/Venue/Event identities from the final request while retaining drafts referenced by preserved setlist performers or Songs. Cancelling preview changes nothing. A later response for a previous Artist, Tour, Venue or performance cannot replace the current draft.

A confirmed new provider-assisted Event remains an editable draft. Existing name-edit rules continue to clear the affected Artist/Tour provider selection when the user creates a different named identity. Changing the performance's Artist, Venue or date explicitly detaches the Event from that performance; preserve entered values for manual creation. Explain this transition before applying it. Personal-field edits do not detach any catalog identity.

### Existing local matches

Resolve matches through provider references, never name similarity. If a performance already identifies a local Event, preview and adopt its saved title, Artists, Tours, Venue and date/time values; the local facts take precedence over the provider response. Keep shared fields read-only. Explicit detachment creates a new draft, clears the original Event/performance identity and preserves current values and personal fields.

The same selected Artist, Tour or Venue identity reuses its existing local record without modifying shared facts. If final save discovers an identity created since preview, reuse compatible local facts. If reuse would change the reviewed Event group, return an actionable conflict with no partial writes so the user can review the existing record or explicitly detach. Never overwrite an existing shared record silently.

## Venue enrichment and history

Place search and detail can prefill the Venue form. Display names and addresses in their original spelling, preserve optional/unknown fields, and permit a manual Venue without provider IDs. Only precise Place coordinates populate the Venue map; setlist.fm city coordinates do not.

Automatically connect MusicBrainz and setlist.fm Venues through an explicit URL relationship or an already saved provider association. A similar name/city can suggest a candidate for review, but does not establish a shared identity. Conflicting explicit links require user confirmation; they do not reassign provider IDs.

The Event step's Venue preview shows past and upcoming performance sections with separate loading/empty/failure states and explicit pagination. Use the API for past history and HTML for future history. Selecting a performance enters the same Event preview/apply flow. Browsing or enrichment alone cannot mutate an existing Venue.

Resolve an optional image from a MusicBrainz image relationship or its linked Wikidata/Wikimedia Commons item. Use only an image with usable source/license/credit metadata; unavailable or unsuitable images leave an ordinary no-image preview. Preserve attribution in previews and saved displays. Normalize credit to safe text, use validated image/source hosts and URLs, and avoid rendering provider HTML. Save selected metadata only; image upload/hosting and arbitrary website photo scraping are outside scope.

## Save transaction and provider validation

Extend the current pending-catalog contract for provider-assisted Tours, Venues and Events rather than trusting browser-supplied IDs. Reuse authenticated encrypted public-identity proofs, with explicit provider/resource kind, external ID and a 24-hour expiry. Bind the confirmed performance group to the identity proof so a forged or mixed Artist/Venue/date group cannot claim a different source performance. Keep proofs bounded and distinct from authentication credentials.

Validate proof kind, expiry, normalized values, mapping references, local UUID exclusivity and unused pending records before writes. Resolve Artist, Tour, Venue and Event references inside the existing Attendance transaction and preserve unique provider tuples under concurrent saves. A selected performance resolves to at most one local Event. Provider references are created only for explicitly confirmed identities.

Save uses confirmed draft values without an upstream network dependency. Expired selections produce field-specific validation and an explicit reselect or manual-detach path; do not silently drop IDs or refresh over user edits. A failed or uncertain mutation preserves the draft and stable Attendance UUID. Existing collection reconciliation precedes any retry after an ambiguous response. No automatic mutation replay is added.

## Update and verify local form recovery

Local recovery is part of feature acceptance, including unconfirmed fields and intermediate decisions. Update both the schema/decoder and component wiring; adding provider fields to the final payload alone is insufficient.

Retain one bounded local creation snapshot per account UUID and device. Keep the existing storage key as the compatibility key, and introduce snapshot payload version 2. Strictly validate and migrate valid version-1 snapshots in memory, preserving their current values, stable UUIDs, MusicBrainz selections and unfinished editors. Write version 2 on a subsequent user change. Unknown, corrupt, oversized or unmigratable stored snapshots remain untouched until explicit discard or confirmed successful creation. Do not create a second key that could resurrect an old draft after clearing the new one.

Recover all current state plus the following new state:

- selected provider/resource kinds, opaque external IDs, confirmed public-identity proofs and draft mapping keys;
- pending Artist/Tour/Venue/Event editors, exact entered text and partially entered address/coordinate values;
- performance application/detachment decisions and a normalized selected preview identity/summary sufficient to resume review;
- Venue aliases, address, selected image attribution metadata and confirmed cross-provider associations;
- current step, external query/filter text and history-section selection.

Store only selected normalized data needed to resume the form. Exclude fetched search/history collections, HTML, provider credentials, authentication/session/CSRF data, image bytes, active requests, callbacks and saved collection mirrors. A pending preview remains unconfirmed after recovery; restoring it must not apply fields or write to the server. Request fresh detail only when the user explicitly resumes review. Expired proofs preserve visible fields and require the same reselect/manual path as an uninterrupted form.

Preserve synchronous change writes and the closed-controller guard. Dismissal, reload and same-account reauthentication retain the draft. Switching accounts cannot expose, write or clear another account's draft. Clear only after confirmed successful creation or explicit Discard draft. Validation failure, provider failure, session expiration and uncertain save keep it. Late callbacks cannot resurrect a cleared snapshot. Storage read/write/quota/removal failures warn without freezing editing or blocking an otherwise valid server save.

This feature updates recovery for new concert creation. Saved-concert editing retains its current behavior.

## Verification and acceptance

Use deterministic HTTP fakes with stray network calls blocked for product tests. Parse minimal sanitized HTML fixtures retaining the required structures and identities; exclude unrelated scripts, third-party keys, complete song lists and private data. Parser checks must cover valid empty pages, changed/missing structure and HTTP-200 challenge pages separately.

Backend coverage must prove historical API filters/detail normalization, automatic Tour IDs, upcoming show/performance-ID distinction, Artist website-ID/MBID separation, same-name identity separation, Venue cross-links/coordinate semantics, malformed data, throttling, quotas, timeouts, proof tampering/expiry, local reuse, whole-transaction rollback and provider-identity races. Verify MusicBrainz Artist/Work behavior, manual/nonmusic paths, authorization and existing collection contracts remain intact. Use SQLite and the project's dedicated PostgreSQL test path where available, reporting any environment limitation precisely.

Frontend tests must prove provider/local search access, query/filter cancellation, preview/apply/cancel, complete group replacement, clearing stale time facts, pending-reference pruning, preserved personal/setlist state, local Event authority and explicit detachment. Venue history must identify the chosen performance independently of other listed Artists or shows. Missing images and one failing history section cannot break the form.

Recovery tests must round-trip every new state, migrate a valid version-1 draft, preserve corrupt/unknown snapshots, restore unfinished nested fields, retain provider IDs and Unicode content, preserve expired-proof values, isolate accounts and prevent automatic network/mutation replay. Verify failures retain the same draft and IDs, while confirmed save/discard clear it without late-write resurrection. Exercise unavailable/quota-limited storage and failed removal.

Browser acceptance must use the actual integrated wizard with controlled provider responses and authenticated local backend saving: apply past and upcoming performances, reuse an existing Event, preview Venue history/image, edit/detach, dismiss/reopen/reload from each step, restore unconfirmed editors and previews, switch A → B → A, expire a selection, fail and reconcile a save, confirm success, and discard. Check keyboard/IME behavior and desktop/mobile layout. A synthetic component fixture alone does not establish authenticated save/reload acceptance.

Run the frontend suite, lint and build and the relevant backend suite using the machine's configured runtime prefixes. Record commands actually run and results, distinguishing automated tests from interactive checks. This specification records required future verification; it does not claim those product checks have run for the new feature.

## Research and external dependencies

The [API overview](https://api.setlist.fm/docs/1.0/index.html), [search contract](https://api.setlist.fm/docs/1.0/resource__1.0_search_setlists.html), [Tour schema](https://api.setlist.fm/docs/1.0/json_Tour.html) and [performance schema](https://api.setlist.fm/docs/1.0/json_Setlist.html) establish documented API fields. The [provider administrator's explanation](https://www.setlist.fm/forum/setlistfm/setlistfm-api/api-10searchsetlists-missing-upcoming-events-6bd776d6) distinguishes website upcoming content from API availability.

Bounded curl checks on 2026-10-25 returned HTTP 200 and useful server-rendered HTML for [Maná search](https://www.setlist.fm/search?artist=4bd7afb6&query=mana), [Drama y luz filtering](https://www.setlist.fm/search?artist=4bd7afb6&tour=2bde703a&query=mana), an [individual historical performance](https://www.setlist.fm/setlist/mana/2014/parque-david-ben-gurion-pachuca-mexico-6bc56e8a.html), [upcoming-inclusive search](https://www.setlist.fm/search?artist=4bd7afb6&query=mana&upcoming=true) and an [upcoming performance](https://www.setlist.fm/show/2026-10-30/allstate-arena-rosemont-il-2b893c8a.html?setlist=b4cc196). These establish feasibility for selected examples, not comprehensive coverage or a stable HTML contract. Authenticated API entitlement, account quotas and production throughput were not tested.

The [Movistar Arena Place lookup](https://musicbrainz.org/ws/2/place/50573105-4880-4a66-b668-0356374918f0?inc=aliases+url-rels&fmt=json) returned address, venue coordinates, setlist.fm and Wikidata links. Its linked Wikidata item yielded a [Commons photograph](https://commons.wikimedia.org/wiki/File:Movistar_Arena_junio_de_2025_-_BugWarp_04.jpg) with creator and CC BY-SA 4.0 metadata, demonstrating optional image discovery for that example.

The published [setlist.fm terms](https://www.setlist.fm/help/terms) restrict automated website retrieval, retention beyond short caching, modification and competing use. Form prefilling and user confirmation do not establish an exception. Appropriate provider permission for HTML access and retained reviewed values, IDs and recovery drafts remains an external activation dependency. Continue design/planning without representing this as resolved or adding an end-user permission checkbox as a substitute. API keys and production activation are separate operator actions.

## Review and handoff

All approved product decisions, including API plus HTML, automatic Tour IDs, one Event per source performance, complete group application, local Event authority and local draft recovery are included. No product decision is delegated to guessed source data. The implementation plan follows user review and approval of this written specification, using the explicitly selected writing-plans workflow. Implementation follows a separately selected execution method.
