<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AuditAuthEvents
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function handleLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $this->auditLogger->log($user, 'auth.login', "{$user->name} login");
    }

    public function handleLogout(Logout $event): void
    {
        /** @var User|null $user */
        $user = $event->user;

        if (!$user) {
            return;
        }

        $this->auditLogger->log($user, 'auth.logout', "{$user->name} logout");
    }
}
