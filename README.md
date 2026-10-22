# Spectacles

Two independent applications in one Git repository:

- `backend/`: Laravel 13 API, created with the Laravel installer. PHP 8.5 is the tested runtime.
- `frontend/`: React 19 + TypeScript + Vite SPA. Node 24 and npm are required. Express serves compiled files in production.

The browser calls Laravel directly over HTTP. Authentication is deferred. There is no Inertia or shared frontend runtime.

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

The frontend’s `VITE_API_BASE_URL` is the backend URL **without `/api`**. The backend’s `FRONTEND_URLS` is a comma-separated list of exact frontend origins (scheme, host, port; no path or trailing slash). Defaults allow `http://localhost:5173` and `http://localhost:3000`. If you use `127.0.0.1` or another port, update both sides as needed. CORS is credential-free and currently permits only GET/OPTIONS and Accept/Content-Type headers; extend it when adding write endpoints.

The scaffold keeps the installer’s default model and migrations but the health API does not use a database. Sessions/cache use non-persistent `array` drivers and the queue uses `sync`; configure durable services when future features require them.

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
   SESSION_DRIVER=array
   CACHE_STORE=array
   QUEUE_CONNECTION=sync
   ```

   Retain Cloud’s generated `APP_KEY` if supplied; otherwise generate one with `php artisan key:generate --show` and save it as a backend secret. Keep that key stable across deployments. Do not copy the local `.env` to Cloud.
3. In the **frontend** environment, set `VITE_API_BASE_URL=https://your-backend-domain` **before building**. Set `NODE_ENV=production`. The server reads Cloud’s `PORT` and listens without an explicit host, allowing its IPv6 startup probes. Cloud terminates HTTPS.
4. Deploy and open the frontend. Confirm the connection card succeeds; refresh a nested URL such as `/projects/example` to check SPA fallback. Missing asset URLs must return 404. Confirm `/api/health` returns JSON from the backend and permits only the configured frontend origins in browsers.
5. When adding custom domains, update `APP_URL`, `FRONTEND_URLS`, and `VITE_API_BASE_URL` as appropriate, then redeploy both apps so cached configuration and frontend build values update.

No database resource or migrations are needed for the health-only Cloud deployment. **Do not use local SQLite for production data:** the application filesystem is ephemeral. Before adding persistent features, select and attach managed Postgres or MySQL, accept its injected connection variables, and add `php artisan migrate --force` to the backend deploy commands. Choose persistent session/cache/queue services when those features are introduced. Cloud application provisioning and deployments incur their configured infrastructure costs and are separate from this local setup.

Official guidance: [monorepos](https://laravel.com/cloud/docs/monorepos), [Node.js deployment](https://laravel.com/cloud/docs/deploy-guides/nodejs), [runtimes](https://laravel.com/cloud/docs/runtimes), [Laravel deployment](https://laravel.com/cloud/deploy-a-laravel-app).

## Git workflow

Use `main` as the default branch. Start feature branches from `main`; open release PRs to `dev` and `main` from the same feature branch. Do not merge or rebase `dev` into a feature branch.
