<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Services;

use App\Modules\RBAC\Models\AuditLog;
use App\Modules\RBAC\Repositories\AuditLogRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    /**
     * Record an immutable audit trail entry for sensitive HR/RBAC events.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function record(
        string $event,
        Model $auditable,
        ?array $oldValues = null,
        ?array $newValues = null,
        int|string|null $actorId = null,
    ): AuditLog {
        /** @var AuditLog $entry */
        $entry = $this->auditLogRepository->create([
            'actor_id' => $actorId ?? Auth::id(),
            'event' => $event,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => (string) $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);

        return $entry;
    }
}
