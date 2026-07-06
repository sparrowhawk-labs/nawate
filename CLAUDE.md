# nawate

Laravel package: signed-URL state switching for manual demo/verification (per-session SQLite isolation, zero host-app pollution). Composer path-repository package, same shape as `~/project/engawa-dev`.

- Development plan / phase status / completed-condition verification log: [PLAN.md](PLAN.md)
- Run tests: `composer install && ./vendor/bin/pest` (Pest + orchestra/testbench, real HTTP request cycles via testbench)
- Throwaway spike code (DB connection-switch mechanism verification, kept intentionally, not part of the package): `spike/`
- Package namespace: `Tatun55\Nawate\`; facade `Nawate` (`fragment()`, `link()`); core service `DemoSessionManager` (`src/Services/DemoSessionManager.php`)
