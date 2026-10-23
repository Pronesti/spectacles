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

## Account API

The backend supports Sanctum cookie/session authentication at `/api/v1/auth/register`, `/api/v1/auth/login`, `/api/v1/auth/logout`, and GET/PATCH `/api/v1/me`. The React account screens use these endpoints for session restoration, registration, login, supported profile changes, and logout.

First GET `/sanctum/csrf-cookie` with credentials included. Send its URL-decoded `XSRF-TOKEN` cookie in `X-XSRF-TOKEN` on POST/PATCH requests, include credentials on subsequent requests, and send `Accept: application/json`. Registration accepts name, email, password, and password_confirmation, returns 201, and signs in immediately. Login returns 200; wrong credentials return generic 422 errors. Logout returns 204. Registration requires 12 characters and at most 72 bytes without NUL characters. Passwords are hashed; no bearer tokens are issued.

GET/PATCH `/api/v1/me` return a private JSON:API `users` resource whose id is the User UUID and whose attributes are name, email, and locale. PATCH accepts only those attributes as ordinary JSON fields. Unsupported preview fields are ignored. Email is normalized to lowercase, must be unique, and changes immediately; changing it clears verification without sending mail. Locale is null or a configured APP_LOCALE/APP_FALLBACK_LOCALE value; both default to en.

Use SESSION_DRIVER=database and CACHE_STORE=database, and run pending migrations on a new backend database. SANCTUM_STATEFUL_DOMAINS lists frontend hosts with ports, without schemes. FRONTEND_URLS remains exact allowed origins. For localhost use the defaults and SESSION_DOMAIN=null. For Yerd use SANCTUM_STATEFUL_DOMAINS=spectacles.test,localhost:5173,localhost:3000, SESSION_DOMAIN=.spectacles.test, and SESSION_SECURE_COOKIE=true with the HTTPS frontend/API URLs.

Production SPA sessions require frontend and API under the same parent domain, shared parent SESSION_DOMAIN, HTTPS secure cookies, and durable shared database/session/cache storage. Separate unrelated Cloud application domains do not satisfy the cookie-domain requirement. Configure custom domains before using this flow; no provisioning or deployment is performed here.

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

The concert workspace remains a browser-local demo, shared across sign-ins; account authentication does not add server concert persistence. See [frontend account behavior](frontend/README.md#account-authentication) and the [frontend integration plan](docs/superpowers/plans/2026-10-23-sanctum-frontend-integration.md).
