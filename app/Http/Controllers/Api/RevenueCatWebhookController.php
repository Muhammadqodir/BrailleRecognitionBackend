<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RevenueCat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RevenueCat's server-to-server notifications.
 *
 * Two properties this must have, because webhooks are delivered *at least*
 * once and out of order:
 *
 *   * Idempotent — `subscription_events.event_id` is unique, and a replayed
 *     event is recorded and ignored rather than processed twice.
 *   * Self-correcting — rather than trusting the event body to describe the
 *     new state, it re-reads the subscriber from RevenueCat. An out-of-order
 *     delivery then cannot leave us believing something stale.
 */
class RevenueCatWebhookController extends Controller
{
    public function __construct(private readonly RevenueCat $revenueCat)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        $expected = (string) config('services.revenuecat.webhook_auth');

        // RevenueCat sends the bare value, with no "Bearer " prefix. hash_equals
        // so a wrong secret cannot be discovered by timing the response.
        if ($expected === '' || !hash_equals($expected, (string) $request->header('Authorization'))) {
            Log::warning('RevenueCat webhook rejected: bad Authorization header');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $event = $request->input('event', []);
        $eventId = (string) ($event['id'] ?? '');
        if ($eventId === '') {
            return response()->json(['message' => 'Missing event id'], 400);
        }

        $rcUserId = (string) ($event['app_user_id'] ?? '');
        $user = $rcUserId !== '' ? User::where('rc_user_id', $rcUserId)->first() : null;

        $inserted = DB::table('subscription_events')->insertOrIgnore([
            'event_id' => $eventId,
            'user_id' => $user?->id,
            'rc_user_id' => $rcUserId,
            'type' => (string) ($event['type'] ?? ''),
            'product_id' => $event['product_id'] ?? null,
            'store' => $event['store'] ?? null,
            'environment' => $event['environment'] ?? null,
            'payload' => json_encode($event),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Already seen. Acknowledge so RevenueCat stops retrying.
        if ($inserted === 0) {
            return response()->json(['message' => 'Duplicate, ignored.']);
        }

        if (!$user) {
            // Worth keeping the event — an app user id we have never seen means
            // either a test event or a user created on a client we have not
            // heard from yet. Neither should return an error and trigger retries.
            Log::info('RevenueCat webhook for unknown app_user_id', ['rc_user_id' => $rcUserId]);
            return response()->json(['message' => 'Recorded, no matching user.']);
        }

        // Re-read rather than trusting the event body — see the class comment.
        $this->revenueCat->sync($user);

        return response()->json(['message' => 'Processed.']);
    }
}
