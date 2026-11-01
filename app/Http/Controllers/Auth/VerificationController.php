<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class VerificationController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLog) {}

    public function notice(): InertiaResponse
    {
        return Inertia::render('Auth/Verify');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        $this->auditLog->record('auth.email_verified', 'Email verified successfully', $request->user());

        return redirect()->route('account.show')->with('success', __('auth.email_verified'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.show');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', __('auth.verification_link_sent'));
    }
}
