<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Support\ImageOptimizer;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Replace the user's avatar.
     */
    public function avatar(Request $request): RedirectResponse
    {
        $request->validate(['avatar' => ['required', ...ImageOptimizer::rules(2048)]]);

        $user = $request->user();
        $previous = $user->avatar_path;

        $user->avatar_path = $this->storeAvatar($request->file('avatar'));
        $user->save();

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * A profile picture is only ever shown small: keep a small square of it.
     * A file that cannot be processed is kept as uploaded.
     */
    private function storeAvatar(UploadedFile $upload): string
    {
        try {
            $path = 'avatars/'.Str::random(40).'.webp';

            if (Storage::disk('public')->put($path, ImageOptimizer::avatar($upload->getRealPath()))) {
                return $path;
            }
        } catch (RuntimeException $e) {
            report($e);
        }

        return (string) $upload->store('avatars', 'public');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
