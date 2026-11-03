# Spectacles

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Concertgoers aged 30 and above who want a personal journal of the shows they attend.

## Product Purpose

Help people record shows, preserve setlists and memories, and revisit their attendance history. Success means users can maintain a trustworthy concert journal and easily find past experiences.

## Operating Context

Users maintain an account-specific concert collection, record attendance, and review their history through concert lists, a calendar, a map, and setlists. Concert creation follows Artist → optional Tour → Event → Setlist. Shared catalog searches and optional external provider suggestions assist entry; manual entry remains available.

## Capabilities and Constraints

- Preserve accurate concert facts; unknown facts must remain unknown rather than be guessed.
- Require explicit confirmation before saves. Provider suggestions must not silently replace confirmed facts.
- Keep manual entry usable when providers fail or return no useful result.
- Preserve unfinished drafts through dismissal, reloads, and failed saves. Do not automatically replay uncertain writes.
- Keep collections scoped to the signed-in account. Shared catalog facts and personal attendance details have different ownership.
- Favor a light-content UI: concise content and low information density. This interprets the user's wording; it does not establish a light color theme.

## Brand Commitments

Preserve the name Spectacles and its purpose as a personal concert journal.

## Evidence on Hand

- `README.md` and `frontend/README.md` document current capabilities and workflow constraints.
- `frontend/src/` contains the existing React interface and its copy.
- `design/account/` contains historical design references and verification material; validate freshness before treating these as current visual authority.
- `docs/verification/` contains dated workflow checks, not guarantees about current deployments.

## Product Principles

1. Preserve concert facts and the user's memories faithfully.
2. Keep the user in control of selections and saves.
3. Make recording a concert possible without external providers.
4. Protect unfinished work and keep recovery understandable.
5. Keep content concise and everyday tasks easy to scan.

## Open Decisions

Distinctive competitive positioning, product-specific accessibility standards, language priorities, and mobile-use priorities have not been established. Do not invent commitments in these areas.
