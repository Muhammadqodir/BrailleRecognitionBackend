<?php

namespace App\Console\Commands;

use App\Models\LabelPage;
use App\Models\Translation;
use App\Support\LabelQueue;
use App\Support\Sql;
use Illuminate\Console\Command;

/**
 * Puts phone scans into the labeling queue: every scan read on the phone
 * whose photo this server still holds. Re-running adds new scans and
 * re-scores the pages not labeled yet.
 */
class QueueLabelPages extends Command
{
    protected $signature = 'labels:queue';

    protected $description = 'Add phone scans to the labeling queue and re-score the unlabeled ones';

    public function handle(): int
    {
        $queued = LabelPage::query()->pluck('translation_id')->flip();
        $added = 0;

        Translation::query()
            ->where('input_file', 'like', Translation::SCAN_DIR.'/%')
            ->whereRaw(Sql::json('result_json', 'engine')." = 'device'")
            ->select(['id', 'user_id', 'result_json'])
            ->chunkById(200, function ($translations) use ($queued, &$added) {
                foreach ($translations as $translation) {
                    if ($queued->has($translation->id)) {
                        continue;
                    }
                    [$cells, $width, $height] = LabelQueue::modelCells($translation);
                    if ($width <= 0 || $height <= 0) {
                        continue;
                    }
                    $score = LabelQueue::score($cells, []);
                    LabelPage::create([
                        'translation_id' => $translation->id,
                        'split' => LabelQueue::split((int) $translation->user_id),
                        'priority' => $score['priority'],
                        'issues' => $score['issues'],
                    ]);
                    $added++;
                }
            });

        $rescored = 0;
        LabelPage::query()->where('status', 'todo')->with('translation:id,result_json')->chunkById(200, function ($pages) use (&$rescored) {
            foreach ($pages as $page) {
                $score = LabelQueue::score(LabelQueue::modelCells($page->translation)[0], $page->suggestionList());
                $page->update(['priority' => $score['priority'], 'issues' => $score['issues']]);
                $rescored++;
            }
        });

        $this->info("Added {$added} pages, re-scored {$rescored}.");

        return self::SUCCESS;
    }
}
