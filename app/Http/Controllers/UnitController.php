<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * Menampilkan daftar semua unit.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Unit::class);

        $units = Unit::query()
            ->when($request->input('search'), function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Units/Index', [
            'units' => $units,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Menyimpan unit baru.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Unit::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units',
        ]);

        $unit = Unit::create($validated);

        $this->auditLogger->log(Auth::user(), 'unit.created', "Membuat unit \"{$unit->name}\"", $unit);

        return redirect()->back()->with('success', 'Unit berhasil dibuat.');
    }

    /**
     * Menampilkan halaman edit unit.
     * (Kita tidak membuat halaman terpisah, kita edit inline, tapi route ini bisa dipakai nanti)
     * Untuk saat ini, kita redirect saja.
     */
    public function edit(Unit $unit)
    {
         // Untuk simplicity, kita akan buat edit di halaman Index.
         // Tapi jika butuh halaman edit terpisah, ini adalah tempatnya.
         // return Inertia::render('Admin/Units/Edit', ['unit' => $unit]);
         return redirect()->route('admin.units.index');
    }

    /**
     * Update unit.
     */
    public function update(Request $request, Unit $unit)
    {
        $this->authorize('update', $unit);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units,name,' . $unit->id,
        ]);

        $unit->update($validated);

        $this->auditLogger->log(Auth::user(), 'unit.updated', "Memperbarui unit \"{$unit->name}\"", $unit);

        return redirect()->back()->with('success', 'Unit berhasil diperbarui.');
    }

    /**
     * Hapus unit.
     */
    public function destroy(Unit $unit)
    {
        $this->authorize('delete', $unit);

        // Pengecekan jika ada user di unit ini
        if ($unit->users()->count() > 0) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus unit yang masih memiliki anggota.');
        }

        $this->auditLogger->log(Auth::user(), 'unit.deleted', "Menghapus unit \"{$unit->name}\"");

        $unit->delete();
        return redirect()->back()->with('success', 'Unit berhasil dihapus.');
    }
}
