<?php

namespace App\Providers;

use App\Models\ForumComment;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Models\Webhook;
use App\Policies\ForumCommentPolicy;
use App\Policies\MeetingPolicy;
use App\Policies\TaskPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\WebhookPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Unit::class => UnitPolicy::class,
        Meeting::class => MeetingPolicy::class,
        Task::class => TaskPolicy::class,
        ForumComment::class => ForumCommentPolicy::class,
        Webhook::class => WebhookPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Dipakai oleh middleware route `can:access-admin-panel` (bukan Policy karena
        // bukan otorisasi atas sebuah model spesifik).
        // Superadmin-only admin pages: Unit, Pengaturan, Audit Log, Pemakaian AI.
        Gate::define('access-admin-panel', function (User $user) {
            return $user->hasRole('superadmin');
        });

        // Unit-level admin pages (User, Webhooks): an admin manages their own
        // unit there; the Policies narrow every record to that unit.
        Gate::define('access-unit-admin', function (User $user) {
            return $user->hasAnyRole(['admin', 'superadmin']);
        });
    }
}
