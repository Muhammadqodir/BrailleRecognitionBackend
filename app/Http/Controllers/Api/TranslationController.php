<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Scan history.
 *
 * Full history is a Premium feature. Free users see the most recent few and a
 * count of what is held back, which is what the locked-history card in the app
 * renders — so the gate has to report *how much* is hidden, not just hide it.
 */
class TranslationController extends Controller
{
    /** How many past scans a non-subscriber can see. */
    private const FREE_HISTORY_LIMIT = 3;

    /**
     * GET /api/translations
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $isPremium = $user->isPremium();

        $query = $user->translations()->latest('created_at');
        $total = (clone $query)->count();

        if ($isPremium) {
            $perPage = min(50, max(10, (int) $request->query('per_page', 20)));
            $items = $query->paginate($perPage);

            return response()->json([
                'data' => $items->items(),
                'locked' => 0,
                'total' => $total,
                'is_premium' => true,
                'next_page' => $items->hasMorePages() ? $items->currentPage() + 1 : null,
            ]);
        }

        $items = $query->limit(self::FREE_HISTORY_LIMIT)->get();

        return response()->json([
            'data' => $items,
            'locked' => max(0, $total - $items->count()),
            'total' => $total,
            'is_premium' => false,
            'next_page' => null,
        ]);
    }

    /**
     * POST /api/translations
     *
     * Called by the OCR host once it has recognised an image. It owns the
     * result files, so this only records what came back.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'result_braille' => ['nullable', 'string'],
            'result' => ['nullable', 'string'],
            'result_json' => ['nullable', 'string'],
            'input_file' => ['nullable', 'string', 'max:255'],
            'result_marked' => ['nullable', 'string', 'max:255'],
            'lang' => ['required', 'string', 'max:8'],
        ]);

        $translation = $request->user()->translations()->create($validated);

        return response()->json([
            'message' => 'Saved.',
            'data' => $translation,
            // The app shows a different result screen when nothing was found,
            // rather than an empty success.
            'failed' => $translation->failed,
        ], 201);
    }

    /**
     * PATCH /api/translations/{translation}
     *
     * Favourite, or rate. Scoped to the owner so one user cannot touch
     * another's row by guessing an id — the legacy backend had exactly that
     * hole on set_fav and set_rating.
     */
    public function update(Request $request, Translation $translation): JsonResponse
    {
        if ($translation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $validated = $request->validate([
            'is_fav' => ['nullable', 'boolean'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $translation->fill(array_filter($validated, fn ($v) => $v !== null));
        $translation->save();

        return response()->json(['message' => 'Updated.', 'data' => $translation]);
    }

    /**
     * DELETE /api/translations/{translation}
     */
    public function destroy(Request $request, Translation $translation): JsonResponse
    {
        if ($translation->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $translation->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
