<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Turns a provider identity token into an identity we are willing to trust.
 *
 * The sign-in endpoint used to take `provider_id` and `email` straight from
 * the request body. Anyone could post the email address of an existing account
 * and be handed a Sanctum token for it, because the lookup matches on email —
 * account takeover with nothing but an address, and the legacy import brought
 * 1165 of those across. Nothing about "who you are" may come from the client
 * any more; it comes out of a signature we check ourselves.
 */
class SocialIdentity
{
    private function __construct(
        public readonly string $provider,
        public readonly string $id,
        public readonly ?string $email,
        public readonly ?string $name,
    ) {
    }

    /**
     * @throws RuntimeException when the token is missing, malformed, expired,
     *                          signed by someone else, or issued for a
     *                          different app.
     */
    public static function verify(string $provider, string $idToken): self
    {
        return match ($provider) {
            'google' => self::google($idToken),
            'apple' => self::apple($idToken),
            default => throw new RuntimeException('Unsupported provider.'),
        };
    }

    /**
     * Google publishes a tokeninfo endpoint that checks the signature and
     * expiry for us. The audience check is still ours to make: a validly
     * signed token issued for somebody else's app is not a sign-in here.
     */
    private static function google(string $idToken): self
    {
        $response = Http::timeout(10)
            ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        if (!$response->successful()) {
            throw new RuntimeException('Google rejected the sign-in token.');
        }

        $claims = $response->json();

        $issuer = $claims['iss'] ?? '';
        if (!in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new RuntimeException('Unexpected token issuer.');
        }

        $allowed = array_filter(config('services.google.allowed_audiences', []));
        if ($allowed === []) {
            throw new RuntimeException('Google sign-in is not configured.');
        }
        if (!in_array($claims['aud'] ?? '', $allowed, true)) {
            throw new RuntimeException('Token was issued for a different app.');
        }

        $subject = $claims['sub'] ?? '';
        if ($subject === '') {
            throw new RuntimeException('Token carries no subject.');
        }

        // An unverified address must not be allowed to match an existing
        // account by email, which is exactly the door we are closing.
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self(
            'google',
            $subject,
            $verified ? ($claims['email'] ?? null) : null,
            $claims['name'] ?? null,
        );
    }

    /**
     * Apple has no tokeninfo equivalent, so the JWT is checked against their
     * published keys. The key set is cached: Apple rotates it, and refetching
     * on every sign-in would put their availability in front of ours.
     */
    private static function apple(string $idToken): self
    {
        $audiences = array_filter(config('services.apple.allowed_audiences', []));
        if ($audiences === []) {
            throw new RuntimeException('Apple sign-in is not configured.');
        }

        // Apple rate-limits this endpoint, so it is cached and refetched only
        // when a token names a key we have not seen — which is what a rotation
        // looks like from here. An earlier version refetched on any failed
        // verification, so every junk token cost two requests to Apple and
        // tripped the limit, which then looked like Apple being unreachable.
        $keys = self::appleKeys(false);
        $kid = self::keyIdOf($idToken);
        if ($kid !== null && !isset($keys[$kid])) {
            $keys = self::appleKeys(true);
        }

        try {
            $claims = (array) JWT::decode($idToken, $keys);
        } catch (\Throwable $e) {
            throw new RuntimeException('Apple rejected the sign-in token.');
        }

        if (($claims['iss'] ?? '') !== 'https://appleid.apple.com') {
            throw new RuntimeException('Unexpected token issuer.');
        }
        if (!in_array($claims['aud'] ?? '', $audiences, true)) {
            throw new RuntimeException('Token was issued for a different app.');
        }

        $subject = $claims['sub'] ?? '';
        if ($subject === '') {
            throw new RuntimeException('Token carries no subject.');
        }

        // Apple sends the email only on the very first authorisation. A
        // private-relay address is a real one for our purposes.
        $verified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return new self(
            'apple',
            $subject,
            $verified ? ($claims['email'] ?? null) : null,
            null,
        );
    }

    /** The `kid` a token asks to be verified with, or null if unreadable. */
    private static function keyIdOf(string $idToken): ?string
    {
        $head = explode('.', $idToken)[0] ?? '';
        $json = base64_decode(strtr($head, '-_', '+/'), false);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) && isset($decoded['kid']) ? (string) $decoded['kid'] : null;
    }

    /** @return array<string,\Firebase\JWT\Key> */
    private static function appleKeys(bool $fresh): array
    {
        $cacheKey = 'apple_jwks';
        if ($fresh) {
            Cache::forget($cacheKey);
        }

        $jwks = Cache::remember($cacheKey, now()->addDay(), function (): array {
            $response = Http::timeout(10)->get('https://appleid.apple.com/auth/keys');
            if (!$response->successful()) {
                throw new RuntimeException('Could not reach Apple to verify the token.');
            }

            return $response->json();
        });

        return JWK::parseKeySet($jwks);
    }
}
