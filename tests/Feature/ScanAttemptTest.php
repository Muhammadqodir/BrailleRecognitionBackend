<?php

namespace Tests\Feature;

use App\Models\ScanAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScanAttemptTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_is_stored_privately_and_linked_to_own_translation(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $mine = $user->translations()->create(['lang' => 'EN', 'result' => 'server text']);
        $other = User::factory()->create()->translations()->create(['lang' => 'EN']);

        $post = fn ($tid) => $this->actingAs($user, 'sanctum')->post('/api/scan-attempts', [
            'lang' => 'EN',
            'reason' => 'few_cells',
            'translation_id' => $tid,
            'result_json' => '{"engine":"device","cells":[[1,2,3,4,5,0.9,1.2]]}',
            'image' => UploadedFile::fake()->image('scan.jpg'),
        ], ['Accept' => 'application/json']);

        $a = ScanAttempt::findOrFail($post($mine->id)->assertCreated()->json('data.id'));
        $this->assertSame($mine->id, $a->translation_id);
        $this->assertStringStartsWith('scan_attempts/', $a->photo);
        Storage::disk('local')->assertExists($a->photo);

        // Someone else's translation id is not linked.
        $b = ScanAttempt::findOrFail($post($other->id)->assertCreated()->json('data.id'));
        $this->assertNull($b->translation_id);

        // Attempts never show up in history.
        $this->actingAs($user, 'sanctum')->getJson('/api/translations')
            ->assertJsonCount(1, 'data');

        $user->delete();
        Storage::disk('local')->assertMissing($a->photo);
        $this->assertSame(0, ScanAttempt::count());
    }

    public function test_bad_reason_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/scan-attempts', ['lang' => 'EN', 'reason' => 'whatever'])
            ->assertStatus(422);
    }
}
