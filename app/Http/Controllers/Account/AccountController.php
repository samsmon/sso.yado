<?php

namespace App\Http\Controllers\Account;

use App\Actions\Account\ChangePasswordAction;
use App\Actions\Account\RevokeSessionAction;
use App\Actions\Account\RevokeWebSessionAction;
use App\Actions\Account\UpdateAvatarAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(
        private readonly ChangePasswordAction $changePasswordAction,
        private readonly RevokeSessionAction $revokeSessionAction,
        private readonly RevokeWebSessionAction $revokeWebSessionAction,
        private readonly UpdateAvatarAction $updateAvatarAction,
        private readonly AuditLogService $auditLog,
    ) {}

    public function show(Request $request): InertiaResponse
    {
        return Inertia::render('Account/Show', ['user' => $request->user()]);
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->changePasswordAction->execute(
            $user,
            $validated['current_password'],
            $validated['new_password'],
        );

        $this->revokeSessionAction->revokeAll($user);
        $this->auditLog->record('account.password_changed', 'Password updated', $user);

        return back()->with('success', 'Password updated successfully. All other sessions have been signed out.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->updateAvatarAction->execute($user, $validated['avatar']);
        $this->auditLog->record('account.avatar_updated', 'Avatar updated', $user);

        return back()->with('success', __('account.avatar_updated'));
    }

    public function sessions(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokens = $user->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now()->toDateTimeString())
            ->with('client')
            ->orderByDesc('created_at')
            ->get();

        $devices = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get();

        return Inertia::render('Account/Sessions', [
            'tokens' => $tokens,
            'devices' => $devices,
            'currentSessionId' => $request->session()->getId(),
        ]);
    }

    public function revokeSession(Request $request, string $tokenId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->revokeSessionAction->execute($user, $tokenId);
        $this->auditLog->record('account.session_revoked', 'OAuth session dicabut', $user);

        return back()->with('success', 'Session revoked successfully.');
    }

    public function revokeAllSessions(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->revokeSessionAction->revokeAll($user);
        $this->auditLog->record('account.session_revoked', 'All OAuth sessions revoked', $user);

        return back()->with('success', 'All sessions revoked successfully.');
    }

    public function revokeDevice(Request $request, string $sessionId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->revokeWebSessionAction->execute($user, $sessionId);
        $this->auditLog->record('account.device_revoked', 'Device di-logout', $user);

        return back()->with('success', __('sessions.device_revoked'));
    }

    public function revokeAllDevices(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->revokeWebSessionAction->revokeAllExcept($user, $request->session()->getId());
        $this->auditLog->record('account.device_revoked', 'All other devices signed out', $user);

        return back()->with('success', __('sessions.all_other_devices_revoked'));
    }

    public function updateTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:system,light,dark'],
        ]);

        $request->user()->update(['theme' => $validated['theme']]);

        return response()->json(['status' => 'ok']);
    }

    public function updateLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:id,en,ja'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['locale' => $validated['locale']]);
        $request->session()->put('locale', $validated['locale']);

        return back()->with('success', __('account.locale_updated'));
    }
}
