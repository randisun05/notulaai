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

    /*
    |--------------------------------------------------------------------------
    | Rotasi API Key (sampai 5 key)
    |--------------------------------------------------------------------------
    |
    | GEMINI_API_KEY adalah key utama; GEMINI_API_KEY_2 s/d GEMINI_API_KEY_5
    | opsional. App\Services\AI\Gemini\GeminiKeyPool memakai key pertama yang
    | masih tersedia. Kalau Gemini menolak sebuah key karena kuota/rate limit
    | habis (429) atau key-nya tidak valid/diblokir (400/401/403), key itu
    | diistirahatkan dan request langsung diulang dengan key berikutnya.
    | Error lain (timeout, 5xx, prompt ditolak) tidak memicu rotasi.
    */

    'api_keys' => array_values(array_unique(array_filter(array_map(
        fn ($key) => trim((string) $key),
        [
            env('GEMINI_API_KEY'),
            env('GEMINI_API_KEY_2'),
            env('GEMINI_API_KEY_3'),
            env('GEMINI_API_KEY_4'),
            env('GEMINI_API_KEY_5'),
        ],
    )))),

    // Detik sebuah key dilewati setelah kena kuota/rate limit (429).
    'key_cooldown' => (int) env('GEMINI_KEY_COOLDOWN', 60),

    // Detik sebuah key dilewati setelah ditolak sebagai tidak valid/diblokir.
    'invalid_key_cooldown' => (int) env('GEMINI_INVALID_KEY_COOLDOWN', 3600),

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
