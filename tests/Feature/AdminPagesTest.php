<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    private function event(User $user, string $name, array $props = [], string $locale = 'pt-BR'): void
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
            'platform' => 'android',
            'locale' => $locale,
            'created_at' => now(),
            'updated_at' => now(),
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

    public function test_dashboard_reports_users_translations_and_subscribers(): void
    {
        $paying = User::factory()->create(['auth_provider' => 'google', 'last_seen_at' => now()]);
        $anonymous = User::factory()->create(['auth_provider' => 'anonymous', 'last_seen_at' => now()->subDays(40)]);
        $this->subscribe($paying, 'normal');
        $this->subscribe($anonymous, 'trial');
        // Neither a sandbox purchase nor an expired one is a paying subscriber.
        $this->subscribe(User::factory()->create(), 'normal', ['environment' => 'SANDBOX']);
        $this->subscribe(User::factory()->create(), 'normal', ['expires_at' => now()->subDays(2)]);

        $paying->translations()->create(['lang' => 'EN', 'result' => 'Hello', 'result_json' => json_encode(['engine' => 'device']), 'rating' => 1]);
        $paying->translations()->create(['lang' => 'EN', 'result' => '', 'rating' => -1]);
        $anonymous->translations()->create(['lang' => 'DE', 'result' => 'Hallo']);

        $this->event($paying, 'app_opened', ['is_first_open' => true]);
        $this->event($paying, 'scan_completed');
        $this->event($anonymous, 'app_opened', ['is_first_open' => true], 'en');

        $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('kpis.totalUsers.value', 4)
                ->where('kpis.activeToday.value', 1)
                ->where('kpis.paidSubscribers.value', 1)
                ->where('kpis.trials.value', 1)
                ->where('kpis.translationsThisWeek.value', 3)
                ->where('kpis.readRate.value', 66.7)
                ->where('kpis.thumbsUp.value', 1)
                ->where('kpis.thumbsDown.value', 1)
                ->where('installsByCountry.countries.0.code', 'BR')
                ->where('installsByCountry.unplacedInstalls', 1)
                ->where('funnel.cohort', 2)
                ->where('funnel.steps.2.count', 1)
                ->where('topLanguages.0.lang', 'EN')
                ->where('topLanguages.0.emptyRate', 50)
                ->has('userGrowth', 30)
                ->has('translationsDaily', 14)
                ->where('translationsDaily.13.device', 1)
                ->where('translationsDaily.13.server', 2)
            );
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
