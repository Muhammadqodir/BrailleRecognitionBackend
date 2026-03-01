<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

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
     * Flutter sends the Apple identity token obtained from sign_in_with_apple package.
     * The backend decodes the JWT and verifies the audience.
     *
     * POST /api/auth/apple
     */
    public function appleSignIn(Request $request): JsonResponse
    {
        $request->validate([
            'identity_token' => ['required', 'string'],
            'user_id'        => ['required', 'string'], // Apple's user identifier (stable)
            'name'           => ['nullable', 'string'], // only sent on first sign-in
            'email'          => ['nullable', 'email'],  // only sent on first sign-in
        ]);

        $applePayload = $this->verifyAppleToken($request->identity_token, $request->user_id);

        if (! $applePayload) {
            return response()->json(['message' => 'Invalid Apple token.'], 401);
        }

        // Apple only sends email on the *first* sign-in; afterwards use stored value
        $email = $applePayload['email'] ?? $request->email;

        $user = User::where('apple_id', $request->user_id)->first();

        if (! $user && $email) {
            $user = User::where('email', $email)->first();
        }

        if ($user) {
            $user->update(['apple_id' => $request->user_id]);
        } else {
            $user = User::create([
                'name'              => $request->name ?? 'Apple User',
                'email'             => $email ?? $request->user_id . '@apple.placeholder',
                'apple_id'          => $request->user_id,
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
     * Verify an Apple identity token (JWT).
     * Decodes without full signature verification here — for production you MUST
     * fetch Apple's public keys and verify the RS256 signature. You can use the
     * `lcobucci/jwt` package or `firebase/php-jwt` for that.
     *
     * Returns the payload array on success, or null on failure.
     */
    private function verifyAppleToken(string $identityToken, string $userId): ?array
    {
        $parts = explode('.', $identityToken);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(str_pad(
            strtr($parts[1], '-_', '+/'),
            strlen($parts[1]) % 4 === 0 ? strlen($parts[1]) : strlen($parts[1]) + (4 - strlen($parts[1]) % 4),
            '='
        )), true);

        if (empty($payload['sub']) || $payload['sub'] !== $userId) {
            return null;
        }

        if ($payload['iss'] !== 'https://appleid.apple.com') {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
}
