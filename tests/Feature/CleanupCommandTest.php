<?php

use Illuminate\Support\Facades\DB;
use SparrowhawkLabs\Nawate\Services\DemoSessionManager;
use SparrowhawkLabs\Nawate\Support\StateRecipe;

function provisionExpiredSession(): \SparrowhawkLabs\Nawate\Support\DemoSession
{
    $session = app(DemoSessionManager::class)->provision(new StateRecipe(fragments: []));

    DB::table('nawate_demo_sessions')
        ->where('uuid', $session->uuid)
        ->update(['expires_at' => now()->subMinute()]);

    return $session;
}

test('cleanup removes expired sessions (file + bookkeeping row)', function () {
    $session = provisionExpiredSession();
    expect(is_file($session->sqlitePath))->toBeTrue();

    $this->artisan('nawate:cleanup')->assertExitCode(0);

    expect(is_file($session->sqlitePath))->toBeFalse();
    expect(DB::table('nawate_demo_sessions')->where('uuid', $session->uuid)->exists())->toBeFalse();
});

test('cleanup --dry-run deletes nothing', function () {
    $session = provisionExpiredSession();

    $this->artisan('nawate:cleanup', ['--dry-run' => true])->assertExitCode(0);

    expect(is_file($session->sqlitePath))->toBeTrue();
    expect(DB::table('nawate_demo_sessions')->where('uuid', $session->uuid)->exists())->toBeTrue();
});

test('cleanup leaves unexpired sessions untouched', function () {
    $session = app(DemoSessionManager::class)->provision(new StateRecipe(fragments: []));

    $this->artisan('nawate:cleanup')->assertExitCode(0);

    expect(is_file($session->sqlitePath))->toBeTrue();
    expect(DB::table('nawate_demo_sessions')->where('uuid', $session->uuid)->exists())->toBeTrue();
});
