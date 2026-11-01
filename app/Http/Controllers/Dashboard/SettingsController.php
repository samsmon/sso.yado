<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\Dashboard\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Dashboard/Settings/Index', [
            'settings' => $this->settings->all(),
        ]);
    }

    public function updateMail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mail_driver' => ['required', 'in:resend,smtp'],
            'mail_from_address' => ['required', 'email'],
            'mail_from_name' => ['required', 'string', 'max:255'],
            'resend_api_key' => ['nullable', 'string'],
            'smtp_host' => ['nullable', 'string'],
            'smtp_port' => ['nullable', 'integer'],
            'smtp_username' => ['nullable', 'string'],
            'smtp_password' => ['nullable', 'string'],
            'smtp_encryption' => ['nullable', 'in:tls,ssl'],
        ]);

        $this->settings->saveMail($validated);
        $this->settings->applyToRuntimeConfig();
        $this->auditLog->record('settings.mail_updated', 'Email configuration updated');

        return back()->with('success', __('settings.mail_saved'));
    }

    public function updateAvatarStorage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'avatar_disk' => ['required', 'in:local,s3'],
            's3_key' => ['nullable', 'string'],
            's3_secret' => ['nullable', 'string'],
            's3_region' => ['nullable', 'string'],
            's3_bucket' => ['nullable', 'string'],
            's3_endpoint' => ['nullable', 'string'],
        ]);

        $this->settings->saveAvatarStorage($validated);
        $this->settings->applyToRuntimeConfig();
        $this->auditLog->record('settings.avatar_storage_updated', 'Avatar storage configuration updated');

        return back()->with('success', __('settings.avatar_storage_saved'));
    }

    public function sendTestEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        Mail::raw('Test email from SSO Engine - your mail configuration works.', function ($message) use ($validated) {
            $message->to($validated['test_email'])->subject('SSO Engine - Test Email');
        });

        return back()->with('success', __('settings.test_email_sent', ['email' => $validated['test_email']]));
    }
}
