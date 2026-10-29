<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Tiếp tục với Google": sign in, or sign up, with a Google account.
 *
 * The first time, the Google account is tied to the member with the same
 * email, or a new member is made for it. After that the member is found by
 * Google's id, so changing the email on either side does not lose them.
 */
class GoogleController extends Controller
{
    /**
     * Whether the site has a Google client configured (config/services.php).
     */
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * Send the visitor to Google to pick an account.
     */
    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Google sends the visitor back here with the account they picked.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Cancelled on Google's side, an expired state, or Google unreachable.
            report($e);

            return $this->failed('Không đăng nhập được bằng Google. Vui lòng thử lại.');
        }

        // Google only vouches for addresses it has checked.
        $verified = $google instanceof AbstractUser && ($google->getRaw()['email_verified'] ?? false) === true;

        if (blank($google->getEmail()) || ! $verified) {
            return $this->failed('Email của tài khoản Google này chưa được xác minh.');
        }

        $user = User::query()->where('google_id', $google->getId())->first()
            ?? User::query()->where('email', $google->getEmail())->first();

        if ($user === null) {
            if (CreateNewUser::registrationIsBlocked($google->getEmail())) {
                return $this->failed('Không thể đăng ký bằng email hoặc địa chỉ mạng này.');
            }

            $user = $this->register($google);
        } elseif ($user->google_id === null) {
            $this->link($user, $google);
        } elseif ($user->google_id !== $google->getId()) {
            return $this->failed('Email này đã được liên kết với một tài khoản Google khác.');
        }

        // Same as signing in with a password: a second factor, when the member
        // turned it on, still has to be given.
        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return to_route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * A new member for this Google account. They get a random password, so
     * they sign in with Google until they set one with "Quên mật khẩu".
     */
    private function register(GoogleUser $google): User
    {
        $user = new User([
            'name' => Str::limit((string) ($google->getName() ?: Str::before($google->getEmail(), '@')), 255, ''),
            'email' => $google->getEmail(),
            'password' => Str::random(40),
        ]);

        $user->forceFill(['google_id' => $google->getId(), 'email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Tie the Google account to the member already registered with its email.
     *
     * When that member never confirmed the email, nothing proves the account
     * was made by the owner of the address; Google just proved the visitor is.
     * So the ways in that whoever registered it may have set up (password,
     * passkeys, second factor) are dropped, and the account is the visitor's.
     */
    private function link(User $user, GoogleUser $google): void
    {
        DB::transaction(function () use ($user, $google): void {
            if (! $user->hasVerifiedEmail()) {
                $user->passkeys()->delete();
                $user->forceFill([
                    'password' => Str::random(40),
                    'remember_token' => null,
                    'two_factor_secret' => null,
                    'two_factor_recovery_codes' => null,
                    'two_factor_confirmed_at' => null,
                    'email_verified_at' => now(),
                ]);
            }

            $user->forceFill(['google_id' => $google->getId()])->save();
        });
    }

    private function failed(string $message): RedirectResponse
    {
        return to_route('login')->withErrors(['google' => $message]);
    }
}
