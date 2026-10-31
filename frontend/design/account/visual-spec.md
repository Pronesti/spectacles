# Account experience visual specification

Four new references generated on 2026-10-22 with the built-in image tool, using the implemented dashboard as the style reference. Original images are in `references/`, and full prompts are in `image-prompts.json`. The existing separate concert photograph supplies the actual UI media; no interface text is baked into the photograph.

## Inspected references

**Login.** The screen removes the sidebar and divides into an ivory form area and a tall, inset dark concert photograph. The soundmark/wordmark anchors the top left; “New here? Create an account” sits opposite it. “Welcome back.” is the primary focal point, followed by “Your next great memory is waiting.” The two labelled email/password fields have thin sage outlines, approximately 48px height, and a visibility icon. A full-width dark Sign in action precedes a thin divided “or take a look around” line and an outlined Explore the demo action. The photo uses bright lime stage lights above dark crowd silhouettes, with large white “Be there. Keep it forever.” at its lower edge. Preserve the open framing, generous vertical space, and visible preview note.

**Register.** This belongs to the same split-screen family. “Make it your own.” and “Keep the dates, the places, the songs.” replace the login headline/copy. The form contains Your name, Email, Password, and Confirm password. Preserve the clear switch back to Sign in, the Create preview profile primary action, and the one-time local-profile explanation. The right photo headline reads “Every night has a story.”, followed by “Start keeping yours.” The generated image bleeds the photograph to the edges; use the same inset frame as login to preserve a coherent component family.

**Profile.** The app sidebar and topbar remain. Main heading “A little more you.” and “The person behind the memories.” lead to an open identity/avatar row: lime initial, name/email, Change photo, format/size hint, four color swatches, and Use initials. A thin rule separates this from Display name, Email, Home city, and A few words about you. Name/email share a row on desktop. The bio has a real 80-character counter. Cancel and Save changes sit at the form's lower right. The separate lavender summary reads “Your story, so far.” and “A collection only you could make.” Use live collection counts rather than copying generated numbers or incorrectly implying past attendance.

**Avatar menu.** A fresh dedicated reference shows a circular avatar with a focus ring and a right-aligned floating panel below it. The identity block includes an initial, full name, email, and “Local preview profile.” Thin dividers separate it from the spacious Your profile and Sign out rows and the footer “Concert data stays in this browser.” Your profile has a pale lime focus/hover state. Preserve restrained shadow and border, rather than adding settings, billing, providers, or organization controls.

## Translation rules

- Preserve the existing paper `#f7f7f2`, ink `#1d211e`, lime `#d5f76a`, lavender `#e9e2f7`, olive muted text, and sage divider tokens. Avatar colors add a quiet sand tone alongside the existing colors.
- Use bold, tight-tracked grotesk headings around 44–52px on a laptop, 32–38px on mobile; body and fields 13–16px. Keep essential controls at least 44px tall. Photo type is a large secondary display moment, not another competing form heading.
- Auth frames use roughly equal columns, 24px outer photo inset, 40–60px form-side gutters, and a readable form width. At smaller widths the photo gives way to a single-column form; all four registration fields and the actions must remain reachable without horizontal scrolling.
- Profile uses approximately 2:1 form/summary columns with 30–40px gaps, flat fields, and one lavender framing move. Stack on tablet/mobile; allow the avatar/color controls to wrap without clipping.
- Menu is about 300px wide on desktop, constrained to the viewport on mobile, with 14–16px primary row text. Keyboard controls include opening, item navigation, Escape with focus return, and closing on outside click or focus leaving the menu.
- Retain the same four motifs: vertical rhythm rules, editorial offset composition, controlled photographic frames, flat product panels. Motion remains subtle entrance and disclosure, respecting reduced motion.

## Preview boundaries

No real authentication, account creation, verification, password reset, or backend profile API is introduced. Password values live only in form memory and are never sent, persisted, or logged. Login opens the existing local demo profile after format validation; registration edits that local preview identity. All preview identities use the same existing browser-local concert collection. Profile data uses its own versioned key, and entry/exit state is only a session preview preference. Photo selection stays local, supports PNG/JPEG/WebP up to 2 MiB, and can be replaced with initials. There are no provider calls, uploads to a service, or dependency changes.
