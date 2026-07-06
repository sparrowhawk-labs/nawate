<?php

use Illuminate\Support\Facades\DB;
use SparrowhawkLabs\Jess\Services\DemoSessionManager;
use SparrowhawkLabs\Jess\Support\StateRecipe;

function provisionExpiredSession(): \SparrowhawkLabs\Jess\Support\DemoSession
{
    $session = app(DemoSessionManager::class)->provision(new StateRecipe(fragments: []));

    DB::table('jess_demo_sessions')
        ->where('uuid', $session->uuid)
        ->update(['expires_at' => now()->subMinute()]);

    return $session;
}

test('cleanup removes expired sessions (file + bookkeeping row)', function () {
    $session = provisionExpiredSession();
    expect(is_file($session->sqlitePath))->toBeTrue();

    $this->artisan('jess:cleanup')->assertExitCode(0);

    expect(is_file($session->sqlitePath))->toBeFalse();
    expect(DB::table('jess_demo_sessions')->where('uuid', $session->uuid)->exists())->toBeFalse();
});

test('cleanup --dry-run deletes nothing', function () {
    $session = provisionExpiredSession();

    $this->artisan('jess:cleanup', ['--dry-run' => true])->assertExitCode(0);

    expect(is_file($session->sqlitePath))->toBeTrue();
    expect(DB::table('jess_demo_sessions')->where('uuid', $session->uuid)->exists())->toBeTrue();
});

test('cleanup leaves unexpired sessions untouched', function () {
    $session = app(DemoSessionManager::class)->provision(new StateRecipe(fragments: []));

    $this->artisan('jess:cleanup')->assertExitCode(0);

    expect(is_file($session->sqlitePath))->toBeTrue();
    expect(DB::table('jess_demo_sessions')->where('uuid', $session->uuid)->exists())->toBeTrue();
});
