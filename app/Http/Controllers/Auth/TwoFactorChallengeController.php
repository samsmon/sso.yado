<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly AuditLogService $auditLog,
    ) {}

    public function show(Request $request): InertiaResponse|RedirectResponse
    {
        if (! $request->session()->has('mfa_pending_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function verify(Request $request): Response
    {
        $userId = $request->session()->get('mfa_pending_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string']]);

        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $code = $request->input('code');
        $isValid = $this->twoFactor->verify($user->two_factor_secret, $code)
            || $this->twoFactor->verifyAndConsumeRecoveryCode($user, $code);

        if (! $isValid) {
            $this->auditLog->record('account.two_factor_challenge_failed', 'Wrong 2FA code at login', $user);

            return back()->withErrors(['code' => __('two_factor.invalid_code')]);
        }

        $remember = $request->session()->get('mfa_remember', false);
        $request->session()->forget(['mfa_pending_user_id', 'mfa_remember']);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->auditLog->record('auth.login', 'Login successful (2FA)', $user);

        $intended = $request->session()->pull('url.intended');

        if ($intended) {
            $intendedUrl = preg_replace('/([?&])prompt=login(&|$)/', '$1', $intended);
            $intendedUrl = rtrim(rtrim($intendedUrl, '&'), '?');

            return Inertia::location($intendedUrl);
        }

        $default = $user->role?->slug === 'superadmin'
            ? route('dashboard.index')
            : route('account.show');

        return redirect()->intended($default);
    }
}
