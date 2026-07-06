<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tatun55\Nawate\Facades\Nawate;

class NawateTestUser extends Authenticatable
{
    protected $table = 'users';

    public $timestamps = false;
}

beforeEach(function () {
    config(['auth.providers.users.model' => NawateTestUser::class]);

    // Stand-in for "the host app's actual /cart route" — plain host code,
    // no awareness of nawate at all.
    Route::middleware('web')->get('/cart', function () {
        return response()->json([
            'authenticated_as' => Auth::id(),
            'name' => DB::table('users')->where('id', 1)->value('name'),
        ]);
    });

    Nawate::fragment('user:repeat_customer', function () {
        DB::table('users')->where('id', 1)->update([
            'name' => 'Alice',
            'is_repeat_customer' => 1,
        ]);
    });
});

test('a signed nawate link provisions, switches, logs in, and lands on the target screen', function () {
    $url = Nawate::link(['user:repeat_customer'], '/cart', userId: 1);

    $this->get($url)->assertRedirect('/cart');

    // The redirect target is a *second* request — this is what proves the
    // Cookie-driven SwitchDemoConnection middleware (not just the initial
    // controller hit) keeps the demo DB active.
    $this->get('/cart')
        ->assertOk()
        ->assertJson(['authenticated_as' => 1, 'name' => 'Alice']);
});

test('a tampered link is rejected with a plain-language Japanese message', function () {
    $url = Nawate::link(['user:repeat_customer'], '/cart', userId: 1) . 'x';

    $this->get($url)
        ->assertStatus(403)
        ->assertSee('無効です', false);
});

test('an expired link is rejected the same way', function () {
    $this->travelTo(now()->subMinutes(120));
    $url = Nawate::link(['user:repeat_customer'], '/cart', userId: 1);
    $this->travelBack();

    $this->get($url)->assertStatus(403);
});
