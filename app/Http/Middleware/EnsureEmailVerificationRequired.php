<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;

class EnsureEmailVerificationRequired
{
    /**
     * Enforce Fortify's email verification only when the application requires it.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('app.email_verification_required')) {
            return $next($request);
        }

        return app(EnsureEmailIsVerified::class)->handle($request, $next);
    }
}
