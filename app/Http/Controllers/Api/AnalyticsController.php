<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Analytics ingest. Replaces the interim `track.php` on the legacy backend.
 *
 * Deliberately unauthenticated. The whole point is to see the funnel *before*
 * anyone has an account — the first app open and every onboarding step happen
 * with no token at all. A bearer token, when present, is an enrichment rather
 * than a gate.
 */
class AnalyticsController extends Controller
{
    /** Matches the client's batch size. */
    private const MAX_BATCH = 50;
    private const MAX_PROPS_BYTES = 8192;

    public function store(Request $request): JsonResponse
    {
        $events = $request->input('events');

        // The client posts a JSON array; accept it either as a real array or as
        // a JSON string, because http.post form-encodes it.
        if (is_string($events)) {
            $events = json_decode($events, true);
        }
        if (!is_array($events) || $events === []) {
            return response()->json(['message' => 'No events', 'accepted' => 0], 400);
        }

        $events = array_slice($events, 0, self::MAX_BATCH);

        // Resolved by hand rather than through `auth:sanctum`, which would
        // reject the unauthenticated calls this endpoint exists to accept.
        $userId = $this->optionalUser($request)?->id;
        $now = now();
        $rows = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $name = Str::limit((string) ($event['name'] ?? ''), 64, '');
            if ($name === '') {
                continue;
            }

            $props = json_encode($event['props'] ?? new \stdClass());
            if ($props === false || strlen($props) > self::MAX_PROPS_BYTES) {
                $props = '{}';
            }

            $rows[] = [
                // Without a uid we cannot deduplicate, so mint one rather than
                // dropping the event.
                'event_uid' => Str::limit((string) ($event['uid'] ?? ''), 64, '') ?: (string) Str::uuid(),
                'install_id' => Str::limit((string) ($event['install_id'] ?? ''), 64, ''),
                'user_id' => $userId,
                'name' => $name,
                'props' => $props,
                'platform' => Str::limit((string) ($event['platform'] ?? ''), 16, ''),
                'app_version' => Str::limit((string) ($event['app_version'] ?? ''), 32, ''),
                'is_premium' => !empty($event['is_premium']),
                'locale' => Str::limit((string) ($event['locale'] ?? ''), 16, ''),
                'source' => 'client',
                'client_ts' => $this->clientTime($event['ts'] ?? null),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return response()->json(['message' => 'Nothing usable', 'accepted' => 0], 400);
        }

        // insertOrIgnore against the unique event_uid: the client retries failed
        // batches, and without this a flaky connection would inflate every step
        // of the funnel.
        $accepted = DB::table('analytics_events')->insertOrIgnore($rows);

        // Tie past anonymous events to the account once we know it.
        if ($userId && ($rows[0]['install_id'] ?? '') !== '') {
            DB::table('users')->where('id', $userId)
                ->update(['install_id' => $rows[0]['install_id']]);
        }

        return response()->json(['message' => 'Success!', 'accepted' => $accepted]);
    }

    /** The signed-in user if a valid token was sent, otherwise null. */
    private function optionalUser(Request $request): ?\App\Models\User
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return null;
        }
        $token = \Laravel\Sanctum\PersonalAccessToken::findToken($bearer);
        $user = $token?->tokenable;

        return $user instanceof \App\Models\User ? $user : null;
    }

    /**
     * Devices queue events offline and lie about the clock. An unparseable
     * timestamp becomes null rather than poisoning the ordering of a funnel.
     */
    private function clientTime(mixed $ts): ?string
    {
        if ($ts === null || $ts === '') {
            return null;
        }
        try {
            return is_numeric($ts)
                ? \Carbon\Carbon::createFromTimestampMs((int) $ts, 'UTC')->toDateTimeString()
                : \Carbon\Carbon::parse((string) $ts)->utc()->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}
