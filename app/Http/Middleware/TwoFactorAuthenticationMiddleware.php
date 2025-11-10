<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorAuthenticationMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            // Check if password has been confirmed
            if (! $this->isPasswordConfirmed($request)) {
                return redirect()->route('password.confirm');
            }
        }

        return $next($request);
    }

    /**
     * Determine if the password was recently confirmed.
     */
    private function isPasswordConfirmed(Request $request): bool
    {
        $passwordConfirmedAt = $request->session()->get('auth.password_confirmed_at');

        return $passwordConfirmedAt && $passwordConfirmedAt + config('auth.password_timeout', 900) > time();
    }
}
