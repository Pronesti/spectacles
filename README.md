# Spectacles

Two independent applications in one Git repository:

- `backend/`: Laravel 13 API, created with the Laravel installer. PHP 8.5 is the tested runtime.
- `frontend/`: React 19 + TypeScript + Vite SPA. Node 24 and npm are required. Express serves compiled files in production.

The browser calls Laravel directly over HTTP. The React account screens use Laravel Sanctum SPA sessions. There is no Inertia or shared frontend runtime.

## Local setup

From the repository root, install the backend:

```sh
cd backend
composer run setup
composer run dev
```

`setup` installs dependencies, creates `.env` when missing, generates the local app key, creates SQLite, and runs the installer’s default migrations. Run it once on a fresh checkout; use `composer install` for subsequent dependency installs to preserve your app key. The development server listens at `http://localhost:8000`.

In another terminal, from the repository root:

```sh
cd frontend
cp .env.example .env
npm ci
npm run dev
```

Open `http://localhost:5173`. The connection card must show **The Laravel API is connected.** The public endpoint is `GET http://localhost:8000/api/health`; Laravel’s framework health endpoint is `/up`. The API has no root HTML page.

The frontend’s `VITE_API_BASE_URL` is the backend URL **without `/api`**. The backend’s `FRONTEND_URLS` is a comma-separated list of exact frontend origins (scheme, host, port; no path or trailing slash). Defaults allow `http://localhost:5173` and `http://localhost:3000`. If you use `127.0.0.1` or another port, update both sides as needed. CORS permits credentialed GET/POST/PATCH/OPTIONS requests from those exact origins, including CSRF headers. The health endpoint remains public and session-free.

The health API does not use a database. Account endpoints require the User identity migration and durable session/cache storage; `.env.example` selects `database` for both. The queue remains `sync`. Existing `.env` files are not overwritten by Composer; update their session/cache settings when enabling accounts.

## Local development with Yerd

Yerd detects the repository because its parent directory is parked. Configure that site to proxy Vite and link the Laravel subdirectory separately (run from the repository root):

```sh
yerd root spectacles frontend
yerd front-controller spectacles off
yerd secure spectacles
yerd link spectacles-api "$PWD/backend"
yerd domain primary spectacles-api api.spectacles.test
yerd secure spectacles-api
yerd use spectacles-api 8.5
yerd proxy add spectacles / http://127.0.0.1:5173
```

Set these values in `backend/.env`:

```dotenv
APP_URL=https://api.spectacles.test
FRONTEND_URLS=https://spectacles.test,http://localhost:5173,http://localhost:3000
```

Set these values in `frontend/.env`:

```dotenv
VITE_API_BASE_URL=https://api.spectacles.test
DEV_SERVER_URL=https://spectacles.test
```

Run `php artisan config:clear` from `backend/`, then `npm run dev` from `frontend/`. Open `https://spectacles.test`; the API is at `https://api.spectacles.test/api/health`. Yerd serves Laravel through PHP-FPM, so `composer run dev` is unnecessary in this mode. Keep Vite running while developing; Yerd’s proxy does not start it automatically. `DEV_SERVER_URL` configures the exact allowed hostname and secure live-reload WebSocket; it is optional for localhost development and is not needed on Cloud.

### Automatic local Vite startup

On this Mac, `~/Library/LaunchAgents/local.spectacles.vite.plist` starts the frontend at login and restarts it if it exits. It runs the installed Vite CLI with Yerd’s Node executable from this checkout, bound to `127.0.0.1:5173`; Yerd continues to provide HTTPS. No terminal needs to stay open. The agent reads `frontend/.env`. It requires this checkout and its installed `node_modules`; update its absolute paths if the project moves. It runs while your macOS user session is active.

Logs: `~/Library/Logs/spectacles/vite.log` and `vite-error.log`.

```sh
# Restart after changing frontend environment or configuration:
launchctl kickstart -k "gui/$(id -u)/local.spectacles.vite"
# Stop and disable for the current login session:
launchctl bootout "gui/$(id -u)/local.spectacles.vite"
# Enable again (when unloaded):
launchctl bootstrap "gui/$(id -u)" "$HOME/Library/LaunchAgents/local.spectacles.vite.plist"
```

To permanently disable automatic startup, boot it out and remove its plist from `~/Library/LaunchAgents/`. While the agent is active, do not start a second Vite process on port 5173.

## Database-backed collection

All collection screens now use the signed-in account's database records via `GET /api/v1/collection`. Concert and setlist changes use authenticated, CSRF-protected `POST /api/v1/attendances`, `PATCH /api/v1/attendances/{uuid}`, and `DELETE /api/v1/attendances/{uuid}`. Deleted Attendances are archived to preserve Undo. Run pending migrations before using the new endpoints. New accounts start empty; existing browser preview records remain untouched and are not imported. See frontend/README.md for details.

## Account API

The backend supports Sanctum cookie/session authentication at `/api/v1/auth/register`, `/api/v1/auth/login`, `/api/v1/auth/logout`, and GET/PATCH `/api/v1/me`. The React account screens use these endpoints for session restoration, registration, login, supported profile changes, and logout.

First GET `/sanctum/csrf-cookie` with credentials included. Send its URL-decoded `XSRF-TOKEN` cookie in `X-XSRF-TOKEN` on POST/PATCH requests, include credentials on subsequent requests, and send `Accept: application/json`. Registration accepts name, email, password, and password_confirmation, returns 201, and signs in immediately. Login returns 200; wrong credentials return generic 422 errors. Logout returns 204. Registration requires 12 characters and at most 72 bytes without NUL characters. Passwords are hashed; no bearer tokens are issued.

GET/PATCH `/api/v1/me` return a private JSON:API `users` resource whose id is the User UUID and whose attributes are name, email, and locale. PATCH accepts only those attributes as ordinary JSON fields. Unsupported preview fields are ignored. Email is normalized to lowercase, must be unique, and changes immediately; changing it clears verification without sending mail. Locale is null or a configured APP_LOCALE/APP_FALLBACK_LOCALE value; both default to en.

Use SESSION_DRIVER=database and CACHE_STORE=database, and run pending migrations on a new backend database. SANCTUM_STATEFUL_DOMAINS lists frontend hosts with ports, without schemes. FRONTEND_URLS remains exact allowed origins. For localhost use the defaults and SESSION_DOMAIN=null. For Yerd use SANCTUM_STATEFUL_DOMAINS=spectacles.test,localhost:5173,localhost:3000, SESSION_DOMAIN=.spectacles.test, and SESSION_SECURE_COOKIE=true with the HTTPS frontend/API URLs.

Production SPA sessions require frontend and API under the same parent domain, shared parent SESSION_DOMAIN, HTTPS secure cookies, and durable shared database/session/cache storage. Separate unrelated Cloud application domains do not satisfy the cookie-domain requirement. Configure custom domains before using this flow; no provisioning or deployment is performed here.

### Shared catalog reads

All catalog reads require a signed-in Sanctum session and `Accept: application/json`. They use the existing `/api/v1` prefix. These GET requests do not mutate data or require CSRF initialization. Success is JSON:API; guests receive 401 and invalid queries receive Laravel JSON validation errors with status 422.

- `GET /api/v1/events`: `filter[q]` matches title or lineup Artist name. Optional `filter[venue_uuid]`, `filter[year]`, `filter[month]`, `filter[day]`, or the complete `filter[provider]`, `filter[resource_type]`, `filter[provider_resource_id]` tuple narrow results. Venue and ordered lineup are included by default; `include=tourLinks.tour` adds Tours, and `include=tourLinks.tour.artistLinks.artist` adds their participating Artists.
- `GET /api/v1/events/{event}`: a Spectacles Event UUID returns the same shared facts. Numeric IDs, malformed UUIDs, and missing Events return 404.
- `GET /api/v1/artists`: `filter[q]` searches name/disambiguation; optional exact `filter[type]` and a complete provider tuple identify existing Artists.
- `GET /api/v1/venues`: `filter[q]` searches name; optional `filter[city]` and two-letter `filter[country_code]` narrow location. Address parts, decimal-string coordinates, and timezone remain nullable.
- `GET /api/v1/songs`: `filter[q]` searches title and `filter[disambiguation]` searches composition context. The schema has no composer/Artist association, and this route does not infer credits from private setlists.
- `GET /api/v1/tours`: `filter[q]` searches name and optional `filter[artist_uuid]` matches participating Artists, which are included by default. The wizard uses `filter[artist_uuids][]` and `filter[artist_mbids][]` to match any selected Artist through existing Tour–Artist links and Artist provider identities. The combined scope accepts at most 100 identifiers; explicitly empty scope returns no unrelated Tours. The legacy single-Artist filter remains available.

Collection queries accept `page` (1–100000) and `per_page` (1–100, default 20), sort by display name/title then UUID, and return native paginator links/meta. Supplied filters combine with AND. Empty text means a paginated browse; text is literal substring search with database LOWER case behavior, not fuzzy matching or accent removal. Month requires year and day requires year/month; date filters match stored components without guessing missing precision.

Attendances, owners, members, companion relationships, and private setlists are excluded, including through nested includes. Unsupported query/include/filter keys and nonempty GET bodies are rejected. Provider tuples on these catalog routes filter existing local references only; playable output, imports, and synchronization remain outside this release. The frontend offers catalog selection into account-backed concerts; collection reads and Attendance writes are documented in frontend/README.md. Authenticated MusicBrainz Artist/Work/Tour suggestion reads are available when explicitly configured (see below).

## Verify

```sh
cd backend
composer validate --strict
composer test
vendor/bin/pint --format agent
```

In `frontend/`:

```sh
npm run lint
npm run build
npm test
npm start
```

The build requires `VITE_API_BASE_URL` from `.env` or the process environment and embeds it in browser code. It must be public and must never contain secrets. Changing it requires rebuilding the SPA. `npm test` checks the compiled app, deep-link fallback, asset serving, and missing-asset responses. `npm start` serves `dist/` on `PORT` (default 3000) and handles shutdown signals. Visit `http://localhost:3000` to verify the production server against the local API. Vite preview is only a local preview tool.

## Laravel Cloud: two applications, one repository

After the repository has been committed and pushed to a supported Git provider, create **two separate Cloud applications** referencing the same repository and release branch. Choose their application roots during monorepo setup. No Dockerfile or root workspace package is required.

| Setting | Backend application | Frontend application |
| --- | --- | --- |
| Application root | `backend` | `frontend` |
| Runtime | PHP 8.5 / Laravel | Node.js 24 / Express |
| Build | `composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction` | `npm ci --include=dev` followed by `npm run build` |
| Deploy commands | `php artisan optimize` | None |
| Start | Cloud-managed Laravel web process; document root `public/` | `npm start` |
| Health path | `/up` | `/` |

Set the commands explicitly. The backend has no npm build. The frontend build needs dev dependencies for TypeScript and Vite even though Cloud sets `NODE_ENV=production`; `--include=dev` ensures they are installed. Keep `frontend/package-lock.json` in Git for Node runtime detection. Select Node 24 in Cloud to match `engines` and the tested local version.

1. Create both applications to obtain their public HTTPS domains. Deploy the backend first, then the frontend. They are independently deployed and scaled; use compatible API changes between releases.
2. In the **backend** environment, configure:

   ```dotenv
   APP_NAME="Spectacles API"
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=<unique generated Laravel key>
   APP_URL=https://your-backend-domain
   FRONTEND_URLS=https://your-frontend-domain
   LOG_CHANNEL=stderr
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=sync
   ```

   Retain Cloud’s generated `APP_KEY` if supplied; otherwise generate one with `php artisan key:generate --show` and save it as a backend secret. Keep that key stable across deployments. Do not copy the local `.env` to Cloud.
3. In the **frontend** environment, set `VITE_API_BASE_URL=https://your-backend-domain` **before building**. Set `NODE_ENV=production`. The server reads Cloud’s `PORT` and listens without an explicit host, allowing its IPv6 startup probes. Cloud terminates HTTPS.
4. Deploy and open the frontend. Confirm the connection card succeeds; refresh a nested URL such as `/projects/example` to check SPA fallback. Missing asset URLs must return 404. Confirm `/api/health` returns JSON from the backend and permits only the configured frontend origins in browsers.
5. When adding custom domains, update `APP_URL`, `FRONTEND_URLS`, and `VITE_API_BASE_URL` as appropriate, then redeploy both apps so cached configuration and frontend build values update.

A health-only deployment can explicitly use array session/cache stores without a database. Account endpoints require managed PostgreSQL, its injected connection variables, and `php artisan migrate --force` in backend deploy commands. Keep database session/cache storage durable and shared across backend instances. **Do not use local SQLite for production data:** the application filesystem is ephemeral. Configure the matching SPA/API custom domains, `SANCTUM_STATEFUL_DOMAINS`, shared `SESSION_DOMAIN`, and `SESSION_SECURE_COOKIE=true` before enabling browser authentication. This application does not issue bearer tokens as an alternative to that domain configuration. Cloud application provisioning and deployments incur their configured infrastructure costs and are separate from this local setup.

Official guidance: [monorepos](https://laravel.com/cloud/docs/monorepos), [Node.js deployment](https://laravel.com/cloud/docs/deploy-guides/nodejs), [runtimes](https://laravel.com/cloud/docs/runtimes), [Laravel deployment](https://laravel.com/cloud/deploy-a-laravel-app).

## Git workflow

Use `main` as the default branch. Start feature branches from `main`; open release PRs to `dev` and `main` from the same feature branch. Do not merge or rebase `dev` into a feature branch.

### Frontend account integration

The React account screens use the five Sanctum routes documented above. Session restoration, registration, login, name/email/language changes, and logout use cookie authentication. The frontend does not store account credentials or identity in browser storage. Frontend and API must share a parent domain, with credentialed CORS and Sanctum/session settings matching the frontend origin.

The concert workspace is now account-scoped and database-backed; dashboard, concerts, calendar, map and setlists use the collection API. See [frontend account behavior](frontend/README.md#account-authentication) and the [frontend integration plan](docs/superpowers/plans/2026-10-23-sanctum-frontend-integration.md).

### Optional MusicBrainz suggestions

Creation uses an Artist → Tour → Event → Setlist wizard. Its draft remains in memory until the final Save concert. Local catalogs and manual entry work without external configuration. Existing concert editing and immediate setlist editing remain available.

Set `MUSICBRAINZ_ENABLED=true` and `MUSICBRAINZ_CONTACT` in the backend environment to enable suggestions. The contact is a real operator email or public URL and generates the identifying `Spectacles/1.0 (contact)` User-Agent. `MUSICBRAINZ_USER_AGENT` is an optional full override; a blank override uses the contact setting. Defaults are disabled and blank; there is no API key. Keep personal contact details in the ignored backend `.env`, and do not enable with invented contact details.

`GET /api/v1/musicbrainz/artists?q=...` and `/api/v1/musicbrainz/works?q=...` require a signed-in session and a 2–200 character query. The Tour endpoint `/api/v1/musicbrainz/tours?q=...` accepts 3–200 characters plus `artist_uuids[]` and/or `artist_mbids[]` scope, with at most 100 selected identifiers. Persisted Artist provider IDs resolve server-side; pending confirmed Artist MBIDs are public lookup parameters. Tour ownership comes from explicit MusicBrainz Artist–Tour relationships, whose target Series must have type Tour. Results match any selected Artist and the entered Tour name; missing identities never enable global fallback. Selected Tour identity uses `musicbrainz/series` and requires confirmation before final save. No Tour roster, Events, or new Tour–Artist links are imported. Work identity never identifies a performer. Events and Venues use local/manual selection; Tours remain optional and support multiple choices.

Entity search controls wait for at least three trimmed Unicode characters, search the database first, and consult MusicBrainz only after a successful zero-match response. Empty focus browses up to five local choices without provider calls or pagination; short input makes no searches. Tour browsing is scoped to selected Artists. Manual entry stays available, and source/disambiguation context appears within results without separate source-heading rows. Existing local-only edit controls retain their provider scope.

All deployed workers/instances must use the same durable cache and atomic lock store (the existing database cache supports this). Public normalized results are cached for 15 minutes; outbound requests share a gate of at least 1,100 ms between starts. Tour relationships cache per Artist independently of typed queries. A request fetches at most one missing Artist relationship set; additional cold Artists produce recoverable 429 responses while explicit retries warm the remaining cache, without partial/global results. Missing configuration, busy upstreams, and failures leave manual entry usable. Selection proofs expire after 24 hours; reselect an expired match or explicitly recreate it manually. Pending catalog maps travel only beside the final Attendance request and are resolved in its transaction. Existing shared identities are reused without changing saved facts.
