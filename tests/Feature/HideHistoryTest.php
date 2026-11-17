<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RevenueCat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HideHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(RevenueCat::class)->shouldReceive('sync');
    }

    public function test_deleted_scan_leaves_history_but_row_stays(): void
    {
        $user = User::factory()->create();
        $keep = $user->translations()->create(['lang' => 'EN', 'result' => 'keep']);
        $gone = $user->translations()->create(['lang' => 'EN', 'result' => 'gone']);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/translations/{$gone->id}")
            ->assertOk();

        $this->assertNotNull($gone->fresh()->hidden_at);
        $this->actingAs($user, 'sanctum')->getJson('/api/translations')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $keep->id)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('locked', 0);
    }

    public function test_deleting_does_not_give_free_scans_back(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 5) as $i) {
            $t = $user->translations()->create(['lang' => 'EN', 'result' => "scan $i"]);
            $this->actingAs($user, 'sanctum')->deleteJson("/api/translations/{$t->id}")->assertOk();
        }

        $this->actingAs($user, 'sanctum')->getJson('/api/subscription')
            ->assertJsonPath('data.free_left', 0)
            ->assertJsonPath('data.can_translate', false);
    }

    public function test_deleting_twice_succeeds_and_hidden_scan_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $t = $user->translations()->create(['lang' => 'EN', 'result' => 'x']);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/translations/{$t->id}")->assertOk();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/translations/{$t->id}")->assertOk();
        $this->actingAs($user, 'sanctum')->patchJson("/api/translations/{$t->id}", ['is_fav' => true])
            ->assertNotFound();
    }

    public function test_cannot_delete_someone_elses_scan(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $t = $owner->translations()->create(['lang' => 'EN', 'result' => 'x']);

        $this->actingAs($other, 'sanctum')->deleteJson("/api/translations/{$t->id}")
            ->assertNotFound();
        $this->assertNull($t->fresh()->hidden_at);
    }
}
