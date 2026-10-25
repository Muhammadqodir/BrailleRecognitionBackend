<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LabelPage;
use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LabelingRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_turned_page_saves_in_the_turned_photo_and_exports_turned(): void
    {
        Storage::fake('public');
        $admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);

        // A real 400 x 300 photo, so the export can turn it.
        $image = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put('scans/turn.jpg', (string) ob_get_clean());
        Translation::create([
            'user_id' => User::factory()->create()->id,
            'lang' => 'EN',
            'input_file' => 'scans/turn.jpg',
            'result_marked' => 'https://example.test/storage/scans/turn.jpg',
            'result_json' => json_encode(['engine' => 'device', 'width' => 400, 'height' => 300, 'cells' => [[10, 10, 24, 30, 1, 0.9, 4]]]),
        ]);
        $this->artisan('labels:queue');
        $page = LabelPage::first();

        // Turned a quarter, the photo is 300 wide and 400 tall: y 380 fits, x 350 does not.
        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.labeling.save', $page), ['cells' => [[10, 350, 30, 380, 5]], 'rotation' => 90])
            ->assertOk();
        $this->assertSame(90, $page->fresh()->rotation);
        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.labeling.save', $page), ['cells' => [[350, 10, 370, 30, 5]], 'rotation' => 90])
            ->assertUnprocessable();
        $this->actingAs($admin, 'admin')
            ->putJson(route('admin.labeling.save', $page), ['cells' => [[10, 350, 30, 380, 5]], 'rotation' => 45])
            ->assertUnprocessable();

        $this->actingAs($admin, 'admin')->getJson(route('admin.labeling.show', $page))->assertJsonPath('data.rotation', 90);

        $dir = sys_get_temp_dir().'/labels-'.uniqid();
        $this->artisan('labels:export', ['dir' => $dir])->assertSuccessful();
        $images = array_values(array_filter(File::allFiles($dir), fn ($f) => $f->getExtension() === 'jpg'));
        $patterns = array_values(array_filter(File::allFiles($dir), fn ($f) => str_ends_with($f->getFilename(), '.patterns.json')));
        $this->assertCount(1, $images);
        $this->assertSame([300, 400], array_slice(getimagesize($images[0]->getPathname()), 0, 2));
        $json = json_decode(File::get($patterns[0]->getPathname()), true);
        $this->assertSame([300, 400], $json['image_size']);
        $this->assertEqualsWithDelta(365 / 400, $json['cells'][0]['box'][1], 1e-6);
        File::deleteDirectory($dir);
    }
}
