<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use App\Models\User;
use Illuminate\Support\Facades\Gate; // <-- TAMBAHKAN IMPORT INI
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
         // TAMBAHKAN GATE INI
        // Gate ini akan memeriksa apakah role user adalah 'superadmin'
        Gate::define('access-admin-panel', function (User $user) {
            return $user->role === 'superadmin';
        });
    }
}
