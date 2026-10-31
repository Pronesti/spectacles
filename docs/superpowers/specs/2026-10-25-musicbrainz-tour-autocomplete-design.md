# MusicBrainz Tour Autocomplete Extension Design

Date: 2026-10-25

Status: Planning specification for the requested Tour autocomplete extension. Implementation and delivery are not authorized by this document.

## Purpose and release boundary

Extend the existing Tour step with MusicBrainz suggestions while preserving local catalog search, manual Tour creation, optional/multiple Tours, and the existing wizard's one-draft/final-save behavior. The user explicitly requested a plan for this extension after confirming that MusicBrainz represents Tours as Series of type Tour.

This specification supersedes only the external-Tour exclusion in the [original wizard design](2026-10-25-concert-wizard-musicbrainz-design.md). Its other constraints and the [domain identity rules](2026-10-22-live-events-database-design.md) remain applicable. Do not redesign the wizard or change the other provider assignments.

## Global constraints

- Planning only: do not implement, run product tests, commit, push, open PRs, merge, or deploy while producing this plan.
- Reuse the current Laravel 13/Sanctum 4/PHP 8.5 and React 19/TypeScript 6/Vite 8/Node 24 stack; add no dependencies or schema migrations.
- Preserve current uncommitted wizard/provider work, unrelated changes, ignored environment values, and user data.
- Keep local Tour search and manual creation available independently of MusicBrainz results, configuration, limits, or failures.
- Accept only MusicBrainz Series whose returned type is Tour; store provider=musicbrainz, resource_type=series, and the Series MBID in tour_provider_ids.provider_resource_id.
- MusicBrainz MBIDs never replace Spectacles-owned UUID external_id values, and names never establish identity.
- Keep all selected suggestions in memory until final Attendance save; cancellation creates no Tour, provider reference, Event, or Attendance.
- Preserve zero/multiple Tours, distinct same-name Tours, explicit Event adoption/detachment, personal fields, and staged setlist data.
- Reuse an existing provider-linked Tour without updating its saved name or relationships; resolve concurrent creation through existing unique constraints and savepoints.
- Keep Artist/Work lookup, selection proofs, existing attendance clients, durable collection DTOs, session isolation, and manual Tour writes compatible.
- Share the existing MusicBrainz identifying User-Agent, successful-result cache, outbound pacing gate, inbound throttle, timeouts, and safe error handling.
- Apply selected-Artist filtering only to the local catalog; external suggestions search by Tour name and must not imply verified Artist membership.
- Do not import a Tour's Artists, Events, dates, locations, recordings, or setlists, add synchronization, or automatically change the Event lineup.
- Do not expose operator contact details, environment values, cookies, selection proofs, or private QA data in tracked artifacts.
- Use the active machine's runtime instructions before PHP/Composer commands and preserve SQLite/PostgreSQL compatibility and the single-feature-branch release workflow.

## Current implementation

Inspected on `feat/live-event-data-layer` at HEAD `e440ef5`, with substantial uncommitted wizard/MusicBrainz implementation. The owning source is the current working tree, not the older implementation plan.

`WizardTourStep.tsx` already offers local search, manual name confirmation, optional multiple Tours, a local selected-Artist filter, and explicit Event detachment before changing selected Event relationships. `EntityAutocomplete.tsx` has separate local/provider reads but enables provider search only for Artists and Songs. The provider client, DTO type, proof validator, normalized response controller, and pending resolver similarly cover only Artist/Work.

`Tour` stores a name, UUID, and relationships. `TourProviderId` and the existing migration already enforce unique `(provider, resource_type, provider_resource_id)` identity. There is no Tour disambiguation column; provider disambiguation is transient selection context. `CollectionController` already creates manual Tours or reuses selected Tour UUIDs inside the Attendance transaction. `CollectionResource` returns Tours as `{name,catalogId}`; preserve that shape.

## Provider read contract

Add authenticated `GET /api/v1/musicbrainz/tours?q=...` under the current shared MusicBrainz throttle. Internally use kind/resource `series`, not a nonexistent upstream `tour` resource. Extend the existing candidate kind union with `series`; keep the current normalized candidate shape. The Tour endpoint produces only filtered Tour Series, so no additional persisted type or DTO version is needed.

Search the fixed MusicBrainz `/ws/2/series` endpoint using the existing literal escaped prefix terms and `AND type:tour`. Also validate returned `type` as Tour, excluding Festival, Residency, Run, release-group series, unknown/missing types, malformed IDs, and invalid names. The response's collection key is `series` and label key is `name`. Work/Artist envelopes and label keys stay unchanged. Successful empty results remain distinct from an unavailable service.

Return at most ten suggestions with Series MBID, name, optional disambiguation, matching local summary, and fresh selection proof. Artist type/area/country are null and writers is empty for Series candidates. Use explicit model mapping in the controller; Tour has no `disambiguation` column, so the local summary uses its saved name, UUID, and null descriptive fields. Match existing Tours through TourProviderId, after cache retrieval, without name matching.

Extend the existing version-1 encrypted proof allowlist with `series`, retaining kind/MBID/expiry validation and all Artist/Work proofs. Only the Tour-filtering provider read issues Series proofs to browser clients. Tour save validation accepts only a Series proof for a pending Tour; Artist/Work proofs cannot create a Tour.

The [official Series search fields](https://musicbrainz.org/doc/MusicBrainz_API/Search#Series), checked 2026-10-25, document name, alias, type, ID, comment, and tag fields, without an Artist-ID field. Do not invent `arid` support or query linked Artists/Events to simulate completeness. Label the existing checkbox **Filter local Tours by selected Artist** and explain that MusicBrainz suggestions use the entered Tour name.

## Selection and draft behavior

Use the current combined autocomplete. Local choices immediately reuse their UUID. A Series candidate with a matching local summary also uses that saved Tour name/UUID. A new Series candidate opens the existing inline Tour-name confirmation with its name prefilled, transient disambiguation, and MusicBrainz source label. No database record is created by search, selection, or confirmation.

A confirmed new provider suggestion carries a stable draft UUID, selected MBID for client duplicate detection, and `{name,selection}` input. Editing the prefilled name clears the provider proof/MBID and makes the choice manual; cancelling the inline editor adds nothing. Manual creation continues to work with no provider identity. Duplicate local IDs or active selected MBIDs are not added twice; distinct same-name IDs remain selectable. Blank Tour selection remains valid.

The Tour editor continues to report its editing/decision state to the wizard, preventing navigation/final save from discarding unconfirmed child fields. Adopting an existing Event replaces previous Tour choices and drops abandoned Tour metadata from the final payload. Removing or changing a selected Event's Tours requires the existing explicit detach choice; declining preserves the selected Event and draft values.

## Optional pending Tour contract

Keep durable Tour choices as `{name,catalogId}`. Add optional paired sidecar maps to the existing `pendingCatalog` request:

```ts
type TourDraftInput = { name: string; selection: string | null }
type PendingTour = { key: string; input: TourDraftInput; mbid?: string }
type WizardTourChoice = CatalogChoice & { draft?: PendingTour }
type PendingTourMaps = {
  tours?: Record<string, TourDraftInput>
  tourChoices?: Record<string, string>
}
```

Tour map keys are draft UUIDs; `tourChoices` keys are canonical zero-based decimal positions in `concert.catalog.tours`, pointing to those draft UUIDs. If either map is present, both must be present, including for explicitly empty maps. Legacy sidecars with only Artists/Songs/lineup/entries and requests without pending metadata remain valid. Each map has at most 100 entries. Do not make the new maps required for old clients.

A mapped choice must have null `catalogId`, the same name as its pending input, and no selected `eventId`. Require valid keys/positions, an existing target slot/draft, bounded nonempty name, valid nullable proof, exclusive identity, referenced-only pending Tours, and no draft-key collision across Artist/Song/Tour namespaces. Invalid, expired, or wrong-kind proofs return field-specific 422 before domain writes. Pending metadata/proofs must be stripped from every finalized durable Tour choice; the old finalizer spreads Tour choice objects and would otherwise leak new draft properties.

Resolve Tour jobs with the existing stable sort/savepoint identity path inside final Attendance persistence. Map `series` explicitly to Tour/TourProviderId/tour_id/tour. A null proof creates a manual Tour; a selected Series reuses or creates the unique referenced Tour. Resolve once per draft key, then fill local UUIDs in the Tour slots before existing Event linking. Preserve existing relation deduplication, shared-fact protection, late-failure rollback, and account isolation. No provider request occurs during final save.

## Acceptance and verification

The plan must cover fake-provider read tests for Series filtering/query escaping/local-match refresh/shared pacing; strict proof and optional-map tests; Tour identity reuse/race rollback; frontend strict kind-to-route decoding; draft metadata stripping and pruning; and interactive select/review/manual/error behavior. Reuse existing tests and PostgreSQL race workers, extending their explicit kind mapping to Series without introducing a second concurrency harness.

Verify local results survive provider failure, empty external results retain manual creation, Artist/Work searches still work, multiple/zero Tours remain valid, same-name candidates remain distinct, Event adoption prunes pending Tours, and name editing clears only the Tour identity. SSR and pure tests do not prove the full interaction; implementation acceptance includes real browser tests with safe fixtures. Live prefix verification is a bounded identified optional read during implementation, never a coverage guarantee. PostgreSQL checks require the dedicated test database and must be reported as unverified if its connection is unavailable.

## Evidence and uncertainty

- [2026-10-22 provider validation](../../research/2026-10-22-provider-validation.md): one of four named Tour searches returned a Tour Series, supporting optional suggestions and manual fallback.
- [Saved Eras Tour search](../../research/evidence/2026-10-22-provider-validation/mb-t03.json) and [detail](../../research/evidence/2026-10-22-provider-validation/mb-d04.json): concrete Series MBID/type and relationships; this feature does not import those related Events or Artists.
- [Series model](https://musicbrainz.org/doc/Series) and [Series search fields](https://musicbrainz.org/doc/MusicBrainz_API/Search#Series): current model/resource/query basis. Name-prefix behavior for this new application path remains an implementation acceptance check.

No material product decision blocks planning. This is an additive extension to the current provider/draft/save boundaries, with no permission to execute the plan yet.
