# Frontend account integration verification

Executed 2026-10-23. Product changes remain uncommitted on `feat/live-event-data-layer`, based on `6ea9030` (existing backend endpoints). Existing setup changes and design/planning files were preserved.

## Environment and method

- Node v24.21.0, npm with `/opt/homebrew/opt/node@24/bin` first in PATH.
- PHP commands used the current machine profile's plain `php` prefix. Runtime instructions were read before startup. No backend source was changed or backend suite rerun.
- Browser: ChatGPT in-app browser, version not exposed by the automation surface. Desktop width 1280; mobile width 390. Temporary viewport overrides were reset.
- UI: `http://localhost:5179`; API: `http://localhost:8097`.
- Disposable SQLite: `/var/folders/m8/sdwc4pyj44g6rzmvg50yxqzw0000gn/T//spectacles-account-qa.4OYuxE/database.sqlite` (retained for evidence). No shared database or private dotenv changes.
- Laravel initially served 8097 directly. For browser fault checks, an ephemeral Node HTTP proxy served 8097 and forwarded to this same disposable Laravel environment on 8098. It logged methods, paths, status, payload **keys**, and boolean header checks only. The proxy never recorded cookie/header values, passwords, or request body values.
- The proxy supplied controlled 419/503/HTML responses and 1.5-second delays. Disconnection was tested by stopping only this proxy (connection refusal), rather than changing the browser's global network state.
- Fixture accounts: `frontend-qa@example.com` and `frontend-second@example.com`. After account-switching and duplicate-email checks, the disposable second user was removed from SQLite to free its address; the first user then changed to that address and successfully signed in with it. Session-expiry testing deleted only this disposable database's sessions.
- A temporary, UI-operated QA page seeded the legacy storage fixtures, displayed a workspace SHA-256 hash, and removed the temporary XSRF cookie. This page was removed from `frontend/public` after testing.
- Browser tabs were closed, and all three temporary services were stopped after testing. Existing services were not stopped.

## Automated gates

All commands ran in `frontend` with Node 24 and exited 0:

| Command | Result |
| --- | --- |
| `node --test tests/account.test.mjs tests/account-contract.test.mjs tests/account-api.test.mjs` | 14 passed, 0 failed |
| `npm run lint` | Passed, including final run |
| `npm run build` | TypeScript and Vite passed, including final run |
| `npm test` | 26 passed, 0 failed, including final run after build |
| `git diff --check` (repository root) | Passed |

Source inspection found no old profile/entry key references in product source, and no storage, bearer header, or logging in the new account client/hook/forms. `domain.ts`, backend source, package manifests, and lockfiles were unchanged. App concert operations were preserved.

## Browser acceptance

The following results describe actual actions, including equivalent fault-injection methods where noted. Sanitized HTTP evidence is in [requests-sanitized.jsonl](requests-sanitized.jsonl). Initial clean entry and the first invalid login preceded proxy logging; their browser UI and direct Laravel terminal requests were observed.

| Plan row | Observed result | Result |
| --- | --- | --- |
| 1 | Initial checking gate, then guest login after anonymous `/me` 401; no fabricated identity. Independent health calls returned 200. | Pass |
| 2 | Missing-user login stayed on form with generic Laravel credential error. Mismatched confirmation focused confirmation; 37 accented characters focused password with byte-limit error. Invalid registration made no POST. | Pass |
| 3 | Registration returned 201 after CSRF 204 and entered dashboard with returned fixture name/email, signed-in menu, and shared-demo notice. | Pass |
| 4 | Reload showed checking before `/me` 200 restored identity. A second tab with the same cookies also restored it. Initial unauthenticated entry and later session invalidation returned guest UI. Storage inspection contained only workspace and deliberately seeded legacy keys. | Pass |
| 5 | Each recorded mutation followed CSRF initialization and carried a CSRF header, without bearer auth. Later instrumentation confirmed decoded header equals request cookie (all 12 measured comparisons true). Deleting the XSRF cookie followed by logout succeeded after fresh initialization. CSRF 503 displayed recoverable error and sent no login POST. Browser accepted credentialed cross-origin session responses. | Pass |
| 6 | With proxy stopped, save retained `Offline draft` and the confirmed menu identity. Reconnecting and saving returned 200; only then did identity change. Separate injected 503 also preserved the draft. | Pass |
| 7 | Repeated invalid login reached real 429 and showed wait/retry guidance. Later normal login returned 200 after recovery. Duplicate fixture email PATCH returned 422, focused email, and retained editable draft. No automatic mutation replay. | Pass |
| 8 | Connection refusal retained identity with error. Normal logout returned 204; reload returned guest login. Deleting disposable sessions before logout produced 401 and cleared local identity. Injected logout 419 retained identity and asked for deliberate retry; normal retry succeeded. | Pass |
| 9 | Delayed double-click registration, save, login, and logout each produced exactly one mutation in proxy logs. Pending credential form disabled inputs, visibility, switch, brand, and Explore. Repeated reload during delayed startup showed checking before final confirmed identity; no stale identity or StrictMode error appeared. | Pass |
| 10 | PATCH logs contained only changed name, email, or locale. Canonical lowercase email appeared after save/reload; after freeing the second fixture address, an actual email change persisted and authenticated at next login. English and null default each survived reload. No unsupported editors were present. | Pass |
| 11 | Name/language edits followed by Cancel sent no mutation; reopening restored confirmed name and default locale. Failed edits remained visible and focused the associated field/alert. | Pass |
| 12 | Legacy profile bytes `{"name":"Fake preview","email":"fake@example.com"}` and entry bytes `register` were unchanged and did not control authentication. Workspace hash before and after sign-in, save, logout, account switching, and reload remained `b968a667a912619975cf77001aa669f2eadf6d8d4ff52f3d45909de1d477cb2a`. Existing corrupt/unknown workspace tests passed. | Pass |
| 13 | Second registration showed only Second QA; signing into first restored only its confirmed identity. Same 24 demo concerts and shared-sign-in notice remained. All observed account writes targeted only account routes. | Pass |
| 14 | Desktop/mobile screenshots preserve design/photo/colors. Mobile login, registration, and profile measured 390px document width at 390px viewport. Tab order and autofocus verified in credential forms; password visibility toggled text/password. Menu ArrowUp/Down, Home/End, Escape with focus return, outside click, and focus leaving were exercised; guest actions and profile Cancel/reopen were verified. Pending fieldsets disable controls; errors use associated descriptions and alert roles. | Pass |
| 15 | Injected 200 HTML `/me` showed unavailable Retry/Explore; 200 HTML logout retained identity with confirmation error. Profile 503 retained draft; 419 sent one mutation and requested deliberate retry without debug content. An 11-second delayed `/me` produced the client's 10-second timeout gate; Explore then opened guest demo. | Pass |

One initial proxy fault experiment also intercepted an OPTIONS response, causing a network error; this was corrected to pass OPTIONS through before the HTML logout assertion. Both attempts are retained in sanitized evidence. Some browser automation waits have a short tool deadline; after these waits returned early, subsequent observations confirmed the final UI rather than treating the tool timeout as an application result.

## Screenshots

New `.jpg` artifacts cover login, registration, profile, signed-in menu, guest menu, and failure state at both widths. Existing `.png` visual-review artifacts were left intact.

- [Desktop profile](profile-desktop.jpg), [mobile profile](profile-mobile.jpg)
- [Desktop login](login-desktop.jpg), [mobile login](login-mobile.jpg)
- [Desktop registration](registration-desktop.jpg), [mobile registration](registration-mobile.jpg)
- [Desktop signed-in menu](signed-in-menu-desktop.jpg), [mobile signed-in menu](signed-in-menu-mobile.jpg)
- [Desktop guest menu](guest-menu-desktop.jpg), [mobile guest menu](guest-menu-mobile.jpg)
- [Desktop failure](failure-desktop.jpg), [mobile failure](failure-mobile.jpg)

## Limits

No observed functional acceptance failure remains. These checks cover the local disposable environment and focused keyboard interactions, not a screen-reader audit, every possible event interleaving, or deployed HTTPS/subdomain configuration. No commit, push, PR, merge, or deployment was performed.
