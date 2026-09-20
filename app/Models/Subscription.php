<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Our cached view of what RevenueCat says a user bought.
 *
 * RevenueCat is the source of truth; this exists so an entitlement check is one
 * indexed read rather than an HTTP call on the critical path of a scan.
 */
class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'rc_user_id', 'entitlement', 'product_id', 'store',
        'period_type', 'status', 'is_active', 'will_renew', 'environment',
        'original_transaction_id', 'purchased_at', 'expires_at',
        'unsubscribe_detected_at', 'billing_issue_detected_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'will_renew' => 'boolean',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'unsubscribe_detected_at' => 'datetime',
            'billing_issue_detected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this row grants access right now.
     *
     * A null expiry is a non-expiring grant. Anything else gets a grace window,
     * so a renewal that lands late does not lock out someone who has paid — the
     * store retries for hours and we should not punish them for it.
     */
    public function grantsAccess(int $graceSeconds = 3600): bool
    {
        if (!$this->is_active) {
            return false;
        }
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->addSeconds($graceSeconds)->isFuture();
    }

    /** A trial that has not converted yet. Counted separately from revenue. */
    public function isTrial(): bool
    {
        return strtoupper((string) $this->period_type) === 'TRIAL';
    }
}
