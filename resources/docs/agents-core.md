<!-- nawate:core:start -->
## nawate — signed-URL state switching (demo/verification)

`nawate` makes a signed URL jump straight to a specific app state (user type,
purchase status, cart contents, etc.) for manual demo/QA — zero pollution of
host app controllers/models/views. Full reference (config, internals,
troubleshooting): **`docs/nawate/README.md`**.

### Core API

- `SparrowhawkLabs\Nawate\Facades\Nawate::fragment(string $name, Closure $callback)`
  — register a named state fragment, typically from your own
  `AppServiceProvider::boot()`. The closure runs with nawate's isolated demo
  SQLite connection already set as `database.default` — write ordinary
  Eloquent/Seeder code, no connection name needed.
- `Nawate::link(array $fragments, string $redirectTo, int|string|null $userId = null): string`
  — build a time-limited signed URL that applies `$fragments` **in the given
  order**, optionally logs in as `$userId`, then redirects to `$redirectTo`.

### Rules that matter (read before writing fragments)

1. **Fragment order = execution order.** If one fragment creates a user and
   another has a foreign key to that user (a purchase, a cart, …), the
   user fragment must come first in the `$fragments` array.
2. **`$userId` must be a ID your fragments actually produce.** Every demo
   session is a **fresh copy** of the template DB, so an auto-increment ID
   is not something you control ahead of time. Use a fixed, deterministic ID
   in your Seeder instead: `User::updateOrCreate(['id' => KNOWN_ID], [...])`.
3. **Nawate is inert until `NAWATE_ENABLED=true`.** Set it only in
   local/staging/demo environments — never production.
4. **`config('nawate.template_db_path')` must stay in sync with your
   schema.** It's a migrated SQLite file with no demo data in it; re-copy it
   from a freshly-migrated DB whenever your migrations change.
5. **If your host app's `SESSION_DRIVER` is `database`, switch it to
   `file`** (or any non-DB driver) in every environment where nawate is
   enabled. The demo-DB connection swap happens mid-request-pipeline; a
   DB-backed session store can desync across requests as a result (found and
   fixed empirically — see the full docs for why).

### Cleanup

`php artisan nawate:cleanup [--dry-run]` deletes demo sessions past their
TTL (SQLite copy + bookkeeping row). Nothing runs this on a schedule
automatically — wire it into your own `Schedule` or cron (see full docs).

For the complete API surface, every config key, how the DB connection
switch itself works, fragment design patterns, and troubleshooting:
**`docs/nawate/README.md`**.
<!-- nawate:core:end -->
