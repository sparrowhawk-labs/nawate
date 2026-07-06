# Handover — 2026-07-07 00:00
Scale: large

## Goal
`nawate` v0.1 is now a shipped OSS package (Sparrowhawk Labs), not just a finished local project. North star: CLAUDE.md.
"Done" for this arc: GitHub + Packagist live, auto-update hook working, all tests green, no uncommitted/unpushed work. → **Already true as of this save.**

## Next
No pending task from the user. Candidates if resuming without new direction:
1. **Cut a semver git tag** (e.g. `v0.1.0`) — Packagist currently only shows `dev-main`; a host app pinning a version constraint (`^0.1`) has nothing to resolve to yet.
2. Decide whether `~/project/nawate-demo-app` should get a GitHub remote (currently local-only) or stay a throwaway local harness.
3. Optional Demo 6 (video integration, `PLAN.md` Phase 5) was explicitly left undone — still available if wanted, not blocking anything.

## Gotchas
- **Packagist GitHub Hook Sync silently failed at first** with "the `sparrowhawk-labs` organization has enabled OAuth App access restrictions" — this blocked auto-update for `nawate` *and* pre-existing `pinion-ui`/`pinion-icons`. Fix requires the org owner (`akihiko-takai`) to approve the Packagist OAuth App at `https://github.com/organizations/sparrowhawk-labs/settings/oauth_application_policy`, then re-trigger via `https://packagist.org/trigger-github-sync/` (the profile page's cached "retry hook sync" link can show a stale timestamp — navigate the trigger URL directly to force a fresh run). This is an access-control change, so I (the agent) cannot perform it — user must click "Approve" themselves.
- **Packagist login**: the `akihiko.takai@yakaze.com` GitHub identity had a *pre-existing* Packagist account not yet linked to GitHub OAuth — first login attempt required username/password sign-in (Profile → Settings → connect GitHub), not the "Log in with GitHub" button directly. If publishing another package under this account from a fresh browser, expect the same detour.
- `composer validate` flags a `"version"` field in `composer.json` as a warning once a package is meant for Packagist (version is derived from git tags there) — already removed from `nawate`'s `composer.json`.
- `FragmentExecutionException`'s "MySQL/PostgreSQL-specific SQL feature" hint only fires on SQLite's exact `no such function: X` error signature — deliberately not on generic "syntax error" (too many false positives against ordinary typos). Don't broaden the pattern match without re-reading `src/Exceptions/FragmentExecutionException.php`'s doc comment for why.

## State / Files
- Working tree clean, `origin/main` up to date (`ff3787d`). Nothing uncommitted, nothing unpushed.
- `~/project/sparrowhawk-labs/pinion-ui` also got a related fix this session (AGENTS.md/`@AGENTS.md` mechanism, to match nawate's) — committed and pushed to its own `feat/v0.5-css` branch (`d7ee0f2`), a separate repo/session concern, not part of nawate's own history.
