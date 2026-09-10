<?php

use App\Services\AI\Providers\GeminiOcrProvider;
use App\Services\AI\Providers\GeminiTextProvider;
use App\Services\AI\Providers\OpenRouterTextProvider;
use App\Services\AI\Providers\WhisperLocalTranscriptionProvider;

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
    | Fallback Providers
    |--------------------------------------------------------------------------
    |
    | Kalau provider aktif gagal/timeout, AiManager mencoba provider berikut
    | dalam daftar ini secara berurutan sebelum menyerah. Comma-separated,
    | nama harus ada di array "providers". Kosongkan untuk menonaktifkan
    | (mis. AI_TEXT_FALLBACKS= tanpa nilai).
    |
    | Default: text jatuh ke "openrouter" (kalau OPENAI_API_KEY diisi). STT/OCR
    | belum punya alternatif bawaan.
    */

    'fallbacks' => [
        'text' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_TEXT_FALLBACKS', 'openrouter'))))),
        'transcription' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_TRANSCRIPTION_FALLBACKS', ''))))),
        'ocr' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_OCR_FALLBACKS', ''))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh limiter "ai" (App\Providers\AppServiceProvider) untuk semua
    | endpoint yang memanggil LLM: chat per rapat, generate/kirim email AI,
    | generate ulang action items, proses notula, dan dashboard insight.
    | Tiga lapis: per menit & per hari per user, plus batas harian per unit.
    */

    'rate_limits' => [
        'per_minute' => (int) env('AI_RATE_PER_MINUTE', 20),
        'per_day' => (int) env('AI_RATE_PER_DAY', 200),
        'per_unit_per_day' => (int) env('AI_RATE_PER_UNIT_PER_DAY', 1500),
    ],

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
            'driver' => OpenRouterTextProvider::class,
            'model' => env('AI_OPENROUTER_MODEL', 'openai/gpt-oss-20b:free'),
        ],

        'gemini' => [
            'driver' => GeminiTextProvider::class,
            'model' => env('AI_GEMINI_MODEL', 'gemini-2.0-flash'),
        ],

        'whisper_local' => [
            'driver' => WhisperLocalTranscriptionProvider::class,
        ],

        'gemini_ocr' => [
            'driver' => GeminiOcrProvider::class,
            'model' => env('AI_OCR_MODEL', 'gemini-2.0-flash'),
        ],
    ],
];
