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

`nawate:install` publishes config + runs migrations + publishes docs.

導入先アプリの Claude Code から nawate の使い方を参照できるよう、install 時に:

- この README を **`docs/nawate/README.md`** として導入先に publish（`vendor:publish --tag=nawate-docs` 単体でも可）
- 導入先の **`CLAUDE.md` に一行ポインタを追記**（無ければ作成・既にあれば何もしない＝冪等）

不要なら `--no-docs` でスキップできる。

## Status

Phase 1（パッケージ骨格）まで。状態レシピ・DB分離・署名付きURL・クリーンアップは
今後の Phase 2〜4 で実装予定。詳細: `PLAN.md`（このリポジトリのルート、または
参考実装 `engawa-dev` 相当の開発計画ドキュメント）。

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

Production では `NAWATE_ENABLED` を明示的に true にしない限り、ルート自体が
一切登録されない（デフォルト無効・opt-in方式）。
