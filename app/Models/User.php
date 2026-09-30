<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'apple_id',
        'avatar',
        'auth_provider',
        'install_id',
        'rc_user_id',
        'role',
        'use_case',
        'legacy_id',
        'legacy_token',
        'last_seen_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // An auth credential for the legacy bridge — never serialise it.
        'legacy_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // Translations go with the user through the foreign key's cascade,
        // which fires no model events, so their photos are removed here.
        static::deleting(function (User $user) {
            $user->translations()->where('input_file', 'like', Translation::SCAN_DIR.'/%')
                ->each(fn (Translation $t) => $t->deleteStoredPhoto());
            ScanAttempt::where('user_id', $user->id)->whereNotNull('photo')
                ->each(fn (ScanAttempt $a) => $a->deletePhoto());
        });
    }

    public function translations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Translation::class);
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->where('entitlement', 'Premium');
    }

    /**
     * Whether this user has an active subscription or trial.
     *
     * A trial counts as premium here: they are entitled to everything until
     * it lapses, and the store tells us when that happens.
     */
    public function isPremium(): bool
    {
        $sub = $this->relationLoaded('subscription')
            ? $this->getRelation('subscription')
            : $this->subscription()->first();

        return $sub !== null && $sub->grantsAccess();
    }

    /**
     * Photo translations left before the paywall.
     *
     * Counted from history rather than a counter, so it cannot drift: every
     * scan that found braille is filed there, by the app or by the OCR host.
     * Empty results are the scans that found nothing, and do not count.
     */
    public function freeScansLeft(): int
    {
        $used = $this->translations()
            ->whereNotNull('result')
            ->whereRaw("TRIM(result) <> ''")
            ->count();

        return max(0, (int) config('services.revenuecat.free_scans', 5) - $used);
    }

    public function isAnonymous(): bool
    {
        return $this->auth_provider === 'anonymous';
    }
}
