<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Auth;

class InviteController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        $invitation = $this->resolveInvitation($request->query('token', ''));

        if (! $invitation) {
            return Inertia::render('Auth/Invite', ['error' => $this->tokenError($request->query('token', ''))]);
        }

        return Inertia::render('Auth/Invite', ['invitation' => $invitation]);
    }

    public function store(Request $request): RedirectResponse
    {
        $invitation = $this->resolveInvitation($request->input('token', ''));

        if (! $invitation) {
            return back()->withErrors(['token' => $this->tokenError($request->input('token', ''))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username', 'regex:/^[a-z0-9_]+$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $invitation->email,
            'password' => $validated['password'],
            'role_id' => $invitation->role_id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $invitation->update(['used_at' => now()]);
        $this->auditLog->record('dashboard.invite_accepted', 'Invitation accepted, account created', $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.show');
    }

    private function resolveInvitation(string $token): ?UserInvitation
    {
        if (empty($token)) {
            return null;
        }

        $invitation = UserInvitation::with('role')->where('token', $token)->first();

        if (! $invitation || ! $invitation->isValid()) {
            return null;
        }

        return $invitation;
    }

    private function tokenError(string $token): string
    {
        if (empty($token)) {
            return 'Invalid invitation link.';
        }

        $invitation = UserInvitation::where('token', $token)->first();

        if (! $invitation) {
            return 'Invalid invitation link.';
        }

        if ($invitation->used_at) {
            return 'This invitation link has already been used.';
        }

        return 'Link undangan telah kedaluwarsa.';
    }
}
