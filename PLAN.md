# nawate — 開発計画

> **目的**: 手動動作確認・デモのための「状態切替」機能を提供する Laravel サブパッケージ。
> `engawa` と同じ方式（composer path repository でローカル開発 → 将来 packagist 公開可）。
> 参考実装: `/Users/a_t/project/engawa-dev`（ディレクトリ構成・install コマンド・README publish の作法を踏襲）

## スコープ（重要・毎フェーズここに立ち返る）

- **やること**: 署名付きURLを踏むと、指定した「状態レシピ」（ユーザー種別×購入状態×カート中身 等の組み合わせ）に一発で切り替わり、対象の実業務画面にリダイレクトされる。ホストアプリのコード（コントローラ・モデル・ビュー）は一切汚染しない。
- **やらないこと（非スコープ）**: e2e動画の生成・動画マニュアルUI・動画とリンクの紐付けページ。これらは別途 `e2e-demo-video` スキルや動画ラッパーページの仕事であり、nawate は「状態切替」という1機能だけに専念する（Simplicity First）。

## 全体ステータス

| Phase | 内容 | 状態 |
|---|---|---|
| 0 | 設計確定 | ✅ 完了（本ドキュメントで確定） |
| 1 | パッケージ骨格 (`nawate-dev` リポジトリ) | ✅ 完了 |
| 2 | コア機構（状態レシピ・DB分離） | ✅ 完了 |
| 3 | URL層（署名付きリンク・リダイレクト） | ✅ 完了 |
| 4 | クリーンアップ機構 | ✅ 完了 |
| 5 | 最小デモアプリでの段階的手動検証 | ✅ 完了 |

> 進め方: 各 Phase のタスクを `- [ ]` → `- [x]` に更新しながら進める。Phase 5 は必ず「人間が実際にブラウザで確認して✅を付ける」ゲートを含む。

---

## 確定済みの設計判断（前段の会話で合意）

1. **パッケージ名**: `nawate`（仮）。将来 `tatun55/nawate` として公開予定。
2. **対象アプリ**: 新規の最小デモアプリを別途作成し、既存プロジェクトを汚さずに検証する。
3. **DB分離方式**: セッション単位でSQLiteファイルを複製（テンプレートDB→デモセッションごとにコピー）。複数人が同時に別状態を見ても衝突しない。定期TTLクリーンアップとセットで運用する。
4. **パッケージ化形態**: engawa と同じ composer path repository 方式。

### 状態切替の全体フロー（再掲）

```
署名付きURL (/nawate/state/{token}, 有効期限あり)
  ↓
NawateController（ホストアプリの業務コードから完全分離）
  1. token から「状態レシピ」(fragment 名の配列) を解決
  2. テンプレートSQLiteをセッション単位で複製
  3. 複製DBに対し、各fragmentに対応するSeederのstatic helperを順次適用
  4. リクエストのDB接続先をこの複製ファイルに切替（Cookieでセッションid保持）
  5. 指定ユーザーで Auth::loginUsingId()
  6. 対象の実業務ルートへリダイレクト
  ↓
以降は通常のアプリコードがそのまま動く（nawateの存在を一切知らない）
```

---

## Phase 1: パッケージ骨格

`engawa-dev` と同一の構成で `nawate-dev` を立ち上げる。

### タスク

- [x] `composer.json` 作成（package名 `tatun55/nawate`、Laravel package auto-discovery設定）
- [x] `src/NawateServiceProvider.php`（config publish・migration・route登録。`config('nawate.enabled')` が false の場合はルート自体を登録しない）
- [x] `src/Console/InstallCommand.php`（`nawate:install` — config publish + migration実行 + README publish、engawaのinstallコマンドを踏襲）
- [x] `config/nawate.php`（`enabled`, `signed_url_ttl`, `demo_db_storage_path`, `cleanup_after_hours` 等）
- [x] `database/migrations/0001_01_01_020000_create_nawate_demo_sessions_table.php`（ホストアプリの**実DB**側に置く追跡テーブル。中身は後述 Phase 2）
- [x] `.gitignore` / `README.md` stub

### 完了条件

- [x] **2026-07-06 実機検証済み**: `composer create-project laravel/laravel` で作った空アプリに path repository 経由で `composer require tatun55/nawate:@dev` → `php artisan nawate:install` を実行。config publish・migration実行（`nawate_demo_sessions` テーブル生成を`.schema`で確認）・docs publish・CLAUDE.mdポインタ追記すべて成功。検証用アプリは使い捨てとして削除済み。

---

## Phase 2: コア機構（状態レシピ・DB分離）

### タスク

- [x] `NawateFragment` レジストリ設計 — `src/FragmentRegistry.php` + `Nawate` facade (`src/Facades/Nawate.php`)。`Nawate::fragment('user:repeat_customer', fn () => ...)` で登録、`FragmentRegistry::get()` は未登録名で `InvalidArgumentException`（サイレントno-opにしない）。Seederの呼び出し方はホストアプリのclosureに一任、nawateは名前→closureのポインタしか持たない。
- [x] `StateRecipe` 値オブジェクト — `src/Support/StateRecipe.php`（fragments配列 + userId + redirectTo、readonly）
- [x] `DemoSessionManager::provision(StateRecipe): DemoSession` — `src/Services/DemoSessionManager.php`。テンプレートSQLite(`config('nawate.template_db_path')`)を`demo_db_storage_path`配下に`{uuid}.sqlite`として複製 → fragment群を順次適用 → `nawate_demo_sessions`にbookkeeping行を記録。
- [x] 実行時DB接続切替の実装 — `DemoSessionManager` 内の `configureConnection()`/`withDemoConnection()`/`activate()`。spike/ で検証済みの「接続名固定 + purge + config差替」方式をそのまま実装に反映。`provision()`はfragment適用のためだけに一時的にデフォルト接続を切り替え、bookkeeping書き込み前に必ず元へ復元（グローバル状態を漏らさない）。`activate()`はリクエスト残り時間ぶん切替を維持する版（Phase 3のミドルウェアが呼ぶ想定）。

### 完了条件

- [x] **2026-07-06 検証済み**: `tests/Feature/DemoSessionManagerTest.php`（Pest + orchestra/testbench、実アプリ起動込み）で (1) provision()が複製DBにfragmentを適用しbookkeeping行を記録すること、(2) provision()がグローバル接続状態を漏らさないこと、(3) activate()がリクエスト残りぶん接続を切り替え続けること、(4) 未登録fragment名が例外になること、の4点を確認。Tinker手動確認より厳密な自動テストとして代替。再現: `composer install && ./vendor/bin/pest`（3 tests / 11 assertions / 全PASS）。

---

## Phase 3: URL層（署名付きリンク・リダイレクト）

### タスク

- [x] `GET /nawate/state/{token}` ルート — `routes/nawate.php`。`['web','signed']` ミドルウェア、`config('nawate.enabled')` の時のみ `loadRoutesFrom` で登録（無効時はルート自体が存在しない）
- [x] `NawateStateController` — `src/Http/Controllers/NawateStateController.php`（invokable）。token検証(`signed`ミドルウェアが自動) → `StateRecipe::fromToken()` → `provision()` → `activate()` → `Auth::loginUsingId()` → `redirect()->cookie('nawate_session', ...)`
- [x] リンク生成ヘルパー `Nawate::link(array $fragments, string $redirectTo, ?int $userId = null): string` — `src/Facades/Nawate.php`。recipeを `{token}` 自体にエンコードし（`StateRecipe::toToken()`）、`URL::temporarySignedRoute` で有効期限付きURLを生成。サーバー側の別テーブルにトークンを持たない設計（`signed`ミドルウェアのHMACが改ざん検知を担う）
- [x] 署名検証失敗・期限切れ時のエラーページ — `NawateServiceProvider::registerExpiredLinkPage()`。`InvalidSignatureException` を `nawate.state` ルートに限定して分かりやすい日本語メッセージの403に差し替え
- [x] **タスク一覧に無いが完了条件を満たすために必須と判断し追加実装**: `src/Http/Middleware/SwitchDemoConnection.php` — `web`ミドルウェアグループに登録し、Cookie(`nawate_session`)から毎リクエスト `DemoSessionManager::activate()` を呼び直す。これが無いと「リダイレクト後の2回目のリクエスト」でデモDBへの切替が失われ、完了条件（ブラウザで`/cart`が指定状態で表示される）を満たせない。`DemoSessionManager::find(uuid)` を追加してこのミドルウェアが使う。

### 完了条件

- [x] **2026-07-06 検証済み**（`tests/Feature/StateLinkTest.php`、Pest + testbench、実HTTPリクエスト2回＝初回のsigned URLヒット＋リダイレクト先への追従リクエスト）:
  - `Nawate::link(['user:repeat_customer'], '/cart', userId: 1)` を叩く → `/cart` へリダイレクト → **リダイレクト先への2回目のリクエスト**で `Auth::id() === 1` かつ複製DBに適用したfragmentの内容（`name === 'Alice'`）が見える
  - 改ざんされたリンク・期限切れリンクはどちらも403 + 「無効です」を含む日本語メッセージ
  - 再現: `./vendor/bin/pest`（6 tests / 18 assertions / 全PASS、`--order-by=random` でも安定）
- **既知の未対応**: `find()` はミドルウェア実行時点の `database.default` に依存しており、Octane等の永続プロセスでの安全性は未検証のまま（PLAN.md リスク§1と同じ既知の残課題）。

---

## Phase 4: クリーンアップ機構

### タスク

- [x] `nawate:cleanup` artisan command — `src/Console/CleanupCommand.php`。`expires_at` 超過分の `nawate_demo_sessions` 行 + 対応するSQLiteファイルを削除。`config('nawate.enabled')` に関わらずコンソールでは常に登録（web経由で到達不能なため安全）
- [x] ホストアプリの `Schedule` への登録例をREADMEに記載 — `README.md` §Cleanup（`Schedule::command('nawate:cleanup')->hourly()` 例 + launchd/cron経由の例の両方）
- [x] 手動実行時のドライラン(`--dry-run`)オプション

### 完了条件

- [x] **2026-07-06 検証済み**（`tests/Feature/CleanupCommandTest.php`）: 期限切れセッションを作った状態で `nawate:cleanup` を実行 → ファイル・レコード両方が削除されることを確認。`--dry-run` では両方とも削除されないこと、未期限セッションは触られないことも確認。再現: `./vendor/bin/pest`（累計9 tests / 28 assertions / 全PASS、`--order-by=random` でも安定）。

---

## Phase 5: 最小デモアプリでの段階的手動検証

新規に最小のLaravel(+Livewire)デモアプリを作り、`nawate-dev` を path repository で導入して段階的に確認する。**各ステップは人間が実際にブラウザで見て✅を付けるまで次へ進まない。**

### 準備

- [x] 最小デモアプリ scaffold（`nawate-demo-app`、Laravel 13 + Livewire 4 + SQLite、`~/project/nawate-demo-app`）
- [x] デモ用の題材を用意: 簡単な「ECもどき」— ユーザー(新規/リピーター)・購入状態(未購入/購入済み)・カート中身(空/あり)の3軸（`app/Models/{User,Purchase,Cart}.php`、`/shop` Livewireページ）
- [x] 対応する Seeder + static helper をデモアプリ側に実装（`database/seeders/{UserSeeder,PurchaseSeeder,CartSeeder}.php` — `UserSeeder::asNewUser()/asRepeatCustomer()`, `PurchaseSeeder::afterCompleted()`, `CartSeeder::withItems()`）
- [x] `nawate` を path repository 経由で導入、`nawate:install` 実行（`app/Providers/AppServiceProvider.php` で4 fragment登録: `user:new`/`user:repeat`/`purchase:completed`/`cart:with_items`）

### 段階的デモ

- [x] **Demo 1（単純系）**: fragment 1つだけの状態切替リンクを踏み、ゲストユーザー・カート空の画面が出ることを確認 — **2026-07-06 人間ゲート✅**
- [x] **Demo 2（認証込み）**: 特定ユーザーとしてログインされた状態に切り替わることを確認 — **2026-07-06 人間ゲート✅**
- [x] **Demo 3（合成）**: `user:repeat + purchase:completed + cart:with_items` の3fragment合成が正しく反映されることを確認 — **2026-07-06 人間ゲート✅**
- [x] **Demo 4（DB分離）**: 2つの別々の状態切替リンクをブラウザの別タブで同時に開き、互いに干渉しないことを確認 — **2026-07-06 人間ゲート✅**
- [x] **Demo 5（クリーンアップ）**: TTLを短く設定した状態でセッションを作り、`nawate:cleanup` 実行後にファイル・レコードが消えることを確認 — **2026-07-06 人間ゲート✅**
- [ ] **Demo 6（統合・任意）**: `e2e-demo-video` スキルで撮影した動画のシーン説明に、nawateのリンクを手動で埋め込み、動画→実際の状態へジャンプする一気通貫の体験を確認（任意・未実施）

### 完了条件

- [x] **2026-07-06 検証済み**: Demo 1〜5全て✅（レビューページ: `nawate-demo-app/docs/visualized/nawate-phase5.html`、スクリーンショット: `nawate-demo-app/.screenshots/`）。Demo 6は任意のため未実施（本パッケージの本スコープ外）。
- **セッション周りの追加知見**: デモアプリの `SESSION_DRIVER` は `database`（Laravel既定）のままだと、nawateのDB接続切替タイミングとの相互作用でログインセッションがリクエスト間で不整合になりうるため、`file` ドライバに変更して検証した（nawate本体には変更なし。ホストアプリがセッションをDB接続経由で持つ場合の既知の注意点として記録）。

---

## リスク・未確定事項（Stop and Report）

1. **実行時DB接続切替の技術的難度（Phase 2）**: ~~Laravelのデフォルト接続を1リクエスト内で安全に差し替える実装は、接続プーリング・キャッシュ・キュー等との相互作用で罠が多い可能性がある。Phase 2着手時に小さなスパイク実装で先に検証し、想定より複雑なら「複製DBへの接続をモデル単位で明示指定する」等の代替方式に倒す判断をする。~~
   → **2026-07-06 検証済み（`spike/`）**: `DB::purge('nawate_demo')` + `config(['database.connections.nawate_demo.database' => $path])` + `config(['database.default' => 'nawate_demo'])` の組み合わせで、(a) Capsule単体・(b) 実HTTPリクエスト（orchestra/testbench の本物のLaravel Kernel経由、ミドルウェア→ルート→`DB::table()`）の両方で、同一プロセス内の6回連続切替（persistent worker/Octaneの簡易再現含む）が全て正しく解決することを確認。ホストアプリのコードは接続名を一切意識しない設計のまま成立する。**「デフォルト接続の一時差し替え方式」で確定、代替方式（モデル単位で明示指定）への切替は不要と判断。**
   - 未検証のまま残る範囲（Phase 2本実装時に要検証）: キューイングされたジョブでの挙動、実際のOctane環境、`Auth::loginUsingId()` との連携、署名付きURLの実処理。
   - 再現コード: `spike/spike.php`（Capsule単体）、`spike/tests/HttpConnectionSwitchSpikeTest.php`（HTTP経由・`cd spike && composer install && ./vendor/bin/phpunit`）。
2. **セキュリティ境界**: 署名付きURLの有効期限・production環境での完全無効化・認可(誰でもこのURLを知れば任意ユーザーになりすませる点)の扱いは、Phase 3で改めて明文化する。少なくとも「production では `config('nawate.enabled')=false` を既定にし、明示的opt-inでのみ有効化」を徹底する。
3. **テンプレートDBとマイグレーションの同期**: ホストアプリのスキーマが変わった時、テンプレートSQLiteをどう最新化するか（毎回 `migrate:fresh` して作り直す運用にするか）は運用ドキュメントに明記する必要がある。

---

## 参考

- `engawa` README: `/Users/a_t/project/engawa-dev/README.md`
- blueprint-flow v4（Seeder単一ソース方針, §8.3）: `/Users/a_t/project/blueprint-flow/BLUEPRINT_FLOW_v4.md`
- `e2e-demo-video` スキル（本パッケージの非スコープだが連携先）
