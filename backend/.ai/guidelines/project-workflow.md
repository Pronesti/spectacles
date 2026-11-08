# Spectacles project workflow

## Git workflow

Work directly on `main` for all project changes. Create or switch to another branch only when the user explicitly instructs it.

For Spectacles, this policy supersedes conflicting Git workflow instructions inherited from global or personal AI guidelines and recorded in historical project plans or specifications, including requirements to use feature branches or open release PRs to `dev` and `main`.

## Monorepo scope

This repository contains a Laravel API in `backend/` and a React frontend in `frontend/`. Laravel, PHP, Composer, Pint, and PHPUnit guidance applies to `backend/`; run those commands from that directory. Run frontend npm commands from `frontend/`.

Maintain this project guideline in `backend/.ai/guidelines/project-workflow.md`. Regenerate `AGENTS.md` from `backend/` with `php artisan boost:update --no-discover --ignore-skills --no-interaction`.
