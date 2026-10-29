<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\PhoneVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

/**
 * The phone number check on the profile page: text a code, then type it back.
 */
class PhoneVerificationController extends Controller
{
    /**
     * Text a code to the number on the member's profile.
     */
    public function store(Request $request, PhoneVerification $verification): RedirectResponse
    {
        try {
            $verification->send($request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            // The SMS gateway refused or could not be reached.
            report($e);

            throw ValidationException::withMessages(['code' => 'Không gửi được tin nhắn. Vui lòng thử lại sau ít phút.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã gửi mã xác thực tới số điện thoại của bạn.']);

        return to_route('profile.edit');
    }

    /**
     * Check the code the member typed back.
     */
    public function update(Request $request, PhoneVerification $verification): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']], ['code.digits' => 'Mã xác thực gồm 6 chữ số.']);

        $verification->verify($request->user(), (string) $request->input('code'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Đã xác thực số điện thoại.']);

        return to_route('profile.edit');
    }
}
