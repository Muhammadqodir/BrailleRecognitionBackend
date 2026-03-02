<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;

class AuthController extends Controller
{
    /**
     * Register a new user.
     *
     * POST /api/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name'          => $validated['name'],
            'email'         => $validated['email'],
            'password'      => Hash::make($validated['password']),
            'auth_provider' => 'email',
        ]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    /**
     * Log in with email & password.
     *
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Log out (revoke current token).
     *
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Return the authenticated user's profile.
     *
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    /**
     * Sign in / register with Google (ID token flow).
     *
     * Flutter sends the Google ID token obtained from google_sign_in package.
     * The backend verifies it with Google's tokeninfo endpoint.
     *
     * POST /api/auth/google
     */
    public function googleSignIn(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        // Verify the Google ID token with Google's public endpoint
        $googleUser = $this->verifyGoogleToken($request->id_token);

        if (! $googleUser) {
            return response()->json(['message' => 'Invalid Google token.'], 401);
        }

        $user = User::firstOrCreate(
            ['google_id' => $googleUser['sub']],
            [
                'name'          => $googleUser['name'] ?? 'Google User',
                'email'         => $googleUser['email'],
                'avatar'        => $googleUser['picture'] ?? null,
                'auth_provider' => 'google',
                'email_verified_at' => now(),
            ]
        );

        // Sync email if user previously registered with email
        if (! $user->google_id) {
            $user->update(['google_id' => $googleUser['sub']]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'message' => 'Google sign-in successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Sign in / register with Apple (identity token flow).
     *
     * Flow: Flutter → Apple SDK → identityToken → POST here → verify → login
     *
     * The Flutter app gets a signed identityToken (RS256 JWT) directly from
     * Apple via the sign_in_with_apple package and sends it here.
     * No OAuth redirect or client secret is required for this flow.
     *
     * POST /api/auth/apple
     * Body: { identity_token, name?, email? }
     */
    public function appleSignIn(Request $request): JsonResponse
    {
        $request->validate([
            'identity_token' => ['required', 'string'],
            'name'           => ['nullable', 'string'], // only provided on first sign-in
            'email'          => ['nullable', 'email'],  // only provided on first sign-in
        ]);

        // Verify RS256 signature against Apple's public JWKS and extract claims
        try {
            $applePayload = $this->verifyAppleIdentityToken($request->identity_token);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Invalid Apple identity token: ' . $e->getMessage()], 401);
        }

        // `sub` in the verified JWT is the stable Apple user identifier
        $appleUserId = $applePayload['sub'];

        // Apple only includes email on the very first sign-in
        $email = $applePayload['email'] ?? $request->input('email');

        // Look up existing user by apple_id, fall back to email match
        $user = User::where('apple_id', $appleUserId)->first()
            ?? ($email ? User::where('email', $email)->first() : null);

        if ($user) {
            // Link apple_id if the account was found by email
            if (! $user->apple_id) {
                $user->update(['apple_id' => $appleUserId]);
            }
        } else {
            // New user — create account
            $user = User::create([
                'name'              => $request->input('name') ?? 'Apple User',
                'email'             => $email ?? ($appleUserId . '@privaterelay.appleid.com'),
                'apple_id'          => $appleUserId,
                'auth_provider'     => 'apple',
                'email_verified_at' => now(),
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'message' => 'Apple sign-in successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Verify a Google ID token by calling Google's tokeninfo endpoint.
     * Returns the payload array on success, or null on failure.
     */
    private function verifyGoogleToken(string $idToken): ?array
    {
        $url      = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);
        $response = @file_get_contents($url);

        if (! $response) {
            return null;
        }

        $payload = json_decode($response, true);

        // Check for required fields
        if (empty($payload['sub']) || empty($payload['email'])) {
            return null;
        }

        // Verify email is verified by Google
        if (empty($payload['email_verified']) || $payload['email_verified'] !== 'true') {
            return null;
        }

        // Verify the audience (aud) matches one of our Google Client IDs
        $validClientIds = array_filter([
            env('GOOGLE_CLIENT_ID_ANDROID'),
            env('GOOGLE_CLIENT_ID_WEB'),
            env('GOOGLE_CLIENT_ID_IOS'),
        ]);

        if (! empty($validClientIds)) {
            if (! in_array($payload['aud'], $validClientIds, true)) {
                return null;
            }
        }

        return $payload;
    }

    /**
     * Verify an Apple identityToken by:
     *  1. Fetching Apple's public JWKS from https://appleid.apple.com/auth/keys
     *  2. Verifying the RS256 signature with firebase/php-jwt
     *  3. Asserting issuer  = https://appleid.apple.com
     *  4. Asserting audience = APPLE_CLIENT_ID (your app bundle ID)
     *
     * JWT::decode() also automatically rejects expired tokens (exp claim).
     *
     * @throws \RuntimeException|\Firebase\JWT\ExpiredException|\Firebase\JWT\SignatureInvalidException
     * @return array<string, mixed>
     */
    private function verifyAppleIdentityToken(string $identityToken, ?string $rawNonce = null): array
    {
        $jwks = Cache::remember('apple_jwks', 3600, function () {
            $response = Http::timeout(5)->get('https://appleid.apple.com/auth/keys');

            if (!$response->successful()) {
                throw new \RuntimeException('Could not fetch Apple public keys.');
            }

            return $response->json();
        });

        $keySet = JWK::parseKeySet($jwks);

        try {
            $decoded = (array) JWT::decode($identityToken, $keySet);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid Apple token.');
        }

        // ISS
        if (($decoded['iss'] ?? '') !== 'https://appleid.apple.com') {
            throw new \RuntimeException('Apple token issuer mismatch.');
        }

        // AUD
        $expectedAud = config('services.apple.client_id');
        $aud = is_array($decoded['aud']) ? $decoded['aud'] : [$decoded['aud']];

        if ($expectedAud && !in_array($expectedAud, $aud, true)) {
            throw new \RuntimeException('Apple token audience mismatch.');
        }

        // SUB
        if (empty($decoded['sub'])) {
            throw new \RuntimeException('Apple token missing sub.');
        }

        // NONCE (optional)
        if ($rawNonce) {
            $expectedNonce = hash('sha256', $rawNonce);

            if (($decoded['nonce'] ?? null) !== $expectedNonce) {
                throw new \RuntimeException('Invalid nonce.');
            }
        }

        // EMAIL VERIFIED
        if (isset($decoded['email_verified']) && $decoded['email_verified'] !== true) {
            throw new \RuntimeException('Email not verified.');
        }

        return $decoded;
    }
}
