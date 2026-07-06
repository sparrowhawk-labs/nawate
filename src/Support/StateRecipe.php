<?php

namespace SparrowhawkLabs\Jess\Support;

use InvalidArgumentException;

/**
 * What a signed jess link asks for: which fragments to compose, whom to
 * log in as, and where to land after provisioning.
 */
final class StateRecipe
{
    /**
     * @param array<int, string> $fragments Fragment names, applied in order.
     */
    public function __construct(
        public readonly array $fragments,
        public readonly int|string|null $userId = null,
        public readonly string $redirectTo = '/',
    ) {
    }

    /**
     * Encode as the `{token}` route segment of a jess signed link. The
     * token carries the recipe itself (no server-side lookup table) — the
     * `signed` middleware's HMAC over the full URL is what makes tampering
     * with it detectable, not the token's opacity.
     */
    public function toToken(): string
    {
        $json = json_encode([
            'f' => $this->fragments,
            'u' => $this->userId,
            'r' => $this->redirectTo,
        ]);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function fromToken(string $token): self
    {
        $padded = $token . str_repeat('=', (4 - strlen($token) % 4) % 4);
        $json = base64_decode(strtr($padded, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);

        if (! is_array($data) || ! isset($data['f'], $data['r']) || ! is_array($data['f'])) {
            throw new InvalidArgumentException('Invalid jess state token.');
        }

        return new self(
            fragments: $data['f'],
            userId: $data['u'] ?? null,
            redirectTo: $data['r'],
        );
    }
}
