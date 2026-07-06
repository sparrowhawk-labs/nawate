# nawate

Laravel package: signed-URL state switching for manual demo/verification (per-session SQLite isolation, zero host-app pollution). Composer path-repository package, same shape as `~/project/engawa-dev`.

- Development plan / phase status / completed-condition verification log: [PLAN.md](PLAN.md)
- Run tests: `composer install && ./vendor/bin/pest` (Pest + orchestra/testbench, real HTTP request cycles via testbench)
- Throwaway spike code (DB connection-switch mechanism verification, kept intentionally, not part of the package): `spike/`
- Composer package: `sparrowhawk-labs/nawate`; PHP namespace `SparrowhawkLabs\Nawate\`; facade `Nawate` (`fragment()`, `link()`); core service `DemoSessionManager` (`src/Services/DemoSessionManager.php`)
- MIT-licensed, OSS under Sparrowhawk Labs (renamed from `tatun55/nawate` on 2026-07-06 — see PLAN.md's Status footnote)
- Published: GitHub `sparrowhawk-labs/nawate` (public, `origin`) + Packagist `sparrowhawk-labs/nawate` (auto-updated via GitHub hook). Pushing requires the `akihiko-takai` gh account (org owner) — `gh auth switch --user akihiko-takai`, push, then `gh auth switch --user tatun55` back. Details: `~/claude/docs/composer-package-publishing.md`
- Sibling verification harness (Phase 5 demo app, EC-mock fixtures + fragments): `~/project/nawate-demo-app` (local git, no remote)
