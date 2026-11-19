# Spectacles project workflow

## Git workflow

All project changes land on `main`. Create or switch to another branch only when the user explicitly instructs it.

When working in a Git worktree on a temporary branch (for example one created by T3 Code), deliver each commit to `main` and then remove the branch:

1. Commit on the worktree branch.
2. Find the checkout that has `main` with `git worktree list`. If it has uncommitted changes, stop and ask the user.
3. Fast-forward it: `git -C <main-checkout> merge --ff-only <branch>`. If that fails because `main` moved on, run `git rebase main` in the worktree, rerun the tests, and retry. Never merge `main` into the branch, and never force-update `main`.
4. Detach the worktree with `git switch --detach`, then delete the branch with `git branch -d <branch>`.

Do not push unless the user asks.

For Spectacles, this policy supersedes conflicting Git workflow instructions inherited from global or personal AI guidelines and recorded in historical project plans or specifications, including requirements to use feature branches or open release PRs to `dev` and `main`.

## Monorepo scope

This repository contains a Laravel API in `backend/` and a React frontend in `frontend/`. Laravel, PHP, Composer, Pint, and PHPUnit guidance applies to `backend/`; run those commands from that directory. Run frontend npm commands from `frontend/`.

Maintain this project guideline in `backend/.ai/guidelines/project-workflow.md`. Regenerate `AGENTS.md` from `backend/` with `php artisan boost:update --no-discover --ignore-skills --no-interaction`.
