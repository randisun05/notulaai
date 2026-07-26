<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('access-admin-panel');

        $users = User::query()
            ->with('unit')
            ->when($request->input('search'), function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->paginate(10)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'units' => Unit::all(['id', 'name']), // Kirim data unit untuk form tambah
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('access-admin-panel');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', Rules\Password::defaults()],
            'role' => ['required', Rule::in(['User', 'Admin', 'Super Admin'])],
            'unit_id' => 'nullable|exists:units,id',
            'phone_number' => 'nullable|string|max:20',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'unit_id' => $request->unit_id,
            'phone_number' => $request->phone_number,
        ]);

        return to_route('admin.users.index')->with('success', 'User berhasil dibuat.');
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('access-admin-panel');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('unit'),
            'units' => Unit::all(['id', 'name']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('access-admin-panel');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['User', 'Admin', 'Super Admin'])],
            'unit_id' => 'nullable|exists:units,id',
            'phone_number' => 'nullable|string|max:20',
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->fill($request->only(['name', 'email', 'role', 'unit_id', 'phone_number']));

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return to_route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('access-admin-panel');

        // Tambahan: Logika untuk mencegah Super Admin menghapus diri sendiri
        if ($user->id === auth()->id()) {
            return to_route('admin.users.index')->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return to_route('admin.users.index')->with('success', 'User berhasil dihapus.');
    }
}

