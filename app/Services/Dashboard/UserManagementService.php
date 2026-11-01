<?php

namespace App\Services\Dashboard;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function list(): LengthAwarePaginator
    {
        return User::with('role')->orderByDesc('created_at')->paginate(20);
    }

    public function toggleActive(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        $user->update(['is_active' => ! $user->is_active]);

        $this->auditLog->record(
            $user->is_active ? 'dashboard.user_activated' : 'dashboard.user_deactivated',
            ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan') . " user \"{$user->email}\"",
            $user,
        );
    }

    public function assignRole(User $user, int $roleId, User $actor): void
    {
        $role = Role::findOrFail($roleId);
        $wasSuperadmin = $user->role?->slug === 'superadmin';
        $willBeSuperadmin = $role->slug === 'superadmin';

        if ($user->id === $actor->id && ! $willBeSuperadmin) {
            throw ValidationException::withMessages([
                'role_id' => 'You cannot lower your own account's role.',
            ]);
        }

        if ($wasSuperadmin && ! $willBeSuperadmin) {
            $remainingSuperadmins = User::where('role_id', $user->role_id)
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($remainingSuperadmins === 0) {
                throw ValidationException::withMessages([
                    'role_id' => 'Cannot demote the last remaining superadmin.',
                ]);
            }
        }

        $user->update(['role_id' => $roleId]);

        $this->auditLog->record('dashboard.user_role_changed', "Role of user \"{$user->email}\" changed to \"{$role->name}\"", $user);
    }

    public function changeUserPassword(User $user, string $newPassword): void
    {
        $user->update(['password' => bcrypt($newPassword)]);

        $this->auditLog->record('dashboard.user_password_changed', "Password of user \"{$user->email}\" changed by superadmin", $user);
    }
}
