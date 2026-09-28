<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notulen resmi (format dinas) — satu per rapat. Isi disusun AI lalu
        // disunting notulis; setelah disahkan pimpinan, terkunci.
        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();

            // Identitas rapat
            $table->string('number')->nullable();
            $table->string('location')->nullable();
            $table->string('time_range')->nullable();
            $table->foreignId('chairperson_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('chairperson_name')->nullable();
            $table->string('chairperson_title')->nullable();
            $table->foreignId('minute_taker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('attendees')->nullable();
            $table->text('agenda')->nullable();

            // Isi
            $table->text('opening')->nullable();
            $table->json('discussion')->nullable();   // [{topic, notes}]
            $table->json('decisions')->nullable();    // [string]
            $table->text('closing')->nullable();

            // Pengesahan
            $table->string('status')->default('draf'); // draf, diajukan, disahkan, dikembalikan
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('return_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_minutes');
    }
};
