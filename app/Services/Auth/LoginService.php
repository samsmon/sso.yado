<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as RequestFacade;
use Illuminate\Validation\ValidationException;

class LoginService
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    /**
     * @return bool true if it needs to continue to the two-factor challenge (not fully logged in yet)
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        $login = $credentials['email'];

        // Determine if input is email or username
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$field => $login, 'password' => $credentials['password']], $remember)) {
            $this->auditLog->record('auth.login_failed', "Login failed for \"{$login}\"");

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $this->auditLog->record('auth.login_failed', 'Login denied - account inactive', $user);

            throw ValidationException::withMessages([
                'email' => ['Your account is inactive.'],
            ]);
        }

        if ($user->hasTwoFactorEnabled()) {
            Auth::logout();
            RequestFacade::session()->put('mfa_pending_user_id', $user->id);
            RequestFacade::session()->put('mfa_remember', $remember);

            return true;
        }

        $this->auditLog->record('auth.login', 'Login successful', $user);

        return false;
    }
}
