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

    /*
    | The OCR host. Scans it recognised keep their pictures there, and history
    | stores them as paths relative to it ("results/...").
    */
    'ocr_host_url' => env('OCR_HOST_URL', 'http://109.123.251.227:8000/'),

    /*
    | The admin dashboard lives under this path segment. This repository is
    | public, so production sets a random ADMIN_PATH in .env to keep it out
    | of scans of common admin paths. Obscurity, not access control: the
    | admin guard and the login throttle are what protect it.
    */
    'admin_path' => env('ADMIN_PATH', 'admin'),
];
