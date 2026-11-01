<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Dashboard\InviteUserAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\Dashboard\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementService $service,
        private readonly InviteUserAction $inviteAction,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Dashboard/Users/Index', [
            'users' => $this->service->list(),
            'roles' => Role::all(),
        ]);
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->service->toggleActive($user, $request->user());

        return back()->with('success', 'User status updated.');
    }

    public function assignRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $this->service->assignRole($user, $validated['role_id'], $request->user());

        return back()->with('success', 'User role updated.');
    }

    public function invite(): InertiaResponse
    {
        return Inertia::render('Dashboard/Users/Invite', ['roles' => Role::all()]);
    }

    public function sendInvite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $this->inviteAction->execute($request->user(), $validated['email'], $validated['role_id']);

        return redirect()->route('dashboard.users.index')
            ->with('success', "Invitation sent to {$validated['email']}.");
    }

    public function changePassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->service->changeUserPassword($user, $validated['new_password']);

        return back()->with('success', "Password for user {$user->name} changed successfully.");
    }
}
