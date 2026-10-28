<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Webhook;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class WebhookController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index()
    {
        $this->authorize('viewAny', Webhook::class);

        $actor = Auth::user();
        $isSuperadmin = $actor->hasRole('superadmin');

        return Inertia::render('Admin/Webhooks/Index', [
            'webhooks' => Webhook::query()
                ->when(! $isSuperadmin, fn ($q) => $q->where('unit_id', $actor->unit_id))
                ->with('unit:id,name')
                ->latest()
                ->get(),
            'units' => Unit::query()
                ->when(! $isSuperadmin, fn ($q) => $q->whereKey($actor->unit_id))
                ->orderBy('name')
                ->get(['id', 'name']),
            'availableEvents' => Webhook::EVENTS,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Webhook::class);

        $actor = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // A unit admin may only target their own unit.
            'unit_id' => ['required', 'exists:units,id', ...($actor->hasRole('superadmin') ? [] : [Rule::in([$actor->unit_id])])],
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => [Rule::in(Webhook::EVENTS)],
        ]);

        $webhook = Webhook::create([
            ...$validated,
            'created_by' => $actor->id,
            'secret' => Str::random(40),
            'is_active' => true,
        ]);

        $this->auditLogger->log($actor, 'webhook.created', "Membuat webhook \"{$webhook->name}\"", $webhook);

        return back()->with('success', 'Webhook berhasil dibuat.');
    }

    public function update(Request $request, Webhook $webhook)
    {
        $this->authorize('update', $webhook);

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $webhook->update($validated);

        $this->auditLogger->log(Auth::user(), 'webhook.updated', ($validated['is_active'] ? 'Mengaktifkan' : 'Menonaktifkan')." webhook \"{$webhook->name}\"", $webhook);

        return back()->with('success', 'Webhook berhasil diperbarui.');
    }

    public function destroy(Webhook $webhook)
    {
        $this->authorize('delete', $webhook);

        $this->auditLogger->log(Auth::user(), 'webhook.deleted', "Menghapus webhook \"{$webhook->name}\"");

        $webhook->delete();

        return back()->with('success', 'Webhook berhasil dihapus.');
    }
}
