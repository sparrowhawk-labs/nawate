# nawate

Signed-URL state switching for manual demo/verification in Laravel apps. Hit a
link, land on the target screen already in the exact state you asked for —
zero pollution of host app code.

## Stack

- PHP 8.2+ / Laravel 12+
- SQLite (per-session template copies; independent of the host app's default connection)

## Installation

```bash
composer require tatun55/nawate
php artisan nawate:install
```

`nawate:install` publishes config + runs migrations + publishes docs, in two
layers meant to make nawate legible to LLM coding agents as well as humans:

- **Core layer** — the essentials (API, ordering rules, footguns) are
  appended directly into the host app's **`AGENTS.md`** (created if
  missing). If the host app has a **`CLAUDE.md`**, an `@AGENTS.md` import
  line is ensured there too, so Claude Code picks up the same core section
  without duplicating it (Claude Code reads `CLAUDE.md`, not `AGENTS.md`,
  unless it's imported this way — other AGENTS.md-convention tools read
  `AGENTS.md` directly).
- **Reference layer** — this file, published as **`docs/nawate/README.md`**
  in the host app. Everything below "Status" is the reference layer:
  full API, config, internals, fragment design patterns, and
  troubleshooting.

Both steps are idempotent (safe to re-run `nawate:install`) and can be
skipped with `--no-docs`.

## Status

Phase 1〜5 complete (package skeleton, state recipe / DB isolation core,
signed-URL layer, cleanup command, demo-app verification). See `PLAN.md` in
this repository for the full development log and verification history.

---

## Quick start

```php
// app/Providers/AppServiceProvider.php
use Tatun55\Nawate\Facades\Nawate;
use Database\Seeders\UserSeeder;
use Database\Seeders\PurchaseSeeder;

public function boot(): void
{
    // Fragment order in a link's recipe must respect FK dependencies —
    // see "Fragment design" below.
    Nawate::fragment('user:new', fn () => UserSeeder::asNewUser());
    Nawate::fragment('user:repeat', fn () => UserSeeder::asRepeatCustomer());
    Nawate::fragment('purchase:completed', fn () => PurchaseSeeder::afterCompleted());
}
```

```php
// wherever you build the link to hand someone (a route, a controller, tinker, …)
use Tatun55\Nawate\Facades\Nawate;

$url = Nawate::link(
    fragments: ['user:repeat', 'purchase:completed'],
    redirectTo: '/shop',
    userId: UserSeeder::DEMO_USER_ID, // logs this user in after provisioning
);
```

Hitting `$url`:

1. Provisions a fresh, isolated SQLite copy of your template DB.
2. Runs each fragment's closure against that copy, in order.
3. Logs in as `$userId` if given.
4. Redirects to `$redirectTo`, with a cookie binding this browser to this
   demo session for the rest of its lifetime.

## Config reference (`config/nawate.php`)

| Key | Env var | Default | Meaning |
|---|---|---|---|
| `enabled` | `NAWATE_ENABLED` | `false` | Master switch. No route, no middleware, no exception handling registered at all unless `true`. Set only in local/staging/demo. |
| `signed_url_ttl` | `NAWATE_SIGNED_URL_TTL` | `60` | Minutes a `Nawate::link()` URL stays valid before Laravel's signed-URL check rejects it. |
| `demo_db_storage_path` | `NAWATE_DEMO_DB_STORAGE_PATH` | `app/nawate/demo-sessions` | Where per-session SQLite copies are written. Relative to `storage_path()` unless absolute. |
| `template_db_path` | `NAWATE_TEMPLATE_DB_PATH` | *(none — required)* | Absolute path to a migrated, **empty-of-demo-data** SQLite file. You own keeping this in sync with your schema (see below). |
| `connection` | `NAWATE_CONNECTION` | `nawate_demo` | The DB connection name nawate registers and repoints at each session's file. Kept out of your own connection names — fragments/Seeders never reference it. |
| `cleanup_after_hours` | `NAWATE_CLEANUP_AFTER_HOURS` | `24` | Sessions older than this are eligible for `nawate:cleanup`. |

### Preparing the template DB

nawate does not own your migrations — it copies a file you prepare:

```bash
# after `php artisan migrate` against a normal empty DB (sqlite example)
cp database/database.sqlite storage/app/nawate/template.sqlite
```

Re-copy it any time your schema changes. There's no automatic drift
detection — this is a manual step, documented here rather than automated,
since "automatically re-migrate a file in production" is exactly the kind
of implicit magic nawate avoids.

## Fragment design

A **fragment** is a name plus a closure (`Nawate::fragment($name, $callback)`)
that mutates the demo DB — typically by delegating to your own Seeder's
static methods. Nawate never decides what a fragment does; it only decides
*when* (during provisioning, against the isolated copy) and *in what order*
(the order given in a recipe's `$fragments` array).

Two rules follow directly from that design:

1. **Order fragments to respect foreign keys.** If `cart:with_items`
   inserts a row with `user_id`, whatever fragment creates that user must be
   listed first in the recipe: `['user:repeat', 'cart:with_items']`, not the
   reverse.
2. **Use a fixed, deterministic ID for any user you intend to log in as.**
   Every demo session starts from a *fresh copy* of the template DB, so an
   auto-increment ID is only predictable if you force it:

   ```php
   class UserSeeder
   {
       public const DEMO_USER_ID = 1;

       public static function asNewUser(): User
       {
           return User::updateOrCreate(
               ['id' => self::DEMO_USER_ID],
               ['name' => '…', 'email' => '…', 'password' => Hash::make('…')],
           );
       }
   }
   ```

   `Nawate::link(fragments: [...], redirectTo: '/shop', userId: UserSeeder::DEMO_USER_ID)`
   then reliably logs in as the user the fragment just created, because the
   ID is forced rather than left to auto-increment.

A worked example of this pattern (3-axis EC demo — user type × purchase
status × cart contents) lives in the sibling `nawate-demo-app` project built
during Phase 5 verification.

## How it works (internals)

- **Signed link → recipe.** `Nawate::link()` doesn't look anything up
  server-side; it encodes the whole recipe (fragment names, `$userId`,
  `$redirectTo`) as the token itself
  (`Tatun55\Nawate\Support\StateRecipe::toToken()`/`fromToken()`), then wraps
  it in a Laravel `temporarySignedRoute`. Laravel's own HMAC-over-the-URL is
  what makes tampering detectable — the token's opacity is not a security
  boundary by itself.
- **Provisioning.** Hitting the link (`NawateStateController`) copies
  `template_db_path` to a new UUID-named file under
  `demo_db_storage_path`, points the `nawate.connection` (default
  `nawate_demo`) at that copy, runs the recipe's fragments against it, then
  records a `nawate_demo_sessions` row (uuid, recipe label, file path,
  `expires_at`) — all via `Tatun55\Nawate\Services\DemoSessionManager`.
- **The connection switch itself.** `DB::purge($connection)` +
  `config(['database.connections.<connection>.database' => $path])` +
  `config(['database.default' => $connection])`, restored to whatever was
  default before once fragments finish running. This was verified (Phase 2,
  see `PLAN.md` and `spike/`) against both a bare `Illuminate\Database\Capsule`
  and a full HTTP request cycle through a real Laravel Kernel, across 6
  consecutive switches in the same process (a simplified stand-in for
  Octane-style persistent workers). Host app code never needs to reference
  the connection name.
- **Staying switched across requests.** The controller sets a
  `nawate_session` cookie holding the session UUID. A `SwitchDemoConnection`
  middleware (pushed onto the `web` group, only while `nawate.enabled`)
  reads that cookie on every subsequent request and re-activates the same
  session's connection — so a redirect chain, or simply browsing around
  after landing, keeps seeing the demo data instead of snapping back to the
  host app's real DB.
- **Expired/tampered links.** Laravel's `InvalidSignatureException` is
  caught and replaced, scoped only to the `nawate.state` route, with a plain
  Japanese explanation page (403) instead of the framework default.

### The session-driver gotcha

If the host app's `SESSION_DRIVER` is `database`, the session table lookup
happens through `config('database.default')` at whatever point in the
middleware pipeline `StartSession` runs. Because `SwitchDemoConnection` is
appended to the *end* of the `web` group, a request can start reading its
session against the host's real DB and finish writing it against the demo
DB (or vice-versa on the next request) — the two can desync across
requests. This was found empirically during Phase 5's `nawate-demo-app`
verification. **Fix:** use a non-DB session driver (`file` is the simplest)
in every environment where `nawate.enabled` is true. Nawate itself does not
enforce or auto-switch this — it's a host-app config choice.

### Known unverified edges

Carried over from the original design risk log (`PLAN.md`), still open:

- Behavior inside queued jobs (a job dispatched while the demo connection is
  active, then processed by a separate worker process).
  Nawate targets ordinary synchronous request/response demo/QA — job queues
  are out of scope for now.
- A real Octane (persistent-worker) deployment. The connection-switch
  mechanism was verified against a *simplified* multi-switch simulation in
  the same process, not an actual Octane server.

## Cleanup

Expired demo sessions (SQLite copy + `nawate_demo_sessions` row) are removed by:

```bash
php artisan nawate:cleanup          # deletes what's past expires_at
php artisan nawate:cleanup --dry-run  # lists what would be removed, deletes nothing
```

Nothing runs this automatically — wire it into your own schedule. Two common ways:

```php
// bootstrap/app.php (or routes/console.php) — host app's own Schedule
use Illuminate\Support\Facades\Schedule;

Schedule::command('nawate:cleanup')->hourly();
```

```bash
# or, outside the app entirely (launchd/cron), if you'd rather not touch the host's schedule:
0 * * * * cd /path/to/app && php artisan nawate:cleanup >> storage/logs/nawate-cleanup.log 2>&1
```

## Safety

No route is registered at all in production unless `NAWATE_ENABLED` is
explicitly set to `true` (disabled by default, opt-in only). Signed URLs otherwise carry no
authentication of their own — anyone who obtains a link's URL can use it
(impersonation risk is the tradeoff for zero host-app-code integration).
Treat links as sensitive as the state they grant, and keep `signed_url_ttl`
tight in anything reachable outside a trusted network.

## Localization

The expired/tampered-link 403 page follows the host app's own locale —
`app()->getLocale()`, i.e. `config('app.locale')` / `APP_LOCALE` — the same
setting that drives every other Laravel translation. English and Japanese
are shipped (`resources/lang/{en,ja}/messages.php`); an unset or unshipped
locale falls back to `config('app.fallback_locale')` per normal Laravel
behavior. To add a language or override wording:

```bash
php artisan vendor:publish --tag=nawate-lang
# edit lang/vendor/nawate/{locale}/messages.php
```

## Troubleshooting

- **`SQLSTATE[23000]: … FOREIGN KEY constraint failed` during provisioning**
  — a fragment referencing a not-yet-created row ran before the fragment
  that creates it. Fix the order in the recipe's `$fragments` array (see
  "Fragment design" above).
- **Logged in as the wrong user, or not logged in at all, after a link with
  `userId` set** — the ID doesn't match what the fragment actually created.
  Force a deterministic ID in the Seeder rather than relying on
  auto-increment (see "Fragment design" above).
- **Login doesn't stick past the first request / random logouts while
  demoing** — check `SESSION_DRIVER`; see "The session-driver gotcha" above.
- **Link 403s immediately** — either past `signed_url_ttl` or the URL was
  edited/truncated (breaks the HMAC signature). Generate a new link.
