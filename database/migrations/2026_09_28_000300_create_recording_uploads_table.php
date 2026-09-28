<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Upload rekaman bertahap (potongan beberapa MB) yang bisa dilanjutkan
        // setelah koneksi putus. File sementaranya di disk privat `local`.
        Schema::create('recording_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->timestamps();

            $table->index(['meeting_id', 'user_id', 'file_name', 'file_size']);
        });

        // Rekaman baru disimpan di disk privat (tidak bisa diunduh lewat /storage);
        // baris lama tetap menunjuk disk public.
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('source_disk')->default('public')->after('source_file_path');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn('source_disk');
        });

        Schema::dropIfExists('recording_uploads');
    }
};
