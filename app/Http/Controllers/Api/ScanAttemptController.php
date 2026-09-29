<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScanAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScanAttemptController extends Controller
{
    /**
     * POST /api/scan-attempts
     *
     * The app reports what its own model made of a scan it then sent to the
     * OCR server. Nothing is returned to show; the app does not wait on it.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lang' => ['required', 'string', 'max:8'],
            'reason' => ['required', 'in:few_cells,error'],
            'translation_id' => ['nullable', 'integer'],
            'result_json' => ['nullable', 'string'],
            'error' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg', 'max:8192'],
        ]);
        $user = $request->user();

        // Only link a translation that is this user's own.
        $translationId = $validated['translation_id'] ?? null;
        if ($translationId !== null && !$user->translations()->whereKey($translationId)->exists()) {
            $translationId = null;
        }

        $photo = $request->hasFile('image')
            ? $request->file('image')->storeAs(ScanAttempt::PHOTO_DIR, Str::uuid().'.jpg', 'local')
            : null;

        $attempt = ScanAttempt::create([
            'user_id' => $user->id,
            'translation_id' => $translationId,
            'lang' => $validated['lang'],
            'reason' => $validated['reason'],
            'photo' => $photo,
            'result_json' => $validated['result_json'] ?? null,
            'error' => $validated['error'] ?? null,
        ]);

        return response()->json(['message' => 'Saved.', 'data' => ['id' => $attempt->id]], 201);
    }
}
