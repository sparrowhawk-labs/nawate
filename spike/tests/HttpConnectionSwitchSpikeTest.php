<?php

/**
 * Spike (延長): PLAN.md リスク§1を、実際の HTTP リクエストサイクル
 * （orchestra/testbench = 本物の Laravel Kernel 経由）で検証する。
 *
 * 検証したいこと:
 *   - ミドルウェアで "セッションID"（署名付きURLの代わりに簡易クエリパラメータ）から
 *     複製DBファイルのパスを決め、default connection の接続先を差し替える。
 *   - ホストアプリ側のルート/コントローラ/モデルは接続名を一切意識しない。
 *   - 同一の Laravel アプリケーションインスタンス（testbench は1テストケース内で
 *     アプリを使い回せる = persistent worker の簡易再現）で複数リクエストを
 *     連続して投げ、毎回正しい複製DBの中身が返るか。
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;

class HttpConnectionSwitchSpikeTest extends TestCase
{
    private string $dir;
    private string $dbA;
    private string $dbB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/nawate-http-spike-' . uniqid();
        mkdir($this->dir);
        $this->dbA = "{$this->dir}/session-A.sqlite";
        $this->dbB = "{$this->dir}/session-B.sqlite";
        $this->makeDemoDb($this->dbA, 'Alice');
        $this->makeDemoDb($this->dbB, 'Bob');

        // ベースの接続情報だけ登録（database path はミドルウェアが都度差し込む）
        config(['database.connections.nawate_demo' => [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]]);

        // ホストアプリのコントローラ相当。接続名を一切書かない素の DB クエリ。
        Route::middleware(NawateSwitchMiddlewareSpike::class)
            ->get('/whoami', function () {
                return response()->json(['name' => DB::table('users')->value('name')]);
            });
    }

    protected function tearDown(): void
    {
        @unlink($this->dbA);
        @unlink($this->dbB);
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function makeDemoDb(string $path, string $name): void
    {
        touch($path);
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('$name')");
    }

    /** @test */
    public function runtime_connection_switch_works_across_real_http_requests_via_middleware(): void
    {
        NawateSwitchMiddlewareSpike::$paths = ['A' => $this->dbA, 'B' => $this->dbB];

        // 同一アプリインスタンスに対して連続で異なる「セッション」のリクエストを投げる
        // (persistent worker / Octane を想定した最悪ケースの簡易再現)
        $sequence = [
            ['A', 'Alice'],
            ['B', 'Bob'],
            ['A', 'Alice'],
            ['B', 'Bob'],
            ['B', 'Bob'],
            ['A', 'Alice'],
        ];

        foreach ($sequence as [$session, $expected]) {
            $response = $this->get("/whoami?session={$session}");
            $response->assertOk();
            $this->assertSame($expected, $response->json('name'), "session={$session} で期待と異なる結果");
        }
    }
}

/**
 * PLAN.md の設計通り「Cookie/クエリ等からuuidを読み、config書き換え + purge」を行う実ミドルウェア。
 */
class NawateSwitchMiddlewareSpike
{
    /** @var array<string,string> */
    public static array $paths = [];

    public function handle(Request $request, \Closure $next)
    {
        $session = $request->query('session');
        $path = self::$paths[$session];

        DB::purge('nawate_demo');
        config(['database.connections.nawate_demo.database' => $path]);
        config(['database.default' => 'nawate_demo']);

        return $next($request);
    }
}
