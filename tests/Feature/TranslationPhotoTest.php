<?php

namespace Tests\Feature;

use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranslationPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_recorded_scan_keeps_its_photo_here(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->post('/api/translations', [
            'lang' => 'EN2',
            'result' => "The quick\n",
            'result_braille' => "⠠⠞⠓⠑ ⠟⠥⠊⠉⠅\n",
            'result_json' => '{"engine":"device","cells":[]}',
            'image' => UploadedFile::fake()->image('scan.jpg', 800, 600),
        ], ['Accept' => 'application/json']);

        $res->assertCreated();
        $t = Translation::findOrFail($res->json('data.id'));
        $this->assertStringStartsWith('scans/', $t->input_file);
        Storage::disk('public')->assertExists($t->input_file);
        $this->assertSame(Storage::disk('public')->url($t->input_file), $t->result_marked);
        $this->assertSame('{"engine":"device","cells":[]}', $t->result_json);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/translations/{$t->id}")->assertOk();
        Storage::disk('public')->assertMissing($t->input_file);
    }

    public function test_scan_can_be_reread_in_another_language(): void
    {
        $user = User::factory()->create();
        $t = $user->translations()->create(['lang' => 'EN', 'result' => 'fyf', 'result_json' => '{}']);

        $this->actingAs($user, 'sanctum')->patchJson("/api/translations/{$t->id}", [
            'lang' => 'RU',
            'result' => 'фыф',
            'result_braille' => '⠋⠽⠋',
            'result_json' => '{"lang_source":"picked"}',
        ])->assertOk();

        $t->refresh();
        $this->assertSame('RU', $t->lang);
        $this->assertSame('фыф', $t->result);
        $this->assertSame('{"lang_source":"picked"}', $t->result_json);

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')->patchJson("/api/translations/{$t->id}", ['lang' => 'DE'])
            ->assertNotFound();
        $this->assertSame('RU', $t->refresh()->lang);
    }

    public function test_ocr_host_paths_are_kept_as_sent(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/translations', [
            'lang' => 'RU',
            'result' => 'текст',
            'input_file' => 'results/temp.labeled.jpg',
            'result_marked' => 'results/temp.marked.jpg',
        ]);

        $res->assertCreated();
        $this->assertSame('results/temp.marked.jpg', $res->json('data.result_marked'));
    }

    public function test_deleting_a_user_removes_their_photos(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = $this->actingAs($user, 'sanctum')->post('/api/translations', [
            'lang' => 'EN',
            'image' => UploadedFile::fake()->image('scan.jpg'),
        ], ['Accept' => 'application/json'])->json('data.input_file');

        Storage::disk('public')->assertExists($path);
        $user->delete();
        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_jpeg_upload_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->post('/api/translations', [
            'lang' => 'EN',
            'image' => UploadedFile::fake()->create('x.php', 10, 'text/x-php'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_config_defaults_to_device(): void
    {
        $this->getJson('/api/config')->assertOk()
            ->assertExactJson(['ocr_engine' => 'device', 'ocr_fallback_min_cells' => 8]);

        config(['braille.ocr_engine' => 'server']);
        $this->getJson('/api/config')->assertJson(['ocr_engine' => 'server']);
    }
}
