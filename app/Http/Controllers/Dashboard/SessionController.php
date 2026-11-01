<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\Dashboard\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SessionController extends Controller
{
    public function __construct(
        private readonly SessionService $service,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(): InertiaResponse
    {
        return Inertia::render('Dashboard/Sessions/Index', [
            'oauthSessions' => $this->service->allOAuthSessions(),
            'webSessions'   => $this->service->allWebSessions(),
        ]);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $type = $request->query('type', 'oauth');

        if ($type === 'web') {
            if ($id === session()->getId()) {
                return back()->with('error', 'You cannot revoke your own active session.');
            }
            $this->service->revokeWebSession($id);
        } else {
            $this->service->revokeOAuthSession($id);
        }

        $this->auditLog->record('dashboard.session_revoked', "Session ({$type}) revoked by superadmin");

        return back()->with('success', 'Session revoked successfully.');
    }
}
