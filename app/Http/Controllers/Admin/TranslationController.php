<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TranslationController extends Controller
{
    /** @var list<string> */
    private const SORTABLE = ['created_at', 'lang'];

    private const ENGINES = ['device', 'server'];

    private const RATINGS = ['up', 'down', 'none'];

    private const RESULTS = ['read', 'empty'];

    /** The languages the app's text step reads (braille_text's supportedLangs). */
    public const READABLE_LANGS = ['EN', 'EN2', 'RU', 'DE', 'GR', 'LV', 'PL', 'UZ', 'UZL', 'FR', 'ES', 'PT', 'AR'];

    public function index(Request $request): Response
    {
        $sort = in_array($request->string('sort')->value(), self::SORTABLE, true)
            ? $request->string('sort')->value()
            : 'created_at';
        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->input('per_page'), [10, 20, 30, 50], true)
            ? (int) $request->input('per_page')
            : 20;

        $langs = array_values((array) $request->input('lang', []));
        $engines = array_values(array_intersect((array) $request->input('engine', []), self::ENGINES));
        $ratings = array_values(array_intersect((array) $request->input('rating', []), self::RATINGS));
        $results = array_values(array_intersect((array) $request->input('result', []), self::RESULTS));
        $userId = $request->integer('user_id') ?: null;
        $engine = Sql::engine();

        $translations = Translation::query()
            ->with('user:id,name,email,auth_provider')
            ->select(['id', 'user_id', 'lang', 'result', 'result_marked', 'rating', 'is_fav', 'created_at'])
            ->selectRaw("{$engine} as engine")
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $query->where('result', 'like', '%'.$request->string('search')->trim()->value().'%');
            })
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($langs !== [], fn (Builder $query) => $query->whereIn('lang', $langs))
            ->when(count($engines) === 1, fn (Builder $query) => $query->whereRaw("{$engine} = ?", $engines))
            ->when($ratings !== [], function (Builder $query) use ($ratings) {
                $query->where(function (Builder $q) use ($ratings) {
                    if (in_array('up', $ratings, true)) {
                        $q->orWhere('rating', '>', 0);
                    }
                    if (in_array('down', $ratings, true)) {
                        $q->orWhere('rating', '<', 0);
                    }
                    if (in_array('none', $ratings, true)) {
                        $q->orWhereNull('rating')->orWhere('rating', 0);
                    }
                });
            })
            ->when(count($results) === 1, fn (Builder $query) => $query->whereRaw(
                ($results[0] === 'empty' ? '' : 'NOT ').Sql::emptyResult()
            ))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        $translations->through(fn (Translation $translation) => [
            'id' => $translation->id,
            'text' => mb_strimwidth(trim((string) $translation->result), 0, 160, '…'),
            'image_url' => $translation->markedImageUrl(),
            'lang' => $translation->lang,
            'engine' => $translation->engine,
            'rating' => $translation->rating,
            'is_fav' => $translation->is_fav,
            'user' => $translation->user ? [
                'id' => $translation->user->id,
                'name' => $translation->user->name,
                'email' => $translation->user->email,
                'provider' => $translation->user->auth_provider,
            ] : null,
            'created_at' => $translation->created_at,
        ]);

        return Inertia::render('translations', [
            'translations' => $translations,
            'langCounts' => Translation::query()
                ->selectRaw('lang, COUNT(*) as count')
                ->groupBy('lang')
                ->orderByDesc('count')
                ->pluck('count', 'lang')
                ->map(fn ($count) => (int) $count),
            'engineCounts' => Translation::query()
                ->selectRaw("{$engine} as engine, COUNT(*) as count")
                ->groupBy('engine')
                ->pluck('count', 'engine')
                ->map(fn ($count) => (int) $count),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'user_id' => $userId,
                'lang' => $langs,
                'engine' => $engines,
                'rating' => $ratings,
                'result' => $results,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Translation $translation): JsonResponse
    {
        $translation->load('user:id,name,email,auth_provider');
        $meta = json_decode((string) $translation->result_json, true);
        $meta = is_array($meta) ? $meta : [];

        $device = ($meta['engine'] ?? null) === 'device';

        return response()->json(['data' => [
            'id' => $translation->id,
            'text' => $translation->result,
            'braille' => $translation->result_braille,
            'image_url' => $translation->markedImageUrl(),
            // The phone keeps the plain photo and the boxes it found, and the
            // dashboard draws the marks over it, as the app does. The OCR host
            // stored a marked-up picture of its own instead.
            'photo_url' => $device ? $translation->markedImageUrl() : $translation->ocrHostUrl($translation->input_file),
            'marked_url' => $device ? null : $translation->markedImageUrl(),
            'width' => isset($meta['width']) ? (int) $meta['width'] : null,
            'height' => isset($meta['height']) ? (int) $meta['height'] : null,
            // [x1, y1, x2, y2, dot pattern] in photo pixels.
            'cells' => $device ? collect((array) ($meta['cells'] ?? []))
                ->filter(fn ($cell) => is_array($cell) && count($cell) >= 5)
                ->map(fn (array $cell) => array_map('floatval', array_slice($cell, 0, 5)))
                ->values() : [],
            'lang' => $translation->lang,
            'rating' => $translation->rating,
            'is_fav' => $translation->is_fav,
            'legacy_id' => $translation->legacy_id,
            'user' => $translation->user ? [
                'id' => $translation->user->id,
                'name' => $translation->user->name,
                'email' => $translation->user->email,
                'provider' => $translation->user->auth_provider,
            ] : null,
            'created_at' => $translation->created_at,
            // What the recogniser reported about itself; the per-cell boxes are left out.
            'engine' => $device ? 'device' : 'server',
            'backend' => $meta['backend'] ?? null,
            'model' => $meta['model'] ?? null,
            'size' => isset($meta['width'], $meta['height']) ? "{$meta['width']}×{$meta['height']}" : null,
            'timings' => Arr::where((array) ($meta['timings'] ?? []), fn ($value) => is_numeric($value)),
            'detected_lang' => $meta['lang_guess']['best'] ?? null,
            'lang_source' => $meta['lang_source'] ?? null,
            // What Auto scored each language at, best first: how sure the phone was.
            'lang_scores' => collect((array) ($meta['lang_guess']['scores'] ?? []))
                ->filter(fn ($score) => is_numeric($score))
                ->sortDesc()
                ->map(fn ($score, $lang) => ['lang' => (string) $lang, 'score' => (float) $score])
                ->values(),
            'detect_margin' => isset($meta['lang_guess']['margin']) ? (float) $meta['lang_guess']['margin'] : null,
            'readable_langs' => $device && ! empty($meta['cells']) ? self::READABLE_LANGS : [],
            'turn' => $meta['turn'] ?? null,
        ]]);
    }

    /**
     * The scan's stored cells read in another language, as the app would read
     * them if the person picked it. Nothing is saved: it is for checking what
     * Auto chose.
     */
    public function read(Request $request, Translation $translation): JsonResponse
    {
        $lang = $request->validate(['lang' => ['required', Rule::in(self::READABLE_LANGS)]])['lang'];

        $meta = json_decode((string) $translation->result_json, true);
        if (! is_array($meta) || ($meta['engine'] ?? null) !== 'device' || empty($meta['cells']) || empty($meta['width']) || empty($meta['height'])) {
            return response()->json(['message' => 'Only scans read on the phone keep the cells needed to read them again.'], 422);
        }

        $reader = config('braille.reader');
        if (! is_executable($reader['bin'])) {
            return response()->json(['message' => 'The braille reader is not installed on this server.'], 503);
        }

        $result = Process::timeout(15)
            ->input(json_encode([
                'cells' => $meta['cells'],
                'width' => $meta['width'],
                'height' => $meta['height'],
                'langs' => [$lang],
            ])."\n")
            ->run([$reader['bin'], '--louis', $reader['louis'], '--tables', $reader['tables']]);

        $output = json_decode($result->output(), true);
        $read = $output[$lang] ?? null;

        if (! $result->successful() || ! is_array($read) || isset($read['error'])) {
            report(new \RuntimeException('braille reader failed: '.($output['error'] ?? $read['error'] ?? $result->errorOutput())));

            return response()->json(['message' => 'The braille reader could not read this scan.'], 500);
        }

        return response()->json(['data' => [
            'lang' => $lang,
            'text' => $read['text'],
            'braille' => $read['braille'],
        ]]);
    }
}
