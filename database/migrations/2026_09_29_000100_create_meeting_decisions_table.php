<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Register keputusan lintas rapat: diekstrak AI saat notula selesai, lalu
        // diganti "Kesimpulan rapat" notulen resmi begitu disahkan (source = notula).
        Schema::create('meeting_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->text('text');
            $table->string('source', 10); // ai, notula
            // Tindak lanjut yang menjalankan keputusan ini → status diambil dari Task-nya.
            $table->foreignId('meeting_action_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->index(['unit_id', 'meeting_id']);
        });

        // Rapat berseri: rapat ini lanjutan dari rapat mana.
        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('previous_meeting_id')->nullable()->after('unit_id')->constrained('meetings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_meeting_id');
        });
        Schema::dropIfExists('meeting_decisions');
    }
};
