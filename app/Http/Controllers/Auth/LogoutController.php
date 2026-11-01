<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RevokeTokenAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __construct(
        private readonly RevokeTokenAction $revokeTokenAction,
        private readonly AuditLogService $auditLog,
    ) {}

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user) {
            $this->revokeTokenAction->execute($user);
            $this->auditLog->record('auth.logout', 'Logout', $user);
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectUri = $request->query('redirect_uri');

        if ($redirectUri && $this->isAllowedRedirectUri($redirectUri)) {
            return redirect()->away($redirectUri);
        }

        return redirect()->route('login');
    }

    private function isAllowedRedirectUri(string $uri): bool
    {
        $parsed = parse_url($uri);

        if (empty($parsed['host'])) {
            return false;
        }

        // Only allow HTTPS or localhost
        $scheme = $parsed['scheme'] ?? '';
        if (!in_array($scheme, ['https', 'http'], true)) {
            return false;
        }

        $host = $parsed['host'];

        // Localhost is always allowed (development)
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        // Cek apakah host terdaftar di salah satu OAuth client redirect_uris
        return \Laravel\Passport\Client::where('revoked', false)
            ->get()
            ->contains(function ($client) use ($host) {
                foreach ($client->redirect_uris as $registeredUri) {
                    if (parse_url($registeredUri, PHP_URL_HOST) === $host) {
                        return true;
                    }
                }
                return false;
            });
    }
}
