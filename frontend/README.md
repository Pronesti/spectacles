See [the repository README](../README.md) for setup, verification, and Laravel Cloud deployment.

## Account collection

The dashboard, concert manager, calendar, activity heatmap, Venue map, profile counts, and setlists read the signed-in user's database collection from `GET /api/v1/collection`. New accounts start empty. No demo generator or browser-local workspace is loaded or saved by the app. Old browser preview records are left untouched and are not automatically imported.

Concert creation and edits use `POST /api/v1/attendances` and `PATCH /api/v1/attendances/{uuid}`. These save Events, manual Artists/Venues/Tours, Attendance notes, member flags, and ordered setlist entries in transactions. Confirmed catalog references reuse existing records. Edits detached from an Event reference create separate Event facts without overwriting the shared catalog. Only accepted members can read/edit an Attendance; only its owner can remove it. `DELETE /api/v1/attendances/{uuid}` archives the Attendance so Undo can restore its memberships and other retained data. Saves use Sanctum cookies and CSRF, publish only after confirmation, and retain editors on failures. Requests are never automatically replayed.

Date precision, nullable timezone/Venue, midnight/seconds, zero coordinates, Unicode names and repeated songs remain supported. The map illustration is static; Venue names and locations come from the database, with missing coordinates kept in the directory. The existing concert photograph remains a design asset.

## Account authentication

Registration and sign-in use the Laravel Sanctum cookie session. The app checks `GET /api/v1/me` before showing an account identity. Mutations first request `/sanctum/csrf-cookie`, then send the decoded `XSRF-TOKEN` as `X-XSRF-TOKEN` with browser credentials. There are no bearer tokens or browser-stored account identities. Logout ends the current backend session; a failed logout keeps the visible identity and shows an error.

Your profile saves name, email, and preferred language to Laravel. Email changes apply immediately; use the new address on the next sign-in. The current supported preference is English or the application default. Photo uploads, avatar color, city, bio, verification, and password reset are outside this integration.

Concerts and setlists are account-scoped database records. Signing out or switching accounts does not expose the previous account’s collection. Old preview/profile keys are ignored and left untouched.

Set `VITE_API_BASE_URL` to the backend origin and run the Vite development server for HMR. Frontend and API must share a parent domain for cookie authentication. Local Yerd uses `https://spectacles.test` and `https://api.spectacles.test`; backend stateful domains, allowed frontend origins, session domain, secure cookies, and session storage must match. `GET /api/health` remains a separate public health indicator.

Run `npm run lint`, `npm run build`, and `npm test`. Account protocol tests use fake fetch responses; markup tests use the installed Vite/React server renderer. Real cookie/session, interaction, responsive, and keyboard acceptance is recorded in `design/account/qa/auth-integration-verification.md` after browser testing. The historical image references and visual specification remain in `design/account/`.

## Shared catalog selection

Signed-in users can search existing Events, Artists, Venues, Songs, and Tours from the editors. Choosing an Event fetches fresh details and asks before adoption. Personal flags, notes, and setlists remain in the draft. Every value can also be entered manually. Catalog names are not identity keys: UUIDs distinguish duplicate and comma-containing names.

Tours are optional; their roster does not populate a lineup. Song selection does not identify a performer. The six existing catalog GETs remain authenticated and separate from private collection reads/writes. No external provider lookup or synchronization is connected.
