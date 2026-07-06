<?php

namespace SparrowhawkLabs\Nawate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use SparrowhawkLabs\Nawate\Services\DemoSessionManager;

/**
 * Runs on every 'web' request (only while nawate.enabled). Reads the demo
 * session cookie left by NawateStateController and re-activates that
 * session's DB connection, so a *redirected* request — not just the initial
 * signed-link hit — keeps seeing the demo data. Silently no-ops (host app
 * behaves normally) when there's no cookie or the session has expired.
 */
class SwitchDemoConnection
{
    public function __construct(
        private readonly DemoSessionManager $sessions,
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $uuid = $request->cookie('nawate_session');

        if (is_string($uuid) && $uuid !== '') {
            $session = $this->sessions->find($uuid);

            if ($session !== null) {
                $this->sessions->activate($session);
            }
        }

        return $next($request);
    }
}
