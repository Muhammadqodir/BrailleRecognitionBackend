<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', 'https://your-app.com/auth/google/callback'),

        // Every OAuth client an ID token may legitimately be issued for. The
        // app asks for the token with the *web* client id, so that is the
        // audience that arrives; the iOS client is listed for when the app
        // offers Google there too. A token for anything else is not a sign-in
        // here, however validly it is signed.
        'allowed_audiences' => array_filter([
            env('GOOGLE_WEB_CLIENT_ID'),
            env('GOOGLE_IOS_CLIENT_ID'),
        ]),
    ],

    // Apple Sign-In (mobile identity token flow — no OAuth redirect needed)
    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'), // App bundle ID, e.g. uz.mq.braille

        // `aud` on a native Sign in with Apple token is the bundle id.
        'allowed_audiences' => array_filter([
            env('APPLE_BUNDLE_ID', 'uz.mq.brailleRecognition'),
            env('APPLE_CLIENT_ID'),
        ]),
    ],


    'revenuecat' => [
        // Secret (sk_) key — server-side only. The app ships the public key.
        'secret_key' => env('REVENUECAT_SECRET_KEY', ''),
        'entitlement' => env('REVENUECAT_ENTITLEMENT', 'Premium'),
        // Sent by RevenueCat as the bare Authorization header value, with no
        // "Bearer " prefix. Getting that wrong silently drops every webhook.
        'webhook_auth' => env('REVENUECAT_WEBHOOK_AUTH', ''),
    ],

];
