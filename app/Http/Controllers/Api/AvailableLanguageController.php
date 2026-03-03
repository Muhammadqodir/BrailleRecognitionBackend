<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailableLanguage;
use Illuminate\Http\JsonResponse;

class AvailableLanguageController extends Controller
{
    /**
     * Return all available languages.
     */
    public function index(): JsonResponse
    {
        $languages = AvailableLanguage::select('id', 'name', 'code')->get();

        return response()->json([
            'data' => $languages,
        ]);
    }
}
