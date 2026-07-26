<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Log terpisah dari App\Services\Meeting\ActivityLogger (feed rapat/task
 * yang dilihat pengguna biasa). AuditLogger ini untuk jejak aksi admin yang
 * sensitif (CRUD user/unit, ubah setting, terbitkan/cabut API token, login),
 * hanya bisa dilihat superadmin.
 */
class AuditLogger
{
    public function log(?User $actor, string $action, string $description, ?Model $subject = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
        ]);
    }
}
