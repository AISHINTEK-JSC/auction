<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    /**
     * Bidding and posting listings need a confirmed phone number: send a
     * member without one to their profile, where they can confirm it.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedPhone()) {
            return $next($request);
        }

        $message = 'Bạn cần xác thực số điện thoại trước khi đặt giá hoặc đăng tin.';

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            abort(403, $message);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

        return to_route('profile.edit');
    }
}
