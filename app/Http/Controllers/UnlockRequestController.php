<?php

namespace App\Http\Controllers;

use App\Models\UnlockRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnlockRequestController extends Controller
{
    /**
     * Where a blocked user lands after being signed out.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Blocked', [
            'email' => $request->session()->get('blocked_email'),
            'reason' => $request->session()->get('blocked_reason'),
            'sent' => (bool) $request->session()->get('unlock_request_sent', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        UnlockRequest::query()->create($data);

        return to_route('blocked')->with('unlock_request_sent', true);
    }
}
