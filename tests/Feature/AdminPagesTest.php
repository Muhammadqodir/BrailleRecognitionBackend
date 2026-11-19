<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);
    }

    /** Like the app, which sends events before it has an account: tied by install, user_id left empty. */
    private function event(User $user, string $name, array $props = [], string $locale = 'pt-BR', string $platform = 'android', ?Carbon $at = null): void
    {
        if ($user->install_id === null) {
            $user->forceFill(['install_id' => 'install-'.$user->id])->save();
        }

        DB::table('analytics_events')->insert([
            'event_uid' => (string) Str::uuid(),
            'install_id' => $user->install_id,
            'user_id' => null,
            'name' => $name,
            'props' => json_encode($props),
            'platform' => $platform,
            'locale' => $locale,
            'created_at' => $at ?? now(),
            'updated_at' => $at ?? now(),
        ]);
    }

    /** A RevenueCat webhook event as we store it. */
    private function storeEvent(string $type, string $store, array $payload, Carbon $at, string $environment = 'PRODUCTION'): void
    {
        DB::table('subscription_events')->insert([
            'event_id' => (string) Str::uuid(),
            'rc_user_id' => $payload['original_app_user_id'],
            'type' => $type,
            'product_id' => 'braille_premium_monthly',
            'store' => $store,
            'environment' => $environment,
            'payload' => json_encode(['type' => $type, 'store' => $store, 'environment' => $environment, ...$payload]),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function subscribe(User $user, string $periodType, array $attributes = []): Subscription
    {
        return Subscription::create([
            'user_id' => $user->id,
            'rc_user_id' => 'rc-'.$user->id,
            'store' => 'play_store',
            'period_type' => $periodType,
            'status' => 'active',
            'is_active' => true,
            'environment' => 'PRODUCTION',
            'expires_at' => now()->addMonth(),
            ...$attributes,
        ]);
    }

    public function test_dashboard_reports_the_period_against_the_one_before(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 12:00:00'));

        $paying = User::factory()->create(['auth_provider' => 'google', 'role' => 'Teacher']);
        $anonymous = User::factory()->create(['auth_provider' => 'anonymous']);
        $iphone = User::factory()->create(['role' => 'Teacher']);
        $nowhere = User::factory()->create(['role' => 'Parent']);
        $lastMonth = User::factory()->create();
        $this->subscribe($paying, 'normal');
        $this->subscribe($anonymous, 'trial');
        // Neither a sandbox purchase nor an expired one is a paying subscriber.
        $this->subscribe(User::factory()->create(), 'normal', ['environment' => 'SANDBOX']);
        $this->subscribe(User::factory()->create(), 'normal', ['expires_at' => now()->subDays(2)]);

        $paying->translations()->create(['lang' => 'EN', 'result' => 'Hello', 'result_json' => json_encode(['engine' => 'device']), 'rating' => 1]);
        $paying->translations()->create(['lang' => 'EN', 'result' => '', 'rating' => -1]);
        $anonymous->translations()->create(['lang' => 'DE', 'result' => 'Hallo']);

        // Four new installs in the 30 days (three today), one in the 30 before.
        $this->event($paying, 'app_opened', ['is_first_open' => true]);
        $this->event($paying, 'scan_completed');
        $this->event($anonymous, 'app_opened', ['is_first_open' => true]);
        $this->event($nowhere, 'app_opened', ['is_first_open' => true], 'en');
        $this->event($iphone, 'app_opened', ['is_first_open' => true], 'de-DE', 'ios', now()->subDays(5));
        $this->event($lastMonth, 'app_opened', ['is_first_open' => true], 'pt-BR', 'android', now()->subDays(40));

        // RevenueCat: a trial today, a converted trial, a lifetime buy, a refund
        // today, a purchase last month, and a sandbox one that must not count.
        $this->storeEvent('INITIAL_PURCHASE', 'PLAY_STORE', ['original_app_user_id' => 'rc-a', 'period_type' => 'TRIAL', 'price' => 0, 'country_code' => 'BR'], now());
        $this->storeEvent('RENEWAL', 'APP_STORE', ['original_app_user_id' => 'rc-b', 'period_type' => 'NORMAL', 'price' => 29.99, 'is_trial_conversion' => true, 'takehome_percentage' => 0.7, 'country_code' => 'US'], now()->subDays(3));
        $this->storeEvent('NON_RENEWING_PURCHASE', 'APP_STORE', ['original_app_user_id' => 'rc-c', 'price' => 49.99, 'takehome_percentage' => 0.85, 'country_code' => 'DE'], now()->subDays(2));
        $this->storeEvent('CANCELLATION', 'APP_STORE', ['original_app_user_id' => 'rc-b', 'price' => -29.99, 'takehome_percentage' => 0.7, 'cancel_reason' => 'CUSTOMER_SUPPORT'], now());
        $this->storeEvent('INITIAL_PURCHASE', 'PLAY_STORE', ['original_app_user_id' => 'rc-d', 'period_type' => 'NORMAL', 'price' => 4.99, 'country_code' => 'BR'], now()->subDays(40));
        $this->storeEvent('INITIAL_PURCHASE', 'APP_STORE', ['original_app_user_id' => 'rc-e', 'period_type' => 'NORMAL', 'price' => 4.99], now(), 'SANDBOX');

        // The App Store has reported up to two days ago; Google Play not at all.
        foreach (['2026-11-16', '2026-11-17'] as $day) {
            foreach (['impressions' => 100, 'page_views' => 40, 'downloads' => 10] as $metric => $value) {
                DB::table('store_stats')->insert(['source' => 'asc:test', 'date' => $day, 'platform' => 'ios', 'metric' => $metric, 'country' => 'US', 'value' => $value, 'updated_at' => now()]);
            }
        }

        $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('period.key', '30d')
                ->where('period.from', '2026-10-21')
                ->where('period.previousFrom', '2026-09-21')
                ->where('period.previousTo', '2026-10-20')
                // active
                ->where('kpis.0.value', 4)->where('kpis.0.previous', 1)->where('kpis.0.today', 3)
                ->has('kpis.0.series', 30)
                // new users
                ->where('kpis.1.value', 4)->where('kpis.1.previous', 1)->where('kpis.1.today', 3)
                // paid: the converted trial and the lifetime buy, once each
                ->where('kpis.2.key', 'paid')
                ->where('kpis.2.value', 2)->where('kpis.2.previous', 1)->where('kpis.2.today', 0)
                ->where('kpis.2.now', 1)
                // trials
                ->where('kpis.3.value', 1)->where('kpis.3.today', 1)->where('kpis.3.now', 1)
                // translations
                ->where('kpis.4.value', 3)->where('kpis.4.previous', 0)->where('kpis.4.today', 3)
                // earnings: 29.99 + 49.99 - 29.99 refunded; after the store's cut 42.49
                ->where('kpis.5.key', 'earnings')
                ->where('kpis.5.value', 49.99)->where('kpis.5.previous', 4.99)->where('kpis.5.today', -29.99)
                ->where('kpis.5.proceeds', 42.49)
                ->has('growth', 30)
                ->where('growth.29', ['date' => '2026-11-19', 'installsIos' => 0, 'installsAndroid' => 3, 'trialsIos' => 0, 'trialsAndroid' => 1])
                ->where('growth.24.installsIos', 1)
                ->where('translationVolume.total', 3)
                ->where('translationVolume.found', 2)
                ->where('translationVolume.points.29', ['date' => '2026-11-19', 'found' => 2, 'empty' => 1])
                ->where('countries.rows.0', ['code' => 'BR', 'installs' => 2, 'ios' => 0, 'android' => 2, 'previous' => 1, 'trials' => 1])
                ->where('countries.rows.1.code', 'DE')
                ->where('countries.unplaced', 1)
                ->where('countries.total', 4)
                ->where('newUsersBy.platform', [['key' => 'ios', 'count' => 1, 'previous' => 0], ['key' => 'android', 'count' => 3, 'previous' => 1]])
                ->where('newUsersBy.role', [['key' => 'Teacher', 'count' => 2], ['key' => 'Parent', 'count' => 1], ['key' => null, 'count' => 1]])
                ->where('newUsersBy.language.0', ['key' => 'pt', 'count' => 2])
                // iOS stops on the store's last day; Android has no store data yet
                ->where('funnel.platforms.0.platform', 'ios')
                ->where('funnel.platforms.0.to', '2026-11-17')
                ->where('funnel.platforms.0.steps', [
                    ['key' => 'impressions', 'count' => 200],
                    ['key' => 'page_views', 'count' => 80],
                    ['key' => 'downloads', 'count' => 20],
                    ['key' => 'first_opens', 'count' => 1],
                    ['key' => 'trials', 'count' => 0],
                    ['key' => 'paid', 'count' => 2],
                ])
                ->where('funnel.platforms.1.hasStoreData', false)
                ->where('funnel.platforms.1.to', '2026-11-19')
                ->where('funnel.platforms.1.steps.1.count', null)
                ->where('funnel.platforms.1.steps.3.count', 3)
                ->where('funnel.platforms.1.steps.4.count', 1)
                ->where('brailleLanguages.0.lang', 'EN')
                ->has('subscriptionEvents', 6)
            );
    }

    public function test_dashboard_period_can_be_a_preset_or_custom_dates(): void
    {
        $this->travelTo(Carbon::parse('2026-11-19 12:00:00'));
        $this->actingAs($this->admin, 'admin');

        $this->get(route('admin.dashboard', ['range' => '7d']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.key', '7d')
                ->where('period.from', '2026-11-13')
                ->where('period.previousTo', '2026-11-12')
                ->has('growth', 7)
            );

        // Reversed dates are swapped and a range past today stops today.
        $this->get(route('admin.dashboard', ['from' => '2026-12-31', 'to' => '2026-11-01']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.key', 'custom')
                ->where('period.from', '2026-11-01')
                ->where('period.to', '2026-11-19')
                ->where('period.days', 19)
                ->where('period.previousFrom', '2026-10-13')
                ->where('period.includesToday', true)
            );

        // A year is charted by week: the first bucket starts on the period's
        // first day, the rest on Mondays.
        $this->get(route('admin.dashboard', ['range' => '12m']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('period.bucket', 'week')
                ->where('growth.0.date', '2025-11-20')
                ->where('growth.1.date', '2025-11-24')
            );

        $this->get(route('admin.dashboard', ['range' => 'nonsense']))
            ->assertInertia(fn (Assert $page) => $page->where('period.key', '30d'));
    }

    public function test_users_can_be_filtered_by_plan_role_and_search(): void
    {
        // Fixed names and emails: a random one could contain the ID searched for below.
        $paying = User::factory()->create(['name' => 'Paying Person', 'email' => 'paying@example.com', 'role' => 'Teacher']);
        $trial = User::factory()->create(['name' => 'Trial Person', 'email' => 'trial@example.com', 'role' => null]);
        User::factory()->create(['name' => 'Free Person', 'email' => 'free@example.com', 'role' => 'Parent']);
        $this->subscribe($paying, 'normal');
        $this->subscribe($trial, 'trial');

        $total = fn (array $query) => $this->actingAs($this->admin, 'admin')
            ->get(route('admin.users', $query))
            ->assertOk()
            ->viewData('page')['props']['users']['total'];

        $this->assertSame(1, $total(['plan' => ['paid']]));
        $this->assertSame(1, $total(['plan' => ['trial']]));
        $this->assertSame(1, $total(['plan' => ['free']]));
        $this->assertSame(1, $total(['role' => ['unknown']]));
        $this->assertSame(2, $total(['role' => ['Teacher', 'Parent']]));
        $this->assertSame(1, $total(['search' => 'Paying']));
        $this->assertSame(1, $total(['search' => (string) $trial->id]));
    }

    public function test_users_and_subscriptions_show_and_filter_by_country(): void
    {
        $brazil = User::factory()->create();
        $nowhere = User::factory()->create();
        User::factory()->create();
        $this->event($brazil, 'app_opened');
        $this->event($nowhere, 'app_opened', [], 'en');
        $this->subscribe($brazil, 'trial');

        $props = fn (string $route, array $query) => $this->actingAs($this->admin, 'admin')
            ->get(route($route, $query))
            ->assertOk()
            ->viewData('page')['props'];

        $users = $props('admin.users', ['country' => ['BR']]);
        $this->assertSame(1, $users['users']['total']);
        $this->assertSame('BR', $users['users']['data'][0]['country']);
        $this->assertSame(['BR' => 1], $users['countryCounts']);
        $this->assertSame(2, $props('admin.users', ['country' => ['unknown']])['users']['total']);

        $subscriptions = $props('admin.subscriptions', ['country' => ['BR']]);
        $this->assertSame(1, $subscriptions['subscriptions']['total']);
        $this->assertSame('BR', $subscriptions['subscriptions']['data'][0]['country']);
        $this->assertSame(0, $props('admin.subscriptions', ['country' => ['US']])['subscriptions']['total']);
    }

    public function test_user_detail_returns_profile_and_recent_translations(): void
    {
        $user = User::factory()->create();
        $this->event($user, 'app_opened');
        $user->translations()->create(['lang' => 'EN', 'result' => 'Hi', 'result_marked' => 'results/a.marked.jpg']);

        $this->actingAs($this->admin, 'admin')->getJson(route('admin.users.show', $user))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.country', 'BR')
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.events_count', 1)
            ->assertJsonPath('data.translations.0.engine', 'server')
            ->assertJsonPath('data.translations.0.image_url', 'http://109.123.251.227:8000/results/a.marked.jpg');
    }

    public function test_translations_can_be_filtered_by_engine_result_and_rating(): void
    {
        $user = User::factory()->create();
        $user->translations()->create(['lang' => 'EN', 'result' => 'Read', 'result_json' => json_encode(['engine' => 'device']), 'rating' => 1]);
        $user->translations()->create(['lang' => 'EN', 'result' => '']);
        $user->translations()->create(['lang' => 'DE', 'result' => 'Gelesen', 'rating' => -1]);

        $total = fn (array $query) => $this->actingAs($this->admin, 'admin')
            ->get(route('admin.translations', $query))
            ->assertOk()
            ->viewData('page')['props']['translations']['total'];

        $this->assertSame(1, $total(['engine' => ['device']]));
        $this->assertSame(2, $total(['engine' => ['server']]));
        $this->assertSame(1, $total(['result' => ['empty']]));
        $this->assertSame(2, $total(['result' => ['read']]));
        $this->assertSame(1, $total(['rating' => ['down']]));
        $this->assertSame(1, $total(['lang' => ['DE']]));
        $this->assertSame(1, $total(['search' => 'Gelesen']));
        $this->assertSame(3, $total(['user_id' => $user->id]));
    }

    public function test_translation_detail_reports_the_recogniser(): void
    {
        $translation = User::factory()->create()->translations()->create([
            'lang' => 'EN',
            'result' => 'Hi',
            'result_json' => json_encode(['engine' => 'device', 'backend' => 'coreml', 'width' => 10, 'height' => 20, 'timings' => ['detect_ms' => 5],
                'cells' => [[1, 2, 3, 4, 7, 0.9, 0.5]]]),
            'result_marked' => 'https://braillerecognition.alfocus.uz/storage/scans/a.jpg',
        ]);

        $this->actingAs($this->admin, 'admin')->getJson(route('admin.translations.show', $translation))
            ->assertOk()
            ->assertJsonPath('data.engine', 'device')
            ->assertJsonPath('data.backend', 'coreml')
            ->assertJsonPath('data.size', '10×20')
            ->assertJsonPath('data.timings.detect_ms', 5)
            ->assertJsonPath('data.photo_url', 'https://braillerecognition.alfocus.uz/storage/scans/a.jpg')
            ->assertJsonPath('data.marked_url', null)
            ->assertJsonPath('data.cells.0', [1, 2, 3, 4, 7]);
    }

    public function test_a_phone_scan_can_be_read_in_another_language_without_saving_it(): void
    {
        $reader = tempnam(sys_get_temp_dir(), 'reader');
        chmod($reader, 0755);
        config(['braille.reader.bin' => $reader]);
        Process::fake(['*' => Process::result(json_encode(['RU' => ['text' => 'привет', 'braille' => '⠏⠗']]))]);

        $translation = User::factory()->create()->translations()->create([
            'lang' => 'EN',
            'result' => 'hello',
            'result_json' => json_encode(['engine' => 'device', 'width' => 10, 'height' => 20, 'cells' => [[1, 2, 3, 4, 7]],
                'lang_guess' => ['best' => 'EN', 'margin' => 2.5, 'scores' => ['RU' => -5.0, 'EN' => -3.9]]]),
        ]);

        $this->actingAs($this->admin, 'admin')->getJson(route('admin.translations.show', $translation))
            ->assertJsonPath('data.lang_scores.0.lang', 'EN')
            ->assertJsonPath('data.detect_margin', 2.5)
            ->assertJsonPath('data.readable_langs.0', 'EN');

        $this->actingAs($this->admin, 'admin')->getJson(route('admin.translations.read', [$translation, 'lang' => 'RU']))
            ->assertOk()
            ->assertJsonPath('data.text', 'привет');

        Process::assertRan(fn ($process) => $process->command[0] === $reader
            && json_decode($process->input, true)['langs'] === ['RU']
            && json_decode($process->input, true)['cells'] === [[1, 2, 3, 4, 7]]);
        $this->assertSame('hello', $translation->fresh()->result);

        $this->actingAs($this->admin, 'admin')->getJson(route('admin.translations.read', [$translation, 'lang' => 'XX']))
            ->assertUnprocessable();
        unlink($reader);
    }

    public function test_scans_without_cells_cannot_be_read_again(): void
    {
        $translation = User::factory()->create()->translations()->create(['lang' => 'EN', 'result' => 'hello', 'result_marked' => 'results/a.jpg']);

        $this->actingAs($this->admin, 'admin')->getJson(route('admin.translations.show', $translation))
            ->assertJsonPath('data.readable_langs', []);
        $this->actingAs($this->admin, 'admin')->getJson(route('admin.translations.read', [$translation, 'lang' => 'RU']))
            ->assertUnprocessable();
    }

    public function test_subscriptions_leave_out_rows_with_no_purchase(): void
    {
        $this->subscribe(User::factory()->create(), 'trial');
        Subscription::create(['user_id' => User::factory()->create()->id, 'rc_user_id' => 'rc-x', 'status' => 'inactive']);

        $this->actingAs($this->admin, 'admin')->get(route('admin.subscriptions', ['period_type' => ['trial']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('subscriptions')
                ->where('subscriptions.total', 1)
                ->where('facetCounts.status.active', 1)
            );
    }

    public function test_every_page_needs_an_admin(): void
    {
        foreach (['admin.dashboard', 'admin.users', 'admin.translations', 'admin.subscriptions', 'admin.settings.password'] as $route) {
            $this->get(route($route))->assertRedirect(route('admin.login'));
        }
    }
}
