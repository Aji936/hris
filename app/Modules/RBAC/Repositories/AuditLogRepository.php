<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Repositories;

use App\Modules\RBAC\Models\AuditLog;
use App\Shared\Repositories\BaseRepository;

/**
 * @extends BaseRepository<AuditLog>
 */
class AuditLogRepository extends BaseRepository
{
    protected function model(): string
    {
        return AuditLog::class;
    }
}
