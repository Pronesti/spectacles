See [the repository README](../README.md) for setup, verification, and Laravel Cloud deployment.

## Frontend design preview

The dashboard, concert manager, calendar, activity heatmap, venue map, and setlist editor are interactive React views. The initial 24 concerts are fictional sample combinations, with dates relative to the first visit. Changes are stored only in this browser under `spectacles.preview.v1`; no concert writes are sent to Laravel. The API indicator still checks the real `/api/health` endpoint and opens its connection details and retry control.

Add and edit concerts from the dashboard, collection, or calendar. Interested and tickets bought are independent. Partial dates are preserved and shown separately from exact calendar dates. Deleting a concert or setlist entry offers Undo until another change is made. The heatmap shows concert dates and supports hover, focus, and tap. The self-contained Buenos Aires map has one pin per referenced venue with coordinates; it is illustrative and requires no map key. New venues without coordinates remain in the directory.

Original generated screen references, their prompts, and the extracted visual specification are in `design/`. The separately generated banner photograph is in `src/assets/`. Screenshots and the manual verification record are in `design/qa/`; these are review artifacts, separate from product source.

Run `npm run lint`, `npm run build`, and `npm test`. The tests cover date precision, countdowns, grouping, venue identity, setlist ordering, saved-data validation, and the existing production server. The preview adds no package dependencies or backend changes.

## Account authentication

Registration and sign-in use the Laravel Sanctum cookie session. The app checks `GET /api/v1/me` before showing an account identity. Mutations first request `/sanctum/csrf-cookie`, then send the decoded `XSRF-TOKEN` as `X-XSRF-TOKEN` with browser credentials. There are no bearer tokens or browser-stored account identities. Logout ends the current backend session; a failed logout keeps the visible identity and shows an error.

Your profile saves name, email, and preferred language to Laravel. Email changes apply immediately; use the new address on the next sign-in. The current supported preference is English or the application default. Photo uploads, avatar color, city, bio, verification, and password reset are outside this integration.

Concerts remain fictional browser-local demo data under `spectacles.preview.v1`, shared across sign-ins. Account actions leave that collection intact. The old local profile and entry keys are ignored and left untouched. Explore the demo allows anonymous use without claiming an authenticated session.

Set `VITE_API_BASE_URL` to the backend origin and run the Vite development server for HMR. Frontend and API must share a parent domain for cookie authentication. Local Yerd uses `https://spectacles.test` and `https://api.spectacles.test`; backend stateful domains, allowed frontend origins, session domain, secure cookies, and session storage must match. `GET /api/health` remains a separate public health indicator.

Run `npm run lint`, `npm run build`, and `npm test`. Account protocol tests use fake fetch responses; markup tests use the installed Vite/React server renderer. Real cookie/session, interaction, responsive, and keyboard acceptance is recorded in `design/account/qa/auth-integration-verification.md` after browser testing. The historical image references and visual specification remain in `design/account/`.
