<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RevenueCat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FreeScansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // GET /api/subscription re-checks a non-subscriber with RevenueCat.
        $this->mock(RevenueCat::class)->shouldReceive('sync');
    }

    public function test_new_user_gets_five_free_scans(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('data.can_translate', true)
            ->assertJsonPath('data.free_limit', 5)
            ->assertJsonPath('data.free_left', 5);
    }

    public function test_only_scans_that_found_braille_use_one(): void
    {
        $user = User::factory()->create();
        foreach (['a', 'b', 'c', '', '  '] as $text) {
            $user->translations()->create(['lang' => 'EN', 'result' => $text]);
        }
        $user->translations()->create(['lang' => 'EN']);

        $this->actingAs($user, 'sanctum')->getJson('/api/subscription')
            ->assertJsonPath('data.free_left', 2)
            ->assertJsonPath('data.can_translate', true);
    }

    public function test_paywall_after_the_fifth(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            $user->translations()->create(['lang' => 'EN', 'result' => "scan $i"]);
        }

        $this->actingAs($user, 'sanctum')->getJson('/api/subscription')
            ->assertJsonPath('data.free_left', 0)
            ->assertJsonPath('data.can_translate', false);
    }
}
