<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Form rapat memperlakukan agenda & peserta sebagai opsional, tapi kolomnya
     * NOT NULL — input kosong (dikonversi jadi null oleh middleware) memicu 500.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->text('agenda')->nullable()->change();
            $table->string('attendees')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->text('agenda')->nullable(false)->change();
            $table->string('attendees')->nullable(false)->change();
        });
    }
};
