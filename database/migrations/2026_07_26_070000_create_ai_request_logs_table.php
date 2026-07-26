<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // text | transcription
            $table->string('provider');
            $table->string('model')->nullable();
            $table->longText('prompt')->nullable();
            $table->longText('response')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->float('duration_ms')->nullable();
            $table->string('status'); // success | failed
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_logs');
    }
};
