<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LabelPage;
use App\Support\BrailleReader;
use App\Support\LabelQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Hand-labeling phone scans for the next detector and dot classifier: the
 * page starts from the phone's own cells (or, when DETECTOR is installed, from
 * a newer model run in the browser), suggests what the OCR host found that
 * the phone did not, and the admin corrects it.
 */
class LabelingController extends Controller
{
    /** Where the dot classifier the page runs in the browser lives (not in git: the repo is public). */
    public const CLASSIFIER = 'app/labeling/dot_classifier.onnx';

    /** A newer cell detector than the phone's: when installed, unlabeled pages start from its cells. */
    public const DETECTOR = 'app/labeling/detector.onnx';

    public function index(Request $request): Response
    {
        $start = $request->integer('page') ?: $this->nextId();

        return Inertia::render('labeling', [
            'startId' => $start,
            'stats' => $this->stats(),
            'skipReasons' => LabelPage::SKIP_REASONS,
            'tagNames' => LabelPage::TAGS,
            'langs' => TranslationController::READABLE_LANGS,
            'classifierUrl' => $this->modelUrl(self::CLASSIFIER, 'admin.labeling.classifier'),
            'detectorUrl' => $this->modelUrl(self::DETECTOR, 'admin.labeling.detector'),
            'readerInstalled' => BrailleReader::installed(),
        ]);
    }

    public function show(LabelPage $labelPage): JsonResponse
    {
        $translation = $labelPage->translation;
        [$model, $width, $height] = LabelQueue::modelCells($translation);
        $meta = json_decode((string) $translation->result_json, true);

        return response()->json(['data' => [
            'id' => $labelPage->id,
            'translation_id' => $translation->id,
            'photo_url' => $translation->markedImageUrl(),
            'width' => $width,
            'height' => $height,
            'split' => $labelPage->split,
            'status' => $labelPage->status,
            'skip_reason' => $labelPage->skip_reason,
            'tags' => $labelPage->tags ?? [],
            // Clockwise; saved cells are in the turned photo, model cells and suggestions are not.
            'rotation' => $labelPage->rotation ?? 0,
            'priority' => $labelPage->priority,
            'issues' => $labelPage->issues,
            // [x1, y1, x2, y2, pattern, detector score, dot margin]
            'model_cells' => $model,
            // [x1, y1, x2, y2, pattern, score]
            'suggestions' => $labelPage->suggestionList(),
            // [x1, y1, x2, y2, pattern], null until saved
            'cells' => $labelPage->cells === null ? null : $labelPage->cellList(),
            'lang' => is_array($meta) && ($meta['lang_guess']['best'] ?? null) ? $meta['lang_guess']['best'] : $translation->lang,
            'text' => $translation->result,
            'labeled_at' => $labelPage->labeled_at,
            'labeled_by' => $labelPage->admin?->name,
            'seconds' => $labelPage->seconds,
            'prev_id' => LabelPage::query()->where('status', '!=', 'todo')->where('labeled_at', '<', $labelPage->labeled_at ?? now())
                ->orderByDesc('labeled_at')->value('id'),
        ]]);
    }

    public function save(Request $request, LabelPage $labelPage): JsonResponse
    {
        $translation = $labelPage->translation;
        [, $width, $height] = LabelQueue::modelCells($translation);
        // Cells come in the turned photo's pixels.
        $rotation = in_array((int) $request->input('rotation'), LabelPage::ROTATIONS, true) ? (int) $request->input('rotation') : 0;
        [$width, $height] = LabelPage::frame($width, $height, $rotation);

        $data = $request->validate([
            'rotation' => ['nullable', 'integer', Rule::in(LabelPage::ROTATIONS)],
            'cells' => ['present', 'array', 'max:3000'],
            'cells.*' => ['array', 'size:5'],
            'cells.*.0' => ['numeric', 'min:-2', "max:{$width}"],
            'cells.*.1' => ['numeric', 'min:-2', "max:{$height}"],
            'cells.*.2' => ['numeric', 'gt:cells.*.0', 'max:'.($width + 2)],
            'cells.*.3' => ['numeric', 'gt:cells.*.1', 'max:'.($height + 2)],
            'cells.*.4' => ['integer', 'min:1', 'max:63'],
            'edits' => ['nullable', 'array'],
            'edits.*' => ['integer', 'min:0'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', Rule::in(LabelPage::TAGS)],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
        ]);

        $labelPage->update([
            'cells' => json_encode(array_map(fn (array $c) => [
                round(max(0, (float) $c[0]), 1), round(max(0, (float) $c[1]), 1),
                round(min($width, (float) $c[2]), 1), round(min($height, (float) $c[3]), 1), (int) $c[4],
            ], $data['cells'])),
            'edits' => $data['edits'] ?? null,
            'tags' => array_values(array_unique($data['tags'] ?? [])),
            'rotation' => $rotation,
            'status' => 'done',
            'skip_reason' => null,
            'admin_id' => $request->user('admin')->id,
            'seconds' => $labelPage->seconds + (int) ($data['seconds'] ?? 0),
            'labeled_at' => now(),
        ]);

        return response()->json(['next_id' => $this->nextId($labelPage->id), 'stats' => $this->stats()]);
    }

    public function skip(Request $request, LabelPage $labelPage): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(LabelPage::SKIP_REASONS)],
            'seconds' => ['nullable', 'integer', 'min:0', 'max:7200'],
        ]);

        $labelPage->update([
            'status' => 'skipped',
            'skip_reason' => $data['reason'],
            'admin_id' => $request->user('admin')->id,
            'seconds' => $labelPage->seconds + (int) ($data['seconds'] ?? 0),
            'labeled_at' => now(),
        ]);

        return response()->json(['next_id' => $this->nextId($labelPage->id), 'stats' => $this->stats()]);
    }

    /** The cells on the page now, read as text the way the app would. */
    public function read(Request $request, LabelPage $labelPage): JsonResponse
    {
        $data = $request->validate([
            'lang' => ['required', Rule::in(TranslationController::READABLE_LANGS)],
            'cells' => ['present', 'array', 'max:3000'],
            'cells.*' => ['array', 'size:5'],
            'cells.*.*' => ['numeric'],
            'rotation' => ['nullable', 'integer', Rule::in(LabelPage::ROTATIONS)],
        ]);
        if (! BrailleReader::installed()) {
            return response()->json(['message' => 'The braille reader is not installed on this server.'], 503);
        }
        [, $width, $height] = LabelQueue::modelCells($labelPage->translation);
        [$width, $height] = LabelPage::frame($width, $height, (int) ($data['rotation'] ?? 0));

        try {
            return response()->json(['data' => BrailleReader::read($data['cells'], $width, $height, $data['lang'])]);
        } catch (\RuntimeException $e) {
            report($e);

            return response()->json(['message' => 'The braille reader could not read these cells.'], 500);
        }
    }

    public function classifier(): BinaryFileResponse
    {
        return $this->modelFile(self::CLASSIFIER);
    }

    public function detector(): BinaryFileResponse
    {
        return $this->modelFile(self::DETECTOR);
    }

    /** The model's URL with its file time, so a replaced model is not served from the browser cache. */
    private function modelUrl(string $file, string $route): ?string
    {
        $path = storage_path($file);

        return is_file($path) ? route($route, ['v' => filemtime($path)]) : null;
    }

    private function modelFile(string $file): BinaryFileResponse
    {
        abort_unless(is_file(storage_path($file)), 404);

        return response()->file(storage_path($file), [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /** The most useful page nobody has labeled yet. */
    private function nextId(?int $after = null): ?int
    {
        return LabelPage::query()
            ->where('status', 'todo')
            ->when($after, fn ($q) => $q->where('id', '!=', $after))
            ->orderByDesc('priority')
            ->orderBy('id')
            ->value('id');
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $rows = LabelPage::query()
            ->selectRaw('split, status, COUNT(*) as pages, SUM(seconds) as seconds')
            ->groupBy('split', 'status')
            ->get();

        $count = fn (string $split, string $status) => (int) ($rows->first(fn ($r) => $r->split === $split && $r->status === $status)?->pages ?? 0);
        $cells = 0;
        LabelPage::query()->where('status', 'done')->select(['id', 'cells'])->chunkById(200, function ($pages) use (&$cells) {
            foreach ($pages as $page) {
                $cells += count($page->cellList());
            }
        });

        return [
            'train' => ['todo' => $count('train', 'todo'), 'done' => $count('train', 'done'), 'skipped' => $count('train', 'skipped')],
            'test' => ['todo' => $count('test', 'todo'), 'done' => $count('test', 'done'), 'skipped' => $count('test', 'skipped')],
            'cells' => $cells,
            'seconds' => (int) $rows->sum('seconds'),
            'today' => LabelPage::query()->where('status', 'done')->where('labeled_at', '>=', now()->startOfDay())->count(),
        ];
    }
}
