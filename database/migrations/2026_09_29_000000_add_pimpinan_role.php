<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Peran "pimpinan" untuk instalasi yang sudah berjalan (instalasi baru mendapatkannya
 * dari RolePermissionSeeder): melihat rapat & tindak lanjut semua unit, hanya baca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Role::firstOrCreate(['name' => 'pimpinan', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'pimpinan')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
