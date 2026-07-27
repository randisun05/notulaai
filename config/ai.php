<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Providers
    |--------------------------------------------------------------------------
    |
    | Provider mana yang dipakai untuk masing-masing kapabilitas AI. Setiap
    | provider di bawah harus punya konfigurasi di array "providers".
    | Mengganti provider cukup dengan mengubah env ini, tanpa menyentuh kode.
    */

    'default_text_provider' => env('AI_TEXT_PROVIDER', 'gemini'),
    'default_transcription_provider' => env('AI_TRANSCRIPTION_PROVIDER', 'whisper_local'),
    'default_ocr_provider' => env('AI_OCR_PROVIDER', 'gemini_ocr'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeout
    |--------------------------------------------------------------------------
    |
    | Batas detik menunggu respons HTTP untuk provider yang tidak punya
    | pengaturan timeout sendiri (mis. OpenRouter). Mencegah request yang
    | menggantung membuat proses rangkuman macet tanpa batas waktu.
    */

    'http_timeout' => env('AI_HTTP_TIMEOUT', 60),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Kredensial provider tetap disimpan di config/openai.php, config/services.php,
    | dsb agar tidak duplikat. Di sini hanya mapping nama provider ke driver
    | class dan pengaturan spesifik (model, dsb) yang dipakai AiManager.
    */

    'providers' => [
        'openrouter' => [
            'driver' => \App\Services\AI\Providers\OpenRouterTextProvider::class,
            'model' => env('AI_OPENROUTER_MODEL', 'openai/gpt-oss-20b:free'),
        ],

        'gemini' => [
            'driver' => \App\Services\AI\Providers\GeminiTextProvider::class,
            'model' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        ],

        'whisper_local' => [
            'driver' => \App\Services\AI\Providers\WhisperLocalTranscriptionProvider::class,
        ],

        'gemini_ocr' => [
            'driver' => \App\Services\AI\Providers\GeminiOcrProvider::class,
            'model' => env('AI_OCR_MODEL', 'gemini-2.0-flash'),
        ],
    ],
];
