<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_comment_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['forum_comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_comment_mentions');
    }
};
