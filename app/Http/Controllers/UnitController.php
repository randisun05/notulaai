<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    /**
     * Menampilkan daftar semua unit.
     */
    public function index(Request $request)
    {
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
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units',
        ]);

        Unit::create($validated);

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
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:units,name,' . $unit->id,
        ]);

        $unit->update($validated);

        return redirect()->back()->with('success', 'Unit berhasil diperbarui.');
    }

    /**
     * Hapus unit.
     */
    public function destroy(Unit $unit)
    {
        // Pengecekan jika ada user di unit ini
        if ($unit->users()->count() > 0) {
            return redirect()->back()->with('error', 'Tidak dapat menghapus unit yang masih memiliki anggota.');
        }

        $unit->delete();
        return redirect()->back()->with('success', 'Unit berhasil dihapus.');
    }
}
