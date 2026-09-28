<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daftar hadir: peserta memindai QR rapat (pegawai yang login, atau tamu
        // tanpa akun mengisi nama/jabatan/instansi), atau ditambah manual notulen.
        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('position')->nullable();     // jabatan
            $table->string('organization')->nullable(); // unit / instansi
            $table->string('method', 10);               // qr, manual
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            // Rahasia di URL QR (/hadir/{token}); bisa diganti kalau QR tersebar.
            $table->string('attendance_token', 40)->nullable()->unique()->after('speaker_names');
            $table->timestamp('attendance_closed_at')->nullable()->after('attendance_token');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropUnique(['attendance_token']);
            $table->dropColumn(['attendance_token', 'attendance_closed_at']);
        });

        Schema::dropIfExists('meeting_attendances');
    }
};
