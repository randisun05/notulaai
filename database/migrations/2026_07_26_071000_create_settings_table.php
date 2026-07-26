<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel single-row untuk pengaturan aplikasi (tidak pakai id lookup, selalu baris pertama).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name')->default('AI Notula App');
            $table->string('company_logo_path')->nullable();
            $table->string('company_address')->nullable();
            $table->string('ai_text_provider')->default('openrouter');
            $table->string('ai_transcription_provider')->default('whisper_local');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
