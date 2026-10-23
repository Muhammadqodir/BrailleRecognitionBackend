<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\LabelPage;
use App\Models\Translation;
use App\Models\User;
use App\Support\LabelQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LabelingTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret-password']);
    }

    /** A phone scan with its photo, cells [x1, y1, x2, y2, pattern, score, margin]. */
    private function scan(array $cells, string $photo = 'a'): Translation
    {
        $user = User::factory()->create();
        Storage::disk('public')->put("scans/{$photo}.jpg", 'jpeg');

        return Translation::create([
            'user_id' => $user->id,
            'lang' => 'EN',
            'result' => 'text',
            'input_file' => "scans/{$photo}.jpg",
            'result_marked' => "https://example.test/storage/scans/{$photo}.jpg",
            'result_json' => json_encode(['engine' => 'device', 'width' => 400, 'height' => 300, 'cells' => $cells]),
        ]);
    }

    private function cells(int $n, float $score = 0.9, float $margin = 4): array
    {
        return array_map(fn ($i) => [10 + $i * 20, 10, 24 + $i * 20, 30, 1, $score, $margin], range(0, $n - 1));
    }

    public function test_queue_takes_phone_scans_once_and_puts_unsure_pages_first(): void
    {
        $sure = $this->scan($this->cells(10), 'sure');
        $unsure = $this->scan($this->cells(10, 0.3), 'unsure');
        Translation::create(['user_id' => $sure->user_id, 'lang' => 'EN', 'input_file' => 'results/x.jpg', 'result_json' => '{}']);

        $this->artisan('labels:queue')->assertSuccessful();
        $this->artisan('labels:queue')->assertSuccessful();

        $this->assertSame(2, LabelPage::count());
        $first = LabelPage::query()->orderByDesc('priority')->first();
        $this->assertSame($unsure->id, $first->translation_id);
        $this->assertSame(10, $first->issues['unsure']);
        $this->assertSame(LabelQueue::split($unsure->user_id), $first->split);
    }

    public function test_suggestions_count_cells_only_the_server_found(): void
    {
        $this->scan($this->cells(6), 'photo1');
        $this->artisan('labels:queue');

        // The server sees the same six cells at twice the resolution, plus two more, one read differently.
        $server = array_map(fn ($c) => [$c[0] * 2, $c[1] * 2, $c[2] * 2, $c[3] * 2, 1, 0.9], $this->cells(6));
        $server[0][4] = 3;
        $server[] = [500, 200, 528, 240, 7, 0.95];
        $server[] = [560, 200, 588, 240, 7, 0.95];
        $file = tempnam(sys_get_temp_dir(), 'sugg');
        File::put($file, json_encode(['photo' => 'photo1', 'w' => 800, 'h' => 600, 'cells' => $server])."\n");

        $this->artisan('labels:suggest', ['file' => $file])->assertSuccessful();

        $page = LabelPage::first();
        $this->assertSame(['cells' => 6, 'unsure' => 0, 'missed' => 2, 'extra' => 0, 'dots' => 1], $page->issues);
        $this->assertEqualsWithDelta(250.0, $page->suggestionList()[6][0], 0.01);
    }

    public function test_saving_a_page_stores_its_cells_and_moves_on(): void
    {
        $this->scan($this->cells(6), 'one');
        $this->scan($this->cells(6, 0.2), 'two');
        $this->artisan('labels:queue');
        [$top, $other] = LabelPage::query()->orderByDesc('priority')->get();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.labeling'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('labeling')->where('startId', $top->id));

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.labeling.show', $top))
            ->assertOk()
            ->assertJsonPath('data.width', 400)
            ->assertJsonCount(6, 'data.model_cells')
            ->assertJsonPath('data.cells', null);

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.labeling.save', $top), [
                'cells' => [[10, 10, 24, 30, 5], [30, 10, 44, 30, 63]],
                'edits' => ['deleted' => 4],
                'tags' => ['two_sided', 'side_light', 'two_sided'],
                'seconds' => 42,
            ])
            ->assertOk()
            ->assertJsonPath('next_id', $other->id)
            ->assertJsonPath('stats.cells', 2);

        $top->refresh();
        $this->assertSame('done', $top->status);
        $this->assertSame([[10, 10, 24, 30, 5], [30, 10, 44, 30, 63]], $top->cellList());
        $this->assertSame(42, $top->seconds);
        $this->assertSame(['two_sided', 'side_light'], $top->tags);

        $this->actingAs($this->admin, 'admin')
            ->putJson(route('admin.labeling.save', $other), ['cells' => [[10, 10, 24, 30, 5]], 'tags' => ['dark']])
            ->assertUnprocessable();
        $this->assertSame($this->admin->id, $top->admin_id);
    }

    public function test_cells_without_dots_or_outside_the_photo_are_refused(): void
    {
        $this->scan($this->cells(6));
        $this->artisan('labels:queue');
        $page = LabelPage::first();

        foreach ([[[10, 10, 24, 30, 0]], [[10, 10, 900, 30, 1]], [[24, 10, 10, 30, 1]]] as $cells) {
            $this->actingAs($this->admin, 'admin')->putJson(route('admin.labeling.save', $page), ['cells' => $cells])->assertUnprocessable();
        }
        $this->assertSame('todo', $page->fresh()->status);
    }

    public function test_skipping_and_export(): void
    {
        $this->scan($this->cells(6), 'keep');
        $this->scan($this->cells(6), 'junk');
        $this->artisan('labels:queue');
        [$keep, $junk] = LabelPage::query()->orderBy('id')->get();

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.labeling.skip', $junk), ['reason' => 'not_braille'])->assertOk();
        $this->actingAs($this->admin, 'admin')->putJson(route('admin.labeling.save', $keep), ['cells' => [[100, 150, 120, 180, 9]], 'tags' => ['side_light']])->assertOk();
        $this->assertSame('not_braille', $junk->fresh()->skip_reason);

        $dir = sys_get_temp_dir().'/labels-'.uniqid();
        $this->artisan('labels:export', ['dir' => $dir])->assertSuccessful();

        $split = $keep->split === 'test' ? null : (crc32('val'.$keep->translation->user_id) % 20 === 0 ? 'val' : 'train');
        if ($split) {
            $this->assertSame("0 0.275 0.55 0.05 0.1\n", File::get("{$dir}/det/labels/{$split}/hl_keep.txt"));
            $patterns = json_decode(File::get("{$dir}/det/patterns/{$split}/hl_keep.patterns.json"), true);
        } else {
            $patterns = json_decode(File::get("{$dir}/bench/human_test/patterns/hl_keep.patterns.json"), true);
        }
        $this->assertSame(9, $patterns['cells'][0]['pattern']);
        $this->assertSame(['side_light'], $patterns['tags']);
        $this->assertCount(1, File::allFiles($dir.'/'.($split ? 'det/images' : 'bench/human_test/images')));
        File::deleteDirectory($dir);
    }

    public function test_split_matches_the_m2_run(): void
    {
        // md5('m2' . id) as a 128-bit number mod 5, as Python computed it for the M2 split.
        foreach ([1, 2, 3, 17, 1234, 98765] as $id) {
            $hex = md5('m2'.$id);
            $mod = (int) gmp_strval(gmp_mod(gmp_init($hex, 16), 5));
            $this->assertSame($mod === 0 ? 'test' : 'train', LabelQueue::split($id), "user {$id}");
        }
    }
}
