<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Made-up users, scans, purchases and analytics shaped like production, so the
 * admin dashboard can be worked on locally without a copy of real user data.
 *
 *   php artisan db:seed --class=AdminDemoSeeder
 */
class AdminDemoSeeder extends Seeder
{
    private const ROLES = [
        'Teacher of Visually Impaired Students', 'Parent of a visually impaired child', 'Regular Education Teacher',
        'Language Enthusiast', 'Special Education Department', 'Learning braille myself', 'Something else', null,
    ];

    private const LOCALES = [
        'en-US' => 18, 'es-US' => 7, 'pt-BR' => 5, 'en-GB' => 5, 'es-MX' => 4, 'fr-FR' => 4, 'ru-RU' => 3,
        'es-ES' => 3, 'de-DE' => 3, 'en' => 2, 'ar-SA' => 2, 'pl-PL' => 2, 'uz-UZ' => 2, 'en-IN' => 3, 'it-IT' => 1,
        'tr-TR' => 1, 'en-CA' => 2, 'en-AU' => 1, 'id-ID' => 1, 'ko-KR' => 1,
    ];

    private const LANGS = ['EN' => 50, 'EN2' => 18, 'DE' => 4, 'RU' => 3, 'ES' => 6, 'PL' => 2, 'UZ' => 2, 'PT' => 4, 'AR' => 3, 'FR' => 3, 'GR' => 1];

    private const SAMPLE_TEXT = [
        'The quick brown fox jumps over the lazy dog.', 'Chapter one. It was a bright cold day in April.',
        'Push to open', 'Room 204 — Library', 'Hola, ¿cómo estás?', 'Elevator floor 3', 'Exit',
        'Welcome to the museum. Please do not touch the exhibits.', 'Bonjour tout le monde', 'Guten Tag',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('AdminDemoSeeder writes fake data and must never run in production.');
        }

        mt_srand(42);
        $now = now();

        foreach (range(1, 900) as $i) {
            $legacy = $i <= 420;
            $created = $legacy
                ? Carbon::instance($now)->subDays(mt_rand(60, 620))->subMinutes(mt_rand(0, 1440))
                : Carbon::instance($now)->subDays($this->skewedDays(30))->subMinutes(mt_rand(0, 1440));
            $provider = $legacy ? 'email' : $this->pick(['anonymous' => 80, 'google' => 12, 'apple' => 4, 'email' => 4]);
            $first = Str::of(fake()->firstName())->ascii();
            $named = $provider !== 'anonymous';
            $lastSeen = mt_rand(0, 100) < 55 ? Carbon::instance($now)->subDays($this->skewedDays(40))->subMinutes(mt_rand(0, 1440)) : null;

            $user = User::forceCreate([
                'install_id' => (string) Str::uuid(),
                'rc_user_id' => '$RCAnonymousID:'.Str::random(32),
                'name' => $named ? $first.' '.Str::of(fake()->lastName())->ascii() : null,
                'email' => $named ? Str::lower($first).mt_rand(10, 999).'@example.com' : null,
                'auth_provider' => $provider,
                'role' => self::ROLES[array_rand(self::ROLES)],
                'use_case' => $this->pick(['Homework and schoolwork' => 3, 'Reading signs and labels' => 2, 'Learning braille' => 2]),
                'legacy_id' => $legacy ? $i : null,
                'last_seen_at' => $lastSeen && $lastSeen->lt($created) ? $created : $lastSeen,
                'created_at' => $created,
                'updated_at' => $created,
            ]);

            $this->seedTranslations($user, $legacy, $created);
            $this->seedSubscription($user, $created);
            if (! $legacy) {
                $this->seedEvents($user, $created);
            }
        }
    }

    private function seedTranslations(User $user, bool $legacy, Carbon $created): void
    {
        $rows = [];
        foreach (range(1, mt_rand(0, 100) < 35 ? 0 : mt_rand(1, 9)) as $n) {
            $at = $legacy && mt_rand(0, 100) < 85
                ? (clone $created)->addDays(mt_rand(0, 40))
                : now()->subDays($this->skewedDays(30))->subMinutes(mt_rand(0, 1440));
            if ($at->lt($created)) {
                $at = (clone $created)->addMinutes(mt_rand(1, 600));
            }
            if ($at->gt(now())) {
                $at = now()->subMinutes(mt_rand(1, 30));
            }
            $device = ! $legacy && $at->gt(now()->subDays(20)) && mt_rand(0, 100) < 82;
            $empty = mt_rand(0, 100) < ($device ? 7 : 12);
            $photo = Str::uuid().'.jpg';

            $rows[] = [
                'user_id' => $user->id,
                'result_braille' => $empty ? '' : '⠠⠞⠓⠑ ⠟⠥⠊⠉⠅',
                'result' => $empty ? '' : self::SAMPLE_TEXT[array_rand(self::SAMPLE_TEXT)],
                'result_json' => $device ? json_encode([
                    'engine' => 'device',
                    'backend' => mt_rand(0, 1) ? 'coreml' : 'tflite',
                    'model' => 'yolo11n-1024+dotnet-w16 (2026-09-29)',
                    'width' => 1920, 'height' => 2560,
                    'timings' => ['prepare_ms' => mt_rand(300, 600), 'detect_ms' => mt_rand(150, 900), 'classify_ms' => mt_rand(20, 80), 'text_ms' => mt_rand(40, 120)],
                    'lang_guess' => ['best' => 'EN', 'margin' => mt_rand(10, 200)],
                    'lang_source' => 'detected', 'turn' => [0, 0, 0, 90, 180][mt_rand(0, 4)],
                ]) : null,
                'input_file' => $device ? 'scans/'.$photo : 'uploads/'.$photo,
                'result_marked' => $device ? 'https://braillerecognition.alfocus.uz/storage/scans/'.$photo : 'results/'.$photo,
                'lang' => $this->pick(self::LANGS),
                'is_fav' => mt_rand(0, 100) < 6,
                'rating' => mt_rand(0, 100) < 8 ? (mt_rand(0, 100) < 70 ? 1 : -1) : null,
                'legacy_id' => $legacy ? mt_rand(1, 10_000_000) : null,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        if ($rows !== []) {
            DB::table('translations')->insertOrIgnore($rows);
        }
    }

    private function seedSubscription(User $user, Carbon $created): void
    {
        $roll = mt_rand(0, 1000);
        $base = ['user_id' => $user->id, 'rc_user_id' => $user->rc_user_id, 'entitlement' => 'Premium', 'created_at' => $created, 'updated_at' => now()->subDays(mt_rand(0, 20))];

        if ($roll < 960 || $user->legacy_id !== null) {
            DB::table('subscriptions')->insert([...$base, 'status' => 'inactive', 'environment' => 'PRODUCTION']);

            return;
        }

        $store = mt_rand(0, 100) < 55 ? 'app_store' : 'play_store';
        $trial = mt_rand(0, 100) < 65;
        $status = $this->pick(['active' => 6, 'cancelled' => 4, 'expired' => 1, 'billing_issue' => 1]);
        $purchased = now()->subDays(mt_rand(0, 25));
        $expires = (clone $purchased)->addDays($trial ? 3 : (mt_rand(0, 1) ? 30 : 365));

        DB::table('subscriptions')->insert([...$base,
            'product_id' => mt_rand(0, 1) ? 'braille_premium_yearly' : 'braille_premium_monthly',
            'store' => $store,
            'period_type' => $trial ? 'trial' : 'normal',
            'status' => $status,
            'is_active' => $status !== 'expired',
            'will_renew' => $status === 'active',
            'environment' => mt_rand(0, 100) < 92 ? 'PRODUCTION' : 'SANDBOX',
            'purchased_at' => $purchased,
            'expires_at' => $expires,
        ]);

        foreach (['INITIAL_PURCHASE', ...($status === 'cancelled' ? ['CANCELLATION'] : []), ...($status === 'expired' ? ['EXPIRATION'] : [])] as $k => $type) {
            DB::table('subscription_events')->insert([
                'event_id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'rc_user_id' => $user->rc_user_id,
                'type' => $type,
                'product_id' => $trial ? 'braille_premium_monthly' : 'braille_premium_yearly',
                'store' => strtoupper($store),
                'environment' => 'PRODUCTION',
                'created_at' => (clone $purchased)->addHours($k * 30),
                'updated_at' => (clone $purchased)->addHours($k * 30),
            ]);
        }
    }

    /** A first open and some of the funnel after it, dropping off the way production does. */
    private function seedEvents(User $user, Carbon $created): void
    {
        $platform = mt_rand(0, 100) < 77 ? 'android' : 'ios';
        $locale = $this->pick(self::LOCALES);
        $steps = [
            ['app_opened', ['is_first_open' => true, 'is_anonymous' => true], 100],
            ['onboarding_started', null, 95],
            ['onboarding_completed', null, 80],
            ['paywall_viewed', ['placement' => 'onboarding'], 95],
            ['scan_started', ['source' => 'camera'], 75],
            ['scan_completed', ['lang' => 'EN'], 70],
            ['purchase_started', null, 22],
            ['trial_started', null, 45],
        ];

        $at = clone $created;
        $rows = [];
        foreach ($steps as [$name, $props, $chance]) {
            if (mt_rand(0, 100) > $chance) {
                break;
            }
            $at = (clone $at)->addSeconds(mt_rand(5, 400));
            $rows[] = $this->event($user, $name, $props, $platform, $locale, $at);
        }

        // Later sessions, so "active installs" has a shape beyond day one.
        foreach (range(1, mt_rand(0, 6)) as $n) {
            $later = (clone $created)->addDays(mt_rand(1, 20))->addMinutes(mt_rand(0, 1440));
            if ($later->lt(now())) {
                $rows[] = $this->event($user, 'app_opened', ['is_first_open' => false], $platform, $locale, $later);
            }
        }

        DB::table('analytics_events')->insert($rows);
    }

    private function event(User $user, string $name, ?array $props, string $platform, string $locale, Carbon $at): array
    {
        return [
            'event_uid' => (string) Str::uuid(),
            'install_id' => $user->install_id,
            'user_id' => $user->id,
            'name' => $name,
            'props' => $props ? json_encode($props) : null,
            'platform' => $platform,
            'app_version' => $this->pick(['4.0.3' => 6, '4.0.2' => 3, '4.0.0' => 1]),
            'is_premium' => false,
            'locale' => $locale,
            'source' => 'client',
            'client_ts' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    /** Days ago, weighted toward recent, so growth looks like the last month did. */
    private function skewedDays(int $max): int
    {
        return (int) floor($max * (mt_rand(0, 1000) / 1000) ** 1.6);
    }

    /** @param array<string|int, int> $weights */
    private function pick(array $weights): string
    {
        $roll = mt_rand(1, array_sum($weights));
        foreach ($weights as $value => $weight) {
            if (($roll -= $weight) <= 0) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }
}
