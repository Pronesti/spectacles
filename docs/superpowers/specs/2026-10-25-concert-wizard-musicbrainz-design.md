# Concert Creation Wizard and MusicBrainz Design

Date: 2026-10-25

Status: Conversational design approved by the user, followed by an explicit request to write the implementation plan. This document records that design and its implementation decisions. Product implementation is not authorized by this document.

## Intent and scope

Build a new creation wizard with the steps Artist → Tour → Event → Setlist. Use the existing interface as the visual reference. Every step supports autocomplete-assisted selection of existing records and creation of missing records. The first release connects MusicBrainz for Artists and Songs; Tours, Events, and Venues use the local catalog and manual entry.

This is one coherent creation flow, with provider lookup and persistence as its supporting components. Additional provider integrations are separate future work. The existing concert edit form and post-creation setlist editing remain available.

Success means a signed-in user can complete all four steps, review selected identities, and save one Attendance with its confirmed catalog relationships and setlist. The same valid concert can be created manually while MusicBrainz is unavailable. Navigating backward retains entered data; cancelling persists no domain records.

## Global constraints

- Scope is planning only until the user authorizes implementation.
- Use the installed Laravel 13, Sanctum 4, PHP 8.5, React 19, TypeScript 6, Vite 8, and Node 24 toolchain; add no dependencies.
- Preserve the existing typography, colors, drawer, fields, buttons, and responsive visual language.
- Keep one in-memory draft across all four steps and persist domain records only on final Save concert.
- Preserve multiple Artists, zero or multiple Tours, nullable Venue/timezone, incomplete dates, midnight/seconds, Unicode names, zero coordinates, repeated songs, notes, and tape flags.
- Local catalog UUIDs and provider identifiers remain separate; never match records by name or title alone.
- MusicBrainz Artist IDs belong to artist_provider_ids with provider=musicbrainz and resource_type=artist; composition IDs belong to song_provider_ids with provider=musicbrainz and resource_type=work.
- Suggestions require user confirmation; searching, highlighting, or abandoning a suggestion creates no catalog or Attendance records.
- Keep manual creation available during empty results, missing configuration, rate limiting, malformed provider responses, and provider/network failures.
- Preserve Sanctum cookie authentication, CSRF for writes, account isolation, shared Event authorization, and last-save-wins behavior.
- Existing shared catalog records are reused without modifying their saved facts.
- Setlists remain Attendance-owned ordered entries; do not introduce a public setlist catalog.
- Do not add setlist.fm, Geoapify, external Tour/Event lookup, Spotify resolution, recording-to-work discovery, background refresh, synchronization, schema migrations, or browser storage of drafts.
- Preserve SQLite and PostgreSQL compatibility and use the machine's configured PHP/Composer command prefixes.
- Implement on one feature branch based on main with only release dependencies; do not merge or rebase dev into it. Commit, push, PR, and deployment actions require the applicable user authorization.

## Existing implementation and reuse

Repository inspected at commit `38099a5` on `feat/live-event-data-layer`, with a clean working tree before documentation creation.

- `frontend/src/components/ConcertForm.tsx`: current creation/edit drawer, local catalog selection, manual Venue fields, optional Tours, date precision, personal flags, and notes.
- `frontend/src/concertDraft.ts`: draft creation, Event/Venue adoption, final validation, and independent song/performer identity detachment.
- `frontend/src/components/CatalogPicker.tsx` and `catalogApi.ts`: local reads, cancellation, safe response decoding, session errors, and latest-response protection.
- `frontend/src/components/SetlistEditor.tsx` and `SetlistsView.tsx`: per-entry persistence after creation, repeated songs, notes, ordering, and tape flags.
- `frontend/src/App.tsx` and `collectionApi.ts`: authenticated final-save boundary and collection replacement only after a confirmed response.
- `backend/app/Http/Controllers/CollectionController.php`: one transaction for Event, Attendance, relationships, and setlist. Currently manual artist names create Artists; text-only setlist entries may have no Song record.
- Existing Artist, Song, and provider-ID models/tables already support confirmed MusicBrainz identities with unique provider-resource tuples. No new tables are needed.

Use focused classes in the existing `backend/app/Http/` directory for the provider HTTP client, selection proof, and request-to-catalog resolver. This avoids creating a new application directory or a general provider framework.

## Step behavior

### Artist

Show a searchable select with separately labeled local-catalog and MusicBrainz groups. A selected local Artist adds its UUID to the lineup. A MusicBrainz selection opens the new-artist fields prefilled with canonical name, normalized type, and disambiguation. Area/country are display context only because Artist has no geographic columns. Users can instead open the manual creation fields. Confirming these fields adds a draft Artist, not a database record.

Keep multiple Artists and distinct same-name Artists. Remove duplicate local UUIDs, repeated draft keys, or the same confirmed Artist MBID within the lineup. A draft Artist can also be selected as a setlist performer without creating another Artist.

Local selections are read-only shared facts. Editing a selected identity's name/type/disambiguation uses an explicit Create new Artist action, with no silent update to the original. Editing the name of a new MusicBrainz-prefilled draft clears its provider selection and labels it manual; metadata-only edits can retain the explicitly selected identity.

An empty lineup remains valid for nonmusic or unknown-lineup events. The Event step still requires a title if no artist-derived title is possible.

### Tour

Search existing local Tours or enter a name for a new Tour. Support multiple selections and Skip tour. Existing artist filters can narrow a lookup for a persisted Artist; retain an unfiltered search so unlinked Tours remain discoverable. New Artists have no local UUID yet and must not be sent as catalog filter IDs.

A Tour's roster never supplies the Event lineup. This release does not add a roster editor or automatically associate every lineup Artist with a newly named Tour.

### Event

Search local Events, with title/artist text and the existing optional date/Venue filters. Preview full details before adoption. Explicit adoption replaces the earlier lineup and Tour choices with that Event's actual relationships; explain that replacement before applying it. Flags, notes, and setlist drafts stay intact. Discard unreferenced pending Artist/Song drafts when assembling the final request.

Create-new mode includes title, Event type, Venue, date precision, local time, timezone, interest/ticket flags, and notes. Venue has its own local select and manual fields inside this step. Preserve incomplete dates and unknown facts. Include the existing Event type choices so the empty-lineup/nonmusic path is usable.

After adopting an Event, its shared fields are read-only until the user explicitly chooses Create a new Event using these details. Changing Artists or Tours on an earlier step while an Event is selected offers that same explicit transition. If declined, retain the selected Event and its relationships. Detachment keeps all current draft values, personal fields, and setlist entries; it does not modify the selected Event.

### Setlist

Start with an empty, skippable list. Each song row offers local Song and MusicBrainz Work autocomplete, manual Song creation, and a text-only entry option. A selected Work prefills title and disambiguation, with available composer/lyricist context for review. A Work selection never fills the performer. Direct Work search is sufficient for this release; do not search recordings to infer compositions.

Explicit Create Song fields produce a pending local Song. A text-only row keeps `song_id` null, preserving the existing unresolved-entry behavior. Song titles are display text and can differ from another row's title. Editing a selected row title clears only its Song selection; changing performer text clears only its performer selection. Notes clear Song/performer identities and tape status.

Performers can reuse lineup draft Artists, local Artists, or new confirmed/manual Artists. Preserve repeated Songs as separate ordered entries, notes/section rows, tape flags, reorder, edit, and remove. A draft row's Add/Update action changes only wizard state. The final action is Save concert, with a compact review of Artist/Tour/Event choices above it. There is no fifth wizard step.

## Lookup architecture

Expose authenticated `GET /api/v1/musicbrainz/artists?q=...` and `GET /api/v1/musicbrainz/works?q=...`. These are suggestion DTOs, not persisted JSON:API resources. Continue using JSON:API Resources for stored catalog records. Accept only a query of 2–200 Unicode characters; return at most ten normalized candidates, with a nullable matching local UUID. Artist and Work searches use fixed HTTPS MusicBrainz paths, literal escaped prefix terms, JSON, and no redirects. Do not accept caller-supplied upstream URLs, include expressions, or resource kinds.

Use an identifying configured User-Agent. Missing configuration disables external suggestions with a recoverable service response. No API key or provider account is required for this public lookup. Upstream requests share an atomic cache-backed application gate with at least 1,100 ms between request starts, across Artists and Works and across workers. The database cache/lock tables already exist. Production instances must share this cache; tests use an isolated store.

Cache successful normalized public candidates, including empty lists, for 15 minutes. Attach local UUID matches after cache retrieval so later-created local entities are discovered immediately. Issue fresh selection proofs after cache retrieval. Do not cache transport failures as empty lists. Use a 2-second connection timeout and 5-second total upstream timeout, with no automatic upstream retries. Busy or limited lookups return a safe 429 with Retry-After; unavailable/malformed upstream responses return 503. Neither prevents local results or manual entry.

The client debounces by 350 ms, requires two characters for external lookup, and ignores stale or aborted responses, including after session changes. Local and external result states remain independent. The autocomplete implements labeled combobox/listbox semantics, Arrow keys, Enter, Escape, visible source groups, loading/empty/failure states, and accessible creation actions. Enter selects or confirms the focused action and never accidentally saves the entire wizard. Text entered through an IME does not trigger selection before composition ends.

Provider fields are untrusted. Validate IDs, names/titles, optional fields, relation shapes, and bounded lengths. Omit malformed candidates without corrupting valid siblings; reject an invalid envelope as unavailable. Keep only names/identifiers/type/disambiguation/area/country and writer context. No raw payloads, annotations, tags, ratings, links, or scores enter the saved domain or selection UI. Work relations may supply composer/lyricist display names but never performers.

## Final save and identity resolution

Keep the durable `Concert`, `Venue`, and collection response shapes unchanged. Add an optional top-level `pendingCatalog` input to attendance writes. It contains keyed pending Artists/Songs, lineup-position mappings, and setlist-entry mappings. Stable client draft UUIDs allow one pending Artist or Song to be referenced multiple times without name-based matching.

Each MusicBrainz suggestion carries an authenticated encrypted selection proof containing version, resource kind, MBID, and a 24-hour expiry. It contains public provider identity only and is not an authentication credential. Final save validates the proof and user-confirmed fields without another provider request. A forged, expired, or wrong-kind proof returns a field-specific 422 before domain writes; the draft stays available for reselecting or explicitly clearing the match and using manual creation. Do not silently remove a provider identity on save failure.

Validate mapping keys, lengths, references, and identity exclusivity. A lineup slot cannot have both a local UUID and pending Artist. A setlist row cannot have both a local Song/performer UUID and a corresponding pending mapping. Mappings must point to existing row IDs/positions, notes cannot contain identities, and unreferenced pending records are rejected. When reusing an Event, no pending lineup Artists are allowed; its existing lineup/Tours are authoritative. Pending Songs and setlist performers remain allowed.

Resolve pending records inside the existing Attendance save transaction, after user/Attendance authorization and before linking them. For a selected provider identity, reuse its existing Artist/Song without updating shared facts. Otherwise create the parent and provider reference in a nested transaction/savepoint. On a race for the same unique provider tuple, roll back the losing parent creation and reuse the winner; rethrow unrelated integrity errors. Resolve provider identities in stable tuple order. Duplicate resolved lineup identities reject the whole save; repeated Song entries remain valid.

An explicit manual pending Song creates a local Song without a provider reference. Legacy text-only rows still do not create a Song. A draft Artist key resolves once for all lineup/performer uses. Retain all current authorization, rollback, member flags, seat preservation, and collection serialization behavior. Only publish the server-returned collection; selection proofs and pending maps never enter durable browser collection state.

Disable final Save while pending. Keep the same Attendance UUID through failures. Do not automatically replay mutations after timeout or 419. Existing conflict behavior remains: an ambiguous response requires collection reconciliation before retry, avoiding duplicate Attendance/Event creation.

## Verification and acceptance

Use PHPUnit feature tests with fake HTTP and prevented stray requests for both lookup endpoints, query escaping, optional metadata, writer/performer separation, cached hits, shared pacing, malformed data, and provider failures. Use feature tests for pending mappings, proof validation, manual versus selected identities, rollback, duplicate tuples, repeated Songs, existing shared facts, and account isolation on SQLite and a dedicated PostgreSQL test database. A deterministic unique-constraint test does not by itself prove two-process concurrency; include a PostgreSQL two-session check in integration verification if concurrent workers are available and report that limit otherwise.

Use Node tests for response decoding, request cancellation, wizard transitions, Event adoption/detachment, pending-map pruning, identity clearing, and payload composition. Use existing Vite/React SSR tests for accessible markup and preservation of the edit form. SSR and reducer tests do not prove browser focus, keyboard interaction, layout, or network behavior; exercise those manually with fake MusicBrainz responses and record the result.

Verify mobile and desktop widths, all four steps, a fully manual path, missing provider config, 503/429, stale searches, ambiguous same-name candidates, wrong first Work match, final save failure, same-account session recovery, different-account isolation, cancellation without writes, and saved setlist reload. Product tests are prescribed for implementation, not run during planning.

## Research support and limits

- [Provider validation, 2026-10-22](../../research/2026-10-22-provider-validation.md): intended Artist first in nine selected searches; distinguishes WOS, Los Bunkers, Zoé, accented names, and missing country. Eleven of 53 saved MusicBrainz responses were 503; these were observed session results, not an availability guarantee.
- The same report found a wrong first Work result for Eres and demonstrated a common Work across different performers of El fantasma de Canterville. This supports explicit identity review and independent performer fields.
- [Source research](../../research/2026-10-22-normalization-data-sources.md) and the [existing domain specification](2026-10-22-live-events-database-design.md) supply identity, optional-data, and provider-role constraints. This spec narrows the release to MusicBrainz; it does not change the other selected providers' future roles.
- [Official search documentation](https://musicbrainz.org/doc/MusicBrainz_API/Search), checked 2026-10-25, documents Lucene escaping, search resources, and limits. [API overview](https://musicbrainz.org/doc/MusicBrainz_API) documents identification and one-call-per-second pacing; it was retrieved earlier in this conversation, while later rechecks received 429. [Work documentation](https://musicbrainz.org/doc/Work) separates compositions and performance recordings. [Data license](https://musicbrainz.org/doc/About/Data_License), checked 2026-10-25, distinguishes core and supplementary data.
- The old exact-name batches did not validate incremental prefix matching or sustained autocomplete throughput. Verify prefix behavior with fixed fixtures and a bounded, explicitly identified live check during implementation acceptance; do not claim the old batches prove that behavior.

## Review outcome

The design preserves the approved four-step experience and confines external integration to MusicBrainz Artists/Works. Contract details above resolve draft identity, atomic save, and local record reuse without a migration. No additional product decision blocks the implementation plan. This document and its companion plan are local planning deliverables; neither authorizes execution or delivery actions.
