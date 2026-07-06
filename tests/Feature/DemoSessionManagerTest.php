<?php

use Illuminate\Support\Facades\DB;
use SparrowhawkLabs\Jess\Facades\Jess;
use SparrowhawkLabs\Jess\Services\DemoSessionManager;
use SparrowhawkLabs\Jess\Support\StateRecipe;

test('provision copies the template, applies fragments, and records a session', function () {
    Jess::fragment('user:repeat_customer', function () {
        DB::table('users')->where('id', 1)->update([
            'name' => 'Alice',
            'is_repeat_customer' => 1,
        ]);
    });

    $originalDefault = config('database.default');

    $session = app(DemoSessionManager::class)->provision(
        new StateRecipe(fragments: ['user:repeat_customer'], userId: 1, redirectTo: '/cart')
    );

    expect($session->uuid)->not->toBeEmpty();
    expect(is_file($session->sqlitePath))->toBeTrue();

    // provision() must not leak the connection switch into global state.
    expect(config('database.default'))->toBe($originalDefault);

    // Bookkeeping row lives on the host's real connection.
    $row = DB::table('jess_demo_sessions')->where('uuid', $session->uuid)->first();
    expect($row)->not->toBeNull();
    expect($row->recipe)->toBe('user:repeat_customer');
    expect($row->demo_db_path)->toBe($session->sqlitePath);

    // The fragment actually landed in the copied demo DB, not the host DB.
    $pdo = new PDO('sqlite:' . $session->sqlitePath);
    $demoRow = $pdo->query('SELECT name, is_repeat_customer FROM users WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
    expect($demoRow['name'])->toBe('Alice');
    expect((int) $demoRow['is_repeat_customer'])->toBe(1);
});

test('activate() points the runtime default connection at the session db for the rest of the request', function () {
    Jess::fragment('user:vip', function () {
        DB::table('users')->where('id', 1)->update(['name' => 'VIP User']);
    });

    $manager = app(DemoSessionManager::class);
    $session = $manager->provision(new StateRecipe(fragments: ['user:vip']));

    $manager->activate($session);

    expect(config('database.default'))->toBe(config('jess.connection'));
    expect(DB::table('users')->where('id', 1)->value('name'))->toBe('VIP User');
    // (TestCase::tearDown() restores the default connection after this test —
    // activate() intentionally leaves the switch in place for a real request.)
});

test('unregistered fragment name throws instead of silently no-op-ing', function () {
    $manager = app(DemoSessionManager::class);

    expect(fn () => $manager->provision(new StateRecipe(fragments: ['does:not-exist'])))
        ->toThrow(InvalidArgumentException::class);
});

test('a fragment calling a MySQL-only function fails with a clear, attributed error', function () {
    Jess::fragment('search:rand', function () {
        // RAND() is MySQL's random function (SQLite's equivalent is
        // RANDOM()) — SQLite has no function by this name, so this
        // reproduces "wrote MySQL-only SQL into a fragment" without
        // needing a real MySQL server.
        DB::select('select RAND() as r');
    });

    $manager = app(DemoSessionManager::class);

    try {
        $manager->provision(new StateRecipe(fragments: ['search:rand']));
        $this->fail('Expected FragmentExecutionException to be thrown.');
    } catch (\SparrowhawkLabs\Jess\Exceptions\FragmentExecutionException $e) {
        expect($e->getMessage())->toContain("fragment 'search:rand'");
        expect($e->getMessage())->toContain('MySQL/PostgreSQL-specific SQL feature');
        expect($e->getMessage())->toContain('RAND');
        expect($e->getPrevious())->toBeInstanceOf(\Illuminate\Database\QueryException::class);
    }
});

test('a fragment with an ordinary bug still throws its original exception type via the wrapper', function () {
    Jess::fragment('broken:typo', function () {
        DB::table('no_such_table_at_all')->insert(['x' => 1]);
    });

    $manager = app(DemoSessionManager::class);

    try {
        $manager->provision(new StateRecipe(fragments: ['broken:typo']));
        $this->fail('Expected FragmentExecutionException to be thrown.');
    } catch (\SparrowhawkLabs\Jess\Exceptions\FragmentExecutionException $e) {
        // No "no such function" signature here, so no engine-incompatibility
        // guess is appended — the original DB error still comes through.
        expect($e->getMessage())->toContain("fragment 'broken:typo'");
        expect($e->getMessage())->not->toContain('MySQL/PostgreSQL-specific');
    }
});
