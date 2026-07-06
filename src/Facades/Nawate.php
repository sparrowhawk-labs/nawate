<?php

namespace SparrowhawkLabs\Nawate\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\URL;
use SparrowhawkLabs\Nawate\FragmentRegistry;
use SparrowhawkLabs\Nawate\Support\StateRecipe;

/**
 * @method static void fragment(string $name, \Closure $callback)
 * @method static bool has(string $name)
 * @method static \Closure get(string $name)
 * @method static array names()
 *
 * @see FragmentRegistry
 */
class Nawate extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FragmentRegistry::class;
    }

    /**
     * Build a time-limited signed link that switches to the given recipe.
     * The recipe itself travels in the {token} segment (see
     * StateRecipe::toToken()) — the `signed` middleware's HMAC over the
     * whole URL is what makes tampering detectable.
     */
    public static function link(array $fragments, string $redirectTo, int|string|null $userId = null): string
    {
        $recipe = new StateRecipe(fragments: $fragments, userId: $userId, redirectTo: $redirectTo);

        return URL::temporarySignedRoute(
            'nawate.state',
            now()->addMinutes((int) config('nawate.signed_url_ttl', 60)),
            ['token' => $recipe->toToken()],
        );
    }
}
