<?php

declare(strict_types=1);

namespace App\Modules\RBAC\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\RBAC\Requests\SyncUserRolesRequest;
use App\Modules\RBAC\Services\RoleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleManagementService $roleManagementService,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $roles = $this->roleManagementService->listRolesWithPermissions();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function syncUserRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        Gate::authorize('manage', Role::class);

        /** @var list<string> $roles */
        $roles = $request->validated('roles');

        $updatedUser = $this->roleManagementService->syncUserRoles($user, $roles);

        return response()->json([
            'data' => [
                'id' => $updatedUser->id,
                'roles' => $updatedUser->getRoleNames()->values()->all(),
            ],
        ]);
    }
}
