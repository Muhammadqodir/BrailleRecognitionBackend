<?php

namespace App\Support;

use App\Models\LabelPage;
use App\Models\Translation;

/**
 * Which phone scans to label, and in what order.
 *
 * Cells are [x1, y1, x2, y2, pattern, ...] in photo pixels. The phone stores
 * [.., pattern, detector score, dot margin] (the smallest |logit| of the six
 * dots); the OCR host's suggestions are [.., pattern, score].
 */
class LabelQueue
{
    /** A phone cell below either is one the phone was unsure of. */
    public const LOW_SCORE = 0.45;

    public const LOW_MARGIN = 1.0;

    /** Server cells at least this sure count as cells the phone missed. */
    public const SUGGEST_MIN = 0.5;

    public const MATCH_IOU = 0.3;

    /**
     * Same split as the M2 distillation run (md5('m2' + user id) % 5 == 0 is
     * test), so a model trained on either never saw the other's test users.
     */
    public static function split(int $userId): string
    {
        // The whole 128-bit hash mod 5, as Python's int(md5, 16) % 5.
        $mod = 0;
        foreach (str_split(md5('m2'.$userId)) as $digit) {
            $mod = ($mod * 16 + hexdec($digit)) % 5;
        }

        return $mod === 0 ? 'test' : 'train';
    }

    /** @return array{0: list<list<float>>, 1: int, 2: int} the phone's cells, photo width and height */
    public static function modelCells(Translation $translation): array
    {
        $meta = json_decode((string) $translation->result_json, true);
        $meta = is_array($meta) ? $meta : [];
        $cells = LabelPage::decodeCells(json_encode($meta['cells'] ?? []));

        return [array_map(fn (array $c) => array_map('floatval', $c), $cells), (int) ($meta['width'] ?? 0), (int) ($meta['height'] ?? 0)];
    }

    /**
     * How much a page would teach, and why: phone cells it was unsure of,
     * cells only the server found, cells only the phone found, and cells
     * both found but read with different dots, over the page's cell count.
     * Pages with almost no cells are mostly not braille, so they go last.
     *
     * @param  list<list<float>>  $model
     * @param  list<list<float>>  $suggestions
     * @return array{priority: float, issues: array<string, int>}
     */
    public static function score(array $model, array $suggestions): array
    {
        $unsure = count(array_filter($model, fn ($c) => ($c[5] ?? 1) < self::LOW_SCORE || ($c[6] ?? 9) < self::LOW_MARGIN));
        $sure = array_values(array_filter($suggestions, fn ($c) => ($c[5] ?? 1) >= self::SUGGEST_MIN));

        [$pairs, $onlyModel, $onlyServer] = self::match($model, $sure);
        $dots = count(array_filter($pairs, fn ($p) => (int) $model[$p[0]][4] !== (int) $sure[$p[1]][4]));
        $issues = ['cells' => count($model), 'unsure' => $unsure];
        $weighted = $unsure;

        if ($suggestions !== []) {
            $issues += ['missed' => count($onlyServer), 'extra' => count($onlyModel), 'dots' => $dots];
            $weighted += 2 * count($onlyServer) + count($onlyModel) + 2 * $dots;
        }

        $n = max(count($model), count($sure));
        $priority = $n > 0 ? $weighted / $n : 0.0;

        return ['priority' => round($n < 5 ? $priority * 0.1 : $priority, 4), 'issues' => $issues];
    }

    /**
     * Greedy one-to-one matching by IoU, best pairs first.
     *
     * @param  list<list<float>>  $a
     * @param  list<list<float>>  $b
     * @return array{0: list<array{0: int, 1: int}>, 1: list<int>, 2: list<int>} pairs, unmatched in a, unmatched in b
     */
    public static function match(array $a, array $b): array
    {
        $candidates = [];
        foreach ($a as $i => $ca) {
            foreach ($b as $j => $cb) {
                // Cheap reject before the IoU: boxes that do not overlap at all.
                if ($cb[0] >= $ca[2] || $cb[2] <= $ca[0] || $cb[1] >= $ca[3] || $cb[3] <= $ca[1]) {
                    continue;
                }
                $iou = self::iou($ca, $cb);
                if ($iou >= self::MATCH_IOU) {
                    $candidates[] = [$iou, $i, $j];
                }
            }
        }
        usort($candidates, fn ($x, $y) => $y[0] <=> $x[0]);

        $usedA = $usedB = $pairs = [];
        foreach ($candidates as [, $i, $j]) {
            if (! isset($usedA[$i]) && ! isset($usedB[$j])) {
                $usedA[$i] = $usedB[$j] = true;
                $pairs[] = [$i, $j];
            }
        }

        return [
            $pairs,
            array_values(array_diff(array_keys($a), array_keys($usedA))),
            array_values(array_diff(array_keys($b), array_keys($usedB))),
        ];
    }

    /** @param  list<float>  $a @param  list<float>  $b */
    public static function iou(array $a, array $b): float
    {
        $w = min($a[2], $b[2]) - max($a[0], $b[0]);
        $h = min($a[3], $b[3]) - max($a[1], $b[1]);
        if ($w <= 0 || $h <= 0) {
            return 0.0;
        }
        $inter = $w * $h;

        return $inter / (($a[2] - $a[0]) * ($a[3] - $a[1]) + ($b[2] - $b[0]) * ($b[3] - $b[1]) - $inter);
    }
}
