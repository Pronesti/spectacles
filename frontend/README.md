See [the repository README](../README.md) for setup, verification, and Laravel Cloud deployment.

## Account collection

The dashboard, concert manager, calendar, activity heatmap, Venue map, profile counts, and setlists read the signed-in user's database collection from `GET /api/v1/collection`. New accounts start empty. No demo generator or browser-local workspace is loaded or saved by the app. Old browser preview records are left untouched and are not automatically imported.

Concert creation and edits use `POST /api/v1/attendances` and `PATCH /api/v1/attendances/{uuid}`. These save Events, manual Artists/Venues/Tours, Attendance notes, member flags, and ordered setlist entries in transactions. Confirmed catalog references reuse existing records. Edits detached from an Event reference create separate Event facts without overwriting the shared catalog. Only accepted members can read/edit an Attendance; only its owner can remove it. `DELETE /api/v1/attendances/{uuid}` archives the Attendance so Undo can restore its memberships and other retained data. Saves use Sanctum cookies and CSRF, publish only after confirmation, and retain editors on failures. Requests are never automatically replayed.

Date precision, nullable timezone/Venue, midnight/seconds, zero coordinates, Unicode names and repeated songs remain supported. The map illustration is static; Venue names and locations come from the database, with missing coordinates kept in the directory. The existing concert photograph remains a design asset.

## Account authentication

Registration and sign-in use the Laravel Sanctum cookie session. The app checks `GET /api/v1/me` before showing an account identity. Mutations first request `/sanctum/csrf-cookie`, then send the decoded `XSRF-TOKEN` as `X-XSRF-TOKEN` with browser credentials. There are no bearer tokens or browser-stored account identities. Logout ends the current backend session; a failed logout keeps the visible identity and shows an error.

Your profile saves name, email, and preferred language to Laravel. Email changes apply immediately; use the new address on the next sign-in. The current supported preference is English or the application default. Photo uploads, avatar color, city, bio, verification, and password reset are outside this integration.

For local sign-in prefill, set `DEV_LOGIN_EMAIL` and `DEV_LOGIN_PASSWORD` in the ignored `frontend/.env.local`, then restart Vite. Use an existing local account. The fields remain editable and require clicking Sign in; registration stays blank. These values are supplied only by the development server and excluded from production builds.

Concerts and setlists are account-scoped database records. Signing out or switching accounts does not expose the previous account’s collection. Old preview/profile keys are ignored and left untouched.

Set `VITE_API_BASE_URL` to the backend origin and run the Vite development server for HMR. Frontend and API must share a parent domain for cookie authentication. Local Yerd uses `https://spectacles.test` and `https://api.spectacles.test`; backend stateful domains, allowed frontend origins, session domain, secure cookies, and session storage must match. `GET /api/health` remains a separate public health indicator.

Run `npm run lint`, `npm run build`, and `npm test`. Account protocol tests use fake fetch responses; markup tests use the installed Vite/React server renderer. Real cookie/session, interaction, responsive, and keyboard acceptance is recorded in `design/account/qa/auth-integration-verification.md` after browser testing. The historical image references and visual specification remain in `design/account/`.

## Shared catalog selection

Signed-in users can search existing Events, Artists, Venues, Songs, and Tours from the editors. Choosing an Event fetches fresh details and asks before adoption. Personal flags, notes, and setlists remain in the draft. Every value can also be entered manually. Catalog names are not identity keys: UUIDs distinguish duplicate and comma-containing names.

Tours are optional; their roster does not populate a lineup. Song selection does not identify a performer. The six existing catalog GETs remain authenticated and separate from private collection reads/writes. MusicBrainz Artist/Work/Place suggestions and setlist.fm Tour/performance suggestions are mediated by authenticated backend reads when configured; no synchronization is connected.

## Four-step creation

New concerts use Artist → Tour → Event → Setlist. Each account has one local creation draft on this device. Closing, Cancel, Escape, outside dismissal and reload retain the current step, entered fields and unconfirmed editors; reopening restores it ahead of a newly selected calendar date. Discard draft explicitly clears it. Cancel writes no domain records. Existing concert edits still use the prior editor; saved setlist entries retain their immediate-save flow.

New-concert creation collects one Artist, zero or one Tour, one Venue and one Event/performance. Each entity has one query and one keyboard-accessible result list, regardless of source. Empty focus browses five local choices; typed searches wait for three trimmed Unicode characters. A successful zero-match database response starts the appropriate online lookup; local failures do not. Artist uses MusicBrainz, Tour uses artist-scoped MusicBrainz/setlist.fm results, Venue uses MusicBrainz Places, and Event/performance uses local Events or setlist.fm. Provider failures keep manual creation available, and partial results remain labeled as limited. Confirmed selections collapse to a summary with Change; changing an Artist clears the previous Artist-scoped Tour, with saved Event detachment requiring confirmation.

Creation cardinalities do not narrow the shared catalog, saved multi-Artist Event lineups, existing concert editing, or Setlist performer editing. Old recovered multi-selection drafts remain visible with explicit Keep only/Remove choices and cannot advance unresolved. Optional Tour can be skipped. Song/performer editing retains its existing independent workflow; Work identity never fills a performer automatically. Repeated Songs, notes, tape flags and ordering remain supported.

The Event step uses HTML-first setlist.fm lookup and a single local/source result list. Explicit preview Apply sets Artist, optional Tour, Venue and date together while preserving personal notes, flags and setlist. Unknown time facts stay unknown. Saved Events remain authoritative until the user explicitly creates a new Event. Venue review preserves address, aliases, coordinates, images and canonical source references; optional metadata stays behind disclosures. Normal creation has no separate provider search panels or Venue-history search form. Existing pending Venue/history reviews from older drafts remain resumable. No intermediate selection writes catalog records.

Only Save concert submits the draft and its ephemeral pending catalog sidecar. An open child editor must be confirmed/cancelled first. Failures keep the Attendance UUID and entered values. An uncertain response blocks another write until Reload collection reconciles server state; mutations are never automatically replayed. Only confirmed successful creation or explicit discard clears the local creation draft. Its versioned, bounded account-specific storage includes necessary public provider selection proofs (which expire after 24 hours); rejected matches must be reselected or explicitly replaced with manual creation. Auth credentials/cookies/CSRF values, fetched result caches and saved collection mirrors are not stored. Corrupt or unknown-version drafts remain untouched until explicit discard or successful save. Unavailable/full storage warns while keeping editing and server saving usable. Draft metadata/proofs never enter durable Workspace DTOs.


Creation recovery retains the existing `spectacles.concert-creation.v1:<owner>` key with payload version 2. Valid version-1 drafts migrate losslessly in memory and write version 2 only after an edit. Selected normalized summaries, source bindings, history filters and unfinished Venue metadata editors are included; complete result pages, raw HTML and authentication data are excluded. A restored preview requires explicit Resume review or Dismiss; it never fetches, applies or submits automatically. After uncertain transport, collection reload reconciles the actual saved records before another deliberate write. See [hybrid-provider verification](../docs/verification/2026-10-25-setlistfm-catalog-form-assistance.md) for browser observations and environment limits.


Artist, Tour, Event and Venue discovery each use one unified search and result list. The single Artist carries forward as context; confirmed entities show compact summaries instead of parallel provider controls. Existing local/source query texts from older recovery snapshots remain available through an explicit Use recovered search choice when they differ; restoring a draft never applies that choice automatically. See the dated hybrid-provider verification report for consolidation regression coverage.
