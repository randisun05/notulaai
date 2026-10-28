<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

/** Webhooks are per unit: a unit admin manages only their unit's webhooks. */
class WebhookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'superadmin']);
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->hasRole('superadmin')
            || ($user->hasRole('admin') && filled($user->unit_id) && $webhook->unit_id === $user->unit_id);
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $this->update($user, $webhook);
    }
}
