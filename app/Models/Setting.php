<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name',
        'company_logo_path',
        'company_address',
        'ai_text_provider',
        'ai_transcription_provider',
        'timezone',
    ];

    /**
     * Pengaturan aplikasi disimpan sebagai satu baris tunggal.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'company_name' => config('app.name', 'AI Notula App'),
            'ai_text_provider' => config('ai.default_text_provider'),
            'ai_transcription_provider' => config('ai.default_transcription_provider'),
            'timezone' => config('app.timezone', 'Asia/Jakarta'),
        ]);
    }
}
