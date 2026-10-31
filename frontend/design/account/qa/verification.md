# Account preview verification

Verified locally on 2026-10-22 at `https://spectacles.test`. Four fresh generated references were inspected before implementation. Their original images and exact prompts remain in this account design folder; all screenshots below are rendered app evidence, not the generated mockups.

## Checks performed

- `npm run lint`: passed without warnings.
- `npm run build`: TypeScript and Vite production build passed.
- `npm test`: all 18 checks passed, including the existing 12 checks and six new account checks.
- `git diff --check`: passed.
- Browser console: no errors recorded during the account flow checks.

The new automated checks cover separate profile/workspace keys; sanitized profile payloads that exclude password/confirmation/foreign fields; names, email and bio limits; preview password length and confirmation; Unicode initials; permitted image types/size; malformed saved profiles; and rejection of external image URLs, SVG data, and unsupported versions.

## Manual browser evidence

1. Opened the avatar menu with ArrowDown. Focus moved to Your profile; ArrowDown moved to Sign out. Escape closed the menu and returned focus to the avatar trigger. Tab and outside click also closed the menu.
2. Opened Profile, changed name/email/city/bio and avatar color, saved, and reloaded. The menu identity, avatar, and form values retained the saved changes.
3. Selected an SVG through Change photo and confirmed its format error. Selected the existing local concert PNG, confirmed it decoded, saved it, and reloaded. The avatar image persisted in both the trigger/menu and profile. Use initials removed it and saved successfully. No file was sent to a service.
4. Tried saving a blank profile name; the inline error and focus response worked. Cancel discarded the unsaved edit and retained the saved identity.
5. Signed out to Login. Empty form submission produced email/password format errors. Show/Hide password changed the input visibility. Submitting sample values opened the existing local preview profile with all 24 concerts, 17 artists, six venues, and 42 saved songs intact.
6. Reloaded after sign-out and remained on Login. Switched to Register, verified empty-field validation and mismatched-password feedback, then created the Jamie Morgan sample preview identity with matching sample values. Password values were not passed to profile persistence or entry callbacks. Registration retained the concert collection.
7. Checked the existing calendar month controls, six unique map pins, setlist view, and real API connection details after the account transitions. The API reported connected.
8. Compared the 1280 × 900 login, register, profile, and menu views with their corresponding generated references. Kept the split photo/form auth composition, larger auth typography, inset photography, header/menu anchoring, open profile fields, and lavender summary aligned alongside the profile heading.
9. Reviewed mobile login, registration, profile, and the avatar menu at 390 × 844. The photo panel gives way to a single-column auth form; the profile and summary stack; the menu remains within the viewport. No whole-page horizontal overflow appeared. Registration and the dashboard also fit a 360 × 780 viewport.

The review corrected helper/counter text being included in field names and prevented programmatic navigation focus from scrolling the header away. Menu screenshots were captured after the short entrance animation had settled.

Temporary Alex Rivera profile edits and the uploaded test image were replaced with the default Jamie Morgan sample identity, initials, and lime color. No concert records were added, deleted, or changed in these account checks.

## Scope and limits

This remains a frontend design preview. Login does not verify credentials; registration creates only one browser-local preview profile. All preview identities share the existing local concert collection. Passwords are not stored, transmitted, or logged. There is no backend account/session API, protected-route enforcement, password recovery, account deletion, or provider login. Profile persistence has its own versioned key; entry/exit uses a session-only screen preference.

Avatar type/size checks and a real successful local photo selection were verified. Storage exhaustion and unreadable-storage recovery were covered by payload validation/source review rather than injected browser failures. No backend changes, new dependencies, migrations, external uploads, paid calls, commits, pushes, PRs, or deployment were performed.
