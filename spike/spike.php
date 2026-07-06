<?php

/**
 * Spike: PLAN.md リスク§1「実行時DB接続切替」の技術的検証。
 *
 * 検証したいこと:
 *   - 1つの永続プロセス（queue worker / Octane を想定した最悪ケース）内で、
 *     "接続名は固定・接続先パスだけを都度差し替え + purge" という方式で、
 *     ホストアプリの Eloquent モデル（接続名を一切意識しないコード）が
 *     正しく毎回切り替わった先の DB を見るか。
 *   - 切替直後に stale な PDO / キャッシュされた行が漏れて見えないか。
 *
 * 非対象（このスパイクではやらない）:
 *   - HTTPミドルウェア経由の実配線、Auth::loginUsingId、署名付きURL検証
 *   - キューイングされたジョブでの挙動（別途要検証と PLAN.md に明記のまま）
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;

// --- 2つの「テンプレートDB複製」を模した sqlite ファイルを用意 ---
$dir = sys_get_temp_dir() . '/nawate-spike-' . uniqid();
mkdir($dir);
$dbA = "$dir/session-A.sqlite";
$dbB = "$dir/session-B.sqlite";

foreach ([$dbA => 'Alice', $dbB => 'Bob'] as $path => $name) {
    touch($path);
    $pdo = new PDO('sqlite:' . $path);
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
    $pdo->exec("INSERT INTO users (name) VALUES ('$name')");
}

// --- ホストアプリ側の「素の」Eloquentモデル。接続名を一切知らない ---
class User extends Model
{
    public $timestamps = false;
    protected $guarded = [];
}

// --- Capsule = DatabaseManager 相当。接続名は 'nawate_demo' で固定 ---
$capsule = new Capsule;

function switchNawateConnection(Capsule $capsule, string $path): void
{
    $capsule->getDatabaseManager()->purge('nawate_demo');
    $capsule->addConnection([
        'driver'   => 'sqlite',
        'database' => $path,
        'prefix'   => '',
    ], 'nawate_demo');
}

// 初回セットアップ: デフォルト接続として 'nawate_demo' を登録
switchNawateConnection($capsule, $dbA);
$capsule->setAsGlobal();       // Facade / Eloquent 全体からの解決に使われる
$capsule->bootEloquent();
Capsule::connection('nawate_demo'); // まだ default 名は 'nawate_demo' ではない点に注意（下で検証）

// --- 検証1: デフォルト接続名を 'nawate_demo' に揃え、モデルが接続名を指定しなくても届くか ---
$capsule->getDatabaseManager()->setDefaultConnection('nawate_demo');

$results = [];
$sequence = [
    [$dbA, 'Alice'],
    [$dbB, 'Bob'],
    [$dbA, 'Alice'],
    [$dbB, 'Bob'],
    [$dbB, 'Bob'],
    [$dbA, 'Alice'],
];

$i = 0;
foreach ($sequence as [$path, $expected]) {
    $i++;
    switchNawateConnection($capsule, $path);
    $name = User::query()->value('name'); // 接続名を一切書かないクエリ
    $ok = $name === $expected;
    $results[] = $ok;
    printf("[%d] switched to %-30s expected=%-6s got=%-6s => %s\n",
        $i, basename($path), $expected, $name ?? 'null', $ok ? 'OK' : 'FAIL');
}

// --- 検証2: 直前に生成済みの Model インスタンス（"リクエスト跨ぎで使い回されるかもしれない"想定）が
//            接続切替後も正しい接続を解決し直すか ---
switchNawateConnection($capsule, $dbA);
$staleInstance = new User(); // 切替前に new 済みのインスタンス、という想定
switchNawateConnection($capsule, $dbB);
$viaStale = $staleInstance->newQuery()->value('name');
$ok2 = $viaStale === 'Bob';
printf("[stale-instance] expected=Bob got=%-6s => %s\n", $viaStale ?? 'null', $ok2 ? 'OK' : 'FAIL');

$allOk = !in_array(false, $results, true) && $ok2;
echo "\n=== " . ($allOk ? 'ALL CHECKS PASSED' : 'SOME CHECKS FAILED') . " ===\n";

// cleanup
foreach ([$dbA, $dbB] as $f) {
    @unlink($f);
}
@rmdir($dir);

exit($allOk ? 0 : 1);
