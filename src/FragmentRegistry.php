<?php

namespace Tatun55\Nawate;

use Closure;
use InvalidArgumentException;

/**
 * Holds the host app's fragment name → Seeder-invoking closure map. Nawate
 * never decides how a fragment is applied — it only stores the pointer the
 * host app registered (blueprint-flow "Seeder single source" — see PLAN.md).
 */
class FragmentRegistry
{
    /** @var array<string, Closure> */
    private array $fragments = [];

    public function fragment(string $name, Closure $callback): void
    {
        $this->fragments[$name] = $callback;
    }

    public function has(string $name): bool
    {
        return isset($this->fragments[$name]);
    }

    public function get(string $name): Closure
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException("Nawate fragment [{$name}] is not registered.");
        }

        return $this->fragments[$name];
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_keys($this->fragments);
    }
}
