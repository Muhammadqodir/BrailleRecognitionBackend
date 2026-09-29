<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Switches the apps read at start-up, so behaviour can change without a
 * release. Public: it is read before there is an account.
 */
class AppConfigController extends Controller
{
    /**
     * GET /api/config
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'ocr_engine' => config('braille.ocr_engine') === 'server' ? 'server' : 'device',
            'ocr_fallback_min_cells' => max(0, (int) config('braille.ocr_fallback_min_cells')),
        ]);
    }
}
