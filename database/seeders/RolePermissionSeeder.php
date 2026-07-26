<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    private const ROLES = ['user', 'admin', 'superadmin'];

    /**
     * Buat role & permission, lalu backfill role setiap user berdasarkan
     * kolom `role` (string) yang masih ada agar data lama tetap konsisten.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'access-admin-panel']);

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        Role::findByName('superadmin')->givePermissionTo('access-admin-panel');

        User::whereDoesntHave('roles')->get()->each(function (User $user) {
            $legacy = strtolower(str_replace(' ', '', trim($user->role ?? '')));
            $role = in_array($legacy, self::ROLES, true) ? $legacy : 'user';

            $user->syncRoles([$role]);

            if ($user->role !== $role) {
                $user->forceFill(['role' => $role])->saveQuietly();
            }
        });
    }
}
