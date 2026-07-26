<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->dateTime('date');
            $table->text('agenda');
            $table->string('attendees');
            $table->string('status')->default('Dijadwalkan'); // Dijadwalkan, Memproses, Selesai Diproses, Gagal
            $table->longText('transcript')->nullable();
            $table->longText('summary')->nullable();
            $table->string('source_file_path')->nullable(); // Menyimpan path file sumber jika ada
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
