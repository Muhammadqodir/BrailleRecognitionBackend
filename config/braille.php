<?php

return [

    /*
    | Where the apps recognise scans. "device" runs the model on the phone and
    | keeps the OCR host as a fallback; "server" sends every scan to the OCR
    | host, as before. Flip it in .env to move every installed app back to
    | the server without a release.
    */
    'ocr_engine' => env('OCR_ENGINE', 'device'),

    /*
    | A phone result with fewer cells than this is retried on the OCR host.
    | The phone model is weakest on printed and on-screen dots, and that is
    | how it fails: it finds almost nothing.
    */
    'ocr_fallback_min_cells' => (int) env('OCR_FALLBACK_MIN_CELLS', 8),
];
