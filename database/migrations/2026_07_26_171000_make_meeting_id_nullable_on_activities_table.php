<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task Management (Phase 4) memungkinkan Task berdiri sendiri tanpa Meeting,
     * jadi Activity milik Task tersebut juga harus bisa dicatat tanpa meeting_id.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('meeting_id')->nullable(false)->change();
        });
    }
};
