<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Display a listing of the resource. A unit admin only sees their unit.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $actor = Auth::user();

        $users = User::query()
            ->visibleTo($actor)
            ->with('unit')
            // Grouped so the OR can't escape visibleTo()'s unit filter.
            ->when($request->input('search'), fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(10)->withQueryString();

        $users->getCollection()->each(fn (User $user) => $user->setAttribute('can_manage', $actor->can('update', $user)));

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'units' => $this->assignableUnits($actor),
            'assignableRoles' => $this->assignableRoles($actor),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $actor = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:'.User::class,
            'password' => ['required', Rules\Password::defaults()],
            'role' => ['required', Rule::in($this->assignableRoles($actor))],
            'unit_id' => 'nullable|exists:units,id',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'unit_id' => $this->unitFor($actor, $request->unit_id),
            'phone_number' => $request->phone_number,
        ]);

        $user->syncRoles([$request->role]);

        $this->auditLogger->log($actor, 'user.created', "Membuat user \"{$user->name}\" ({$user->email}) dengan role {$user->role}", $user);

        return to_route('admin.users.index')->with('success', 'User berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->load('unit'),
            'units' => $this->assignableUnits(Auth::user()),
            'assignableRoles' => $this->assignableRoles(Auth::user()),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $actor = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in($this->assignableRoles($actor))],
            'unit_id' => 'nullable|exists:units,id',
            'phone_number' => 'nullable|string|max:20',
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
        ]);

        $previousRole = $user->role;

        $user->fill($request->only(['name', 'email', 'role', 'phone_number']));
        $user->unit_id = $this->unitFor($actor, $request->unit_id);

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();
        $user->syncRoles([$request->role]);

        $this->auditLogger->log($actor, 'user.updated', "Memperbarui user \"{$user->name}\" ({$user->email})", $user, [
            'role_changed' => $previousRole !== $user->role,
            'previous_role' => $previousRole,
            'new_role' => $user->role,
        ]);

        return to_route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    /** Roles the actor may hand out: a unit admin can never create a superadmin. */
    private function assignableRoles(User $actor): array
    {
        return $actor->hasRole('superadmin') ? ['user', 'admin', 'superadmin'] : ['user', 'admin'];
    }

    private function assignableUnits(User $actor)
    {
        return Unit::query()
            ->when(! $actor->hasRole('superadmin'), fn ($q) => $q->whereKey($actor->unit_id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** A unit admin's users always stay in the admin's own unit, whatever was posted. */
    private function unitFor(User $actor, mixed $requestedUnitId): ?int
    {
        if (! $actor->hasRole('superadmin')) {
            return $actor->unit_id;
        }

        return $requestedUnitId ? (int) $requestedUnitId : null;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return to_route('admin.users.index')->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $this->authorize('delete', $user);

        $this->auditLogger->log(Auth::user(), 'user.deleted', "Menghapus user \"{$user->name}\" ({$user->email})");

        $user->delete();

        return to_route('admin.users.index')->with('success', 'User berhasil dihapus.');
    }
}
