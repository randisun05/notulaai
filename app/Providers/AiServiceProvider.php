<?php

namespace App\Providers;

use App\Services\AI\AiManager;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\Contracts\TranscriptionProvider;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiManager::class);

        $this->app->bind(TextGenerationProvider::class, fn ($app) => $app->make(AiManager::class)->text());
        $this->app->bind(TranscriptionProvider::class, fn ($app) => $app->make(AiManager::class)->transcription());
    }
}
