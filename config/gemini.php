<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    |
    | Dibaca oleh package google-gemini-php/laravel (facade Gemini::) untuk
    | membangun client singleton-nya. Terpisah dari config/services.php yang
    | dipakai bagian lain aplikasi (Socialite dsb).
    */

    'api_key' => env('GEMINI_API_KEY'),

    'base_url' => env('GEMINI_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Maksimal detik menunggu respons dari Gemini API sebelum request dianggap
    | gagal. Tanpa ini, request yang menggantung bisa membuat proses rangkuman
    | macet tanpa batas waktu.
    */

    'request_timeout' => env('GEMINI_REQUEST_TIMEOUT', 60),
];
