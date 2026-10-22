<?php

namespace App\Console\Commands;

use App\Models\LabelPage;
use App\Models\Translation;
use App\Support\LabelQueue;
use Illuminate\Console\Command;

/**
 * Loads the OCR host's cells for queued photos, so the labeling page can
 * suggest cells the phone missed and dots it read differently.
 *
 * The file has one JSON object per line: {"photo": "<uuid>", "w": .., "h": ..,
 * "cells": [[x1, y1, x2, y2, pattern, score], ..]}, boxes in the w x h frame
 * and already in the phone's box convention (braille-mobile-model
 * distill/suggestions.py makes it from the M2 teacher labels).
 */
class ImportLabelSuggestions extends Command
{
    protected $signature = 'labels:suggest {file}';

    protected $description = 'Import the OCR host\'s cells as labeling suggestions';

    public function handle(): int
    {
        $handle = fopen($this->argument('file'), 'r');
        if ($handle === false) {
            $this->error('Cannot open the file.');

            return self::FAILURE;
        }

        $imported = $missing = 0;
        while (($line = fgets($handle)) !== false) {
            $row = json_decode($line, true);
            if (! is_array($row) || empty($row['photo']) || empty($row['w']) || empty($row['h'])) {
                continue;
            }

            $page = LabelPage::query()
                ->whereHas('translation', fn ($q) => $q->where('input_file', Translation::SCAN_DIR.'/'.$row['photo'].'.jpg'))
                ->with('translation:id,result_json')
                ->first();
            if (! $page) {
                $missing++;

                continue;
            }

            [$model, $width, $height] = LabelQueue::modelCells($page->translation);
            $fx = $width / $row['w'];
            $fy = $height / $row['h'];
            $cells = array_map(fn (array $c) => [
                round($c[0] * $fx, 1), round($c[1] * $fy, 1), round($c[2] * $fx, 1), round($c[3] * $fy, 1),
                (int) $c[4], round((float) $c[5], 3),
            ], (array) $row['cells']);

            $page->suggestions = json_encode($cells);
            if ($page->status === 'todo') {
                $score = LabelQueue::score($model, $cells);
                $page->priority = $score['priority'];
                $page->issues = $score['issues'];
            }
            $page->save();
            $imported++;
        }
        fclose($handle);

        $this->info("Imported suggestions for {$imported} pages; {$missing} photos are not in the queue.");

        return self::SUCCESS;
    }
}
