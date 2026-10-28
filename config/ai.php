<?php

use App\Services\AI\Providers\GeminiOcrProvider;
use App\Services\AI\Providers\GeminiTextProvider;
use App\Services\AI\Providers\OpenRouterTextProvider;
use App\Services\AI\Providers\WhisperLocalTranscriptionProvider;

/*
| Gemini model IDs Google has already shut down (requests return 404). Older
| .env files still carry them (the previous .env.example shipped
| AI_GEMINI_MODEL=gemini-2.0-flash), so they are swapped for the default
| instead of silently failing every Gemini call into the OpenRouter fallback.
*/
$retiredGeminiModels = ['gemini-2.0-flash', 'gemini-2.0-flash-001', 'gemini-2.0-flash-lite', 'gemini-2.0-flash-lite-001'];

// "-latest" alias: Google keeps it pointed at the current Flash model, so a
// version retirement doesn't take the app down. Pin an exact ID via env if needed.
$geminiDefault = 'gemini-flash-latest';

$geminiModel = function (?string $configured) use ($retiredGeminiModels, $geminiDefault): string {
    $configured = trim((string) $configured);

    return $configured === '' || in_array($configured, $retiredGeminiModels, true) ? $geminiDefault : $configured;
};

// Price in USD per 1M tokens; null (unset env) means "unknown" and leaves cost null.
$price = fn (string $env, ?float $default = null): ?float => is_numeric(env($env)) ? (float) env($env) : $default;

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
    |
    | "pricing" (USD per 1 juta token, input/output) dipakai AiRequestLogger
    | untuk mengisi kolom ai_request_logs.cost. null = harga tidak diketahui,
    | cost dibiarkan kosong. Free tier Gemini: set AI_GEMINI_PRICE_* ke 0.
    */

    'providers' => [
        'openrouter' => [
            'driver' => OpenRouterTextProvider::class,
            'model' => $openRouterModel = env('AI_OPENROUTER_MODEL', 'openai/gpt-oss-20b:free'),
            // ":free" OpenRouter models cost nothing.
            'pricing' => [
                'input' => $price('AI_OPENROUTER_PRICE_INPUT', str_ends_with((string) $openRouterModel, ':free') ? 0.0 : null),
                'output' => $price('AI_OPENROUTER_PRICE_OUTPUT', str_ends_with((string) $openRouterModel, ':free') ? 0.0 : null),
            ],
        ],

        'gemini' => [
            'driver' => GeminiTextProvider::class,
            'model' => $geminiModel(env('AI_GEMINI_MODEL')),
            'pricing' => [
                'input' => $price('AI_GEMINI_PRICE_INPUT'),
                'output' => $price('AI_GEMINI_PRICE_OUTPUT'),
            ],
        ],

        'whisper_local' => [
            'driver' => WhisperLocalTranscriptionProvider::class,
            // Self-hosted: no per-request charge.
            'pricing' => ['input' => 0.0, 'output' => 0.0],
        ],

        'gemini_ocr' => [
            'driver' => GeminiOcrProvider::class,
            'model' => $geminiModel(env('AI_OCR_MODEL')),
            'pricing' => [
                'input' => $price('AI_GEMINI_PRICE_INPUT'),
                'output' => $price('AI_GEMINI_PRICE_OUTPUT'),
            ],
        ],
    ],
];
