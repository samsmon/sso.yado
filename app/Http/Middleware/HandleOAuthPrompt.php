<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleOAuthPrompt
{
    /**
     * Handles the prompt=login parameter from OAuth clients (e.g. the "Add Account" feature in Malas).
     * If the user is authenticated and prompt=login is sent to /oauth/authorize,
     * log out the active user and redirect back to the authorize URL without prompt=login
     * so the Passport auth middleware sends the user to the login page to enter the new account's credentials.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('oauth/authorize') && $request->query('prompt') === 'login') {
            if (Auth::check()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $url = $request->fullUrlWithQuery(['prompt' => null]);

            return redirect($url);
        }

        return $next($request);
    }
}