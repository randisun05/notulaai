<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AI\Contracts\OcrProvider;
use App\Services\AI\Contracts\TextGenerationProvider;
use App\Services\AI\Contracts\TranscriptionProvider;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit()
    {
        $this->authorize('access-admin-panel');

        return Inertia::render('Admin/Settings/Edit', [
            'setting' => Setting::current(),
            'textProviders' => $this->providersImplementing(TextGenerationProvider::class),
            'transcriptionProviders' => $this->providersImplementing(TranscriptionProvider::class),
            'ocrProviders' => $this->providersImplementing(OcrProvider::class),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorize('access-admin-panel');

        $textProviders = $this->providersImplementing(TextGenerationProvider::class);
        $transcriptionProviders = $this->providersImplementing(TranscriptionProvider::class);
        $ocrProviders = $this->providersImplementing(OcrProvider::class);

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_logo' => 'nullable|image|max:2048',
            'ai_text_provider' => ['required', Rule::in($textProviders)],
            'ai_transcription_provider' => ['required', Rule::in($transcriptionProviders)],
            'ai_ocr_provider' => ['required', Rule::in($ocrProviders)],
            'timezone' => 'required|timezone',
        ]);

        $setting = Setting::current();

        if ($request->hasFile('company_logo')) {
            if ($setting->company_logo_path) {
                Storage::disk('public')->delete($setting->company_logo_path);
            }
            $validated['company_logo_path'] = $request->file('company_logo')->store('branding', 'public');
        }
        unset($validated['company_logo']);

        $setting->update($validated);

        $this->auditLogger->log(Auth::user(), 'setting.updated', 'Memperbarui pengaturan aplikasi', $setting);

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    /**
     * @return array<int, string>
     */
    private function providersImplementing(string $interface): array
    {
        return collect(config('ai.providers'))
            ->filter(fn (array $config) => is_a($config['driver'], $interface, true))
            ->keys()
            ->values()
            ->all();
    }
}
