<?php

namespace SparrowhawkLabs\Nawate\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use SparrowhawkLabs\Nawate\Services\DemoSessionManager;
use SparrowhawkLabs\Nawate\Support\StateRecipe;

/**
 * The one entry point a nawate signed link hits: resolve the recipe carried
 * in {token}, provision the demo DB, switch to it, log the recipe's user in,
 * and send the browser on to the real business screen. Everything past the
 * redirect is ordinary host-app code — it never learns nawate exists.
 */
class NawateStateController extends Controller
{
    public function __invoke(string $token, DemoSessionManager $sessions): RedirectResponse
    {
        $recipe = StateRecipe::fromToken($token);

        $session = $sessions->provision($recipe);
        $sessions->activate($session);

        if ($recipe->userId !== null) {
            Auth::loginUsingId($recipe->userId);
        }

        $cookieMinutes = (int) config('nawate.cleanup_after_hours', 24) * 60;

        return redirect($recipe->redirectTo)
            ->cookie(Cookie::make('nawate_session', $session->uuid, $cookieMinutes));
    }
}
