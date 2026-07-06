<?php

namespace SparrowhawkLabs\Jess\Support;

use DateTimeInterface;

/**
 * A provisioned, per-session SQLite copy with the requested fragments
 * already applied — the record persisted in `jess_demo_sessions`.
 */
final class DemoSession
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $recipe,
        public readonly string $sqlitePath,
        public readonly DateTimeInterface $expiresAt,
    ) {
    }
}
