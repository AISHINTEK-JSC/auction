<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsNotBlocked
{
    /**
     * Sign a blocked user out on their next request and send them to the
     * "account locked" page, where they can ask an admin to unlock it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isBlocked()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('blocked')->with([
            'blocked_email' => $user->email,
            'blocked_reason' => $user->block_reason,
        ]);
    }
}
