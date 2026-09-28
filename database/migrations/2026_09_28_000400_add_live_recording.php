<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rekaman live: satu file per "bagian" (bagian baru kalau browser perekam
        // tertutup lalu melanjutkan), dipotong bertahap mulai live_cursor_seconds.
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('live_user_id')->nullable()->after('processing_heartbeat_at')->constrained('users')->nullOnDelete();
            $table->timestamp('live_started_at')->nullable()->after('live_user_id');
            $table->unsignedInteger('live_part')->default(0)->after('live_started_at');
            $table->string('live_extension', 10)->nullable()->after('live_part');
            $table->unsignedBigInteger('live_bytes')->default(0)->after('live_extension');
            // Detik rekaman (sepanjang rapat, semua bagian) saat bagian aktif dimulai.
            $table->decimal('live_part_offset_seconds', 10, 3)->default(0)->after('live_bytes');
            $table->decimal('live_recorded_seconds', 10, 3)->default(0)->after('live_part_offset_seconds');
            // Sampai detik ke berapa rekaman sudah dipotong menjadi segmen.
            $table->decimal('live_cursor_seconds', 10, 3)->default(0)->after('live_recorded_seconds');
            // {"Pembicara 1": "Pak Budi", ...} — diterapkan ke transkrip.
            $table->json('speaker_names')->nullable()->after('live_cursor_seconds');
        });

        Schema::create('meeting_markers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // keputusan, tindak_lanjut
            $table->decimal('at_seconds', 10, 3);
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_markers');

        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('live_user_id');
            $table->dropColumn(['live_started_at', 'live_part', 'live_extension', 'live_bytes', 'live_part_offset_seconds',
                'live_recorded_seconds', 'live_cursor_seconds', 'speaker_names']);
        });
    }
};
