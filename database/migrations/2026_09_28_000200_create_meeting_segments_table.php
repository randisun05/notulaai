<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rekaman panjang dipecah per ±10 menit; tiap bagian ditranskrip oleh job
        // sendiri supaya bisa diulang terpisah dan progresnya terlihat.
        Schema::create('meeting_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('index');
            $table->decimal('start_seconds', 10, 3);
            $table->decimal('end_seconds', 10, 3);
            $table->string('audio_path')->nullable();
            $table->string('status')->default('pending'); // pending, done, failed
            $table->longText('text')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'index']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            // transcribing -> summarizing; null saat tidak sedang diproses.
            $table->string('processing_stage')->nullable()->after('status');
            $table->unsignedInteger('processing_total_segments')->nullable()->after('processing_stage');
            // Disentuh setiap ada kemajuan — dasar watchdog meetings:fail-stuck.
            $table->timestamp('processing_heartbeat_at')->nullable()->after('processing_total_segments');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['processing_stage', 'processing_total_segments', 'processing_heartbeat_at']);
        });

        Schema::dropIfExists('meeting_segments');
    }
};
