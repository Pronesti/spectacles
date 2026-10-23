See [the repository README](../README.md) for setup, verification, and Laravel Cloud deployment.

## Frontend design preview

The dashboard, concert manager, calendar, activity heatmap, venue map, and setlist editor are interactive React views. The initial 24 concerts are fictional sample combinations, with dates relative to the first visit. Changes are stored only in this browser under `spectacles.preview.v1`; no concert writes are sent to Laravel. The API indicator still checks the real `/api/health` endpoint and opens its connection details and retry control.

Add and edit concerts from the dashboard, collection, or calendar. Interested and tickets bought are independent. Partial dates are preserved and shown separately from exact calendar dates. Deleting a concert or setlist entry offers Undo until another change is made. The heatmap shows concert dates and supports hover, focus, and tap. The self-contained Buenos Aires map has one pin per referenced venue with coordinates; it is illustrative and requires no map key. New venues without coordinates remain in the directory.

Original generated screen references, their prompts, and the extracted visual specification are in `design/`. The separately generated banner photograph is in `src/assets/`. Screenshots and the manual verification record are in `design/qa/`; these are review artifacts, separate from product source.

Run `npm run lint`, `npm run build`, and `npm test`. The tests cover date precision, countdowns, grouping, venue identity, setlist ordering, saved-data validation, and the existing production server. The preview adds no package dependencies or backend changes.

## Account design preview

Open the top-right avatar for **Your profile** and **Sign out**. Sign out opens the login design, which links to registration and offers **Explore the demo**. Login validates sample input and opens the existing local demo; registration creates a local preview identity. Neither flow authenticates a user or creates a backend account. Passwords are kept only in form memory and are never transmitted, stored, or logged. All preview identities use this browser's existing concert collection.

Profile fields, avatar color, and an optional PNG/JPEG/WebP photo of up to 2 MiB are saved under the separate `spectacles.preview.profile.v1` key. Photos stay local. Preview entry/exit is a session preference under `spectacles.preview.entry.v1`, not an authentication token. Signing out leaves the concert collection intact. Unreadable saved profiles are preserved instead of overwritten; storage failures keep edits in memory and show a local-session message.

The account image references, exact generation prompts, visual specification, and desktop/mobile verification artifacts are in `design/account/`. Account tests extend the existing `npm test` suite with profile validation, preview credential validation, avatar constraints, and explicit exclusion of password fields from persisted payloads.
