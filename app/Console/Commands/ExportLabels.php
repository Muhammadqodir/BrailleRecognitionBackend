<?php

namespace App\Console\Commands;

use App\Models\LabelPage;
use App\Support\LabelQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Writes the labeled pages out for training, in the layout of the
 * braille-mobile-model detector set (YOLO labels, one class, plus a
 * patterns.json per page for the dot classifier):
 *
 *   <dir>/det/{images,labels,patterns}/{train,val}/<stem>.*
 *   <dir>/bench/human_test/{images,patterns}/<stem>.*   test-split pages, never trained on
 */
class ExportLabels extends Command
{
    protected $signature = 'labels:export {dir}';

    protected $description = 'Export the labeled pages as a training set and a test benchmark';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        $counts = ['train' => 0, 'val' => 0, 'test' => 0, 'cells' => 0, 'no_photo' => 0];

        LabelPage::query()->where('status', 'done')->with('translation:id,user_id,input_file,result_json')->chunkById(100, function ($pages) use ($dir, &$counts) {
            foreach ($pages as $page) {
                $photo = $page->translation?->storedPhotoPath();
                if (! $photo || ! Storage::disk('public')->exists($photo)) {
                    $counts['no_photo']++;

                    continue;
                }
                // Cells are in the frame the phone recognised; the file is the same picture.
                [, $width, $height] = LabelQueue::modelCells($page->translation);
                $stem = 'hl_'.pathinfo($photo, PATHINFO_FILENAME);
                $cells = $page->cellList();

                $boxes = array_map(fn (array $c) => [
                    round(($c[0] + $c[2]) / 2 / $width, 6), round(($c[1] + $c[3]) / 2 / $height, 6),
                    round(($c[2] - $c[0]) / $width, 6), round(($c[3] - $c[1]) / $height, 6),
                ], $cells);
                $patterns = [
                    'schemaVersion' => 1, 'stem' => $stem, 'source' => 'human', 'image_size' => [$width, $height],
                    'cells' => array_map(fn (array $box, array $c) => ['box' => $box, 'pattern' => (int) $c[4]], $boxes, $cells),
                ];

                if ($page->split === 'test') {
                    $base = "{$dir}/bench/human_test";
                    $split = null;
                } else {
                    // A few people's pages held out to pick the best epoch on.
                    $split = crc32('val'.$page->translation->user_id) % 20 === 0 ? 'val' : 'train';
                    $base = "{$dir}/det";
                }
                $sub = $split ? "/{$split}" : '';
                foreach (['images', 'patterns'] + ($split ? [2 => 'labels'] : []) as $kind) {
                    File::ensureDirectoryExists("{$base}/{$kind}{$sub}");
                }

                File::copy(Storage::disk('public')->path($photo), "{$base}/images{$sub}/{$stem}.jpg");
                File::put("{$base}/patterns{$sub}/{$stem}.patterns.json", json_encode($patterns));
                if ($split) {
                    File::put("{$base}/labels{$sub}/{$stem}.txt", implode('', array_map(fn ($b) => "0 {$b[0]} {$b[1]} {$b[2]} {$b[3]}\n", $boxes)));
                }

                $counts[$split ?? 'test']++;
                $counts['cells'] += count($cells);
            }
        });

        $this->info(json_encode($counts));

        return self::SUCCESS;
    }
}
