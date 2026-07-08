# jess

Laravel package: signed-URL state switching for manual demo/verification (per-session SQLite isolation, zero host-app pollution). Composer path-repository package, same shape as `~/project/engawa-dev`.

- Development plan / phase status / completed-condition verification log: [PLAN.md](PLAN.md)
- Run tests: `composer install && ./vendor/bin/pest` (Pest + orchestra/testbench, real HTTP request cycles via testbench)
- Throwaway spike code (DB connection-switch mechanism verification, kept intentionally, not part of the package): `spike/`
- Composer package: `sparrowhawk-labs/jess`; PHP namespace `SparrowhawkLabs\Jess\`; facade `Jess` (`fragment()`, `link()`); core service `DemoSessionManager` (`src/Services/DemoSessionManager.php`)
- MIT-licensed, OSS under Sparrowhawk Labs (org renamed from `tatun55/nawate` on 2026-07-06; package itself renamed `nawate` → `jess` on 2026-07-07 — see PLAN.md's Status footnote)
- Published: GitHub `sparrowhawk-labs/jess` (public, `origin`) + Packagist `sparrowhawk-labs/jess` (auto-updated via GitHub hook). Pushing requires the `akihiko-takai` gh account (org owner) — `gh auth switch --user akihiko-takai`, push, then `gh auth switch --user tatun55` back. Details: `~/claude/docs/composer-package-publishing.md`
- Sibling verification harness (Phase 5 demo app, EC-mock fixtures + fragments): `~/project/jess-demo-app` (local git, no remote — renamed from `nawate-demo-app` on 2026-07-07 to match the jess rename)
