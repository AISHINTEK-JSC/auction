<?php

namespace App\Services;

use App\Models\User;
use App\Services\Sms\SmsSender;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Confirms that the number on a member's profile is theirs: a six-digit code
 * is texted to it and has to be typed back on the profile page.
 *
 * Only a keyed hash of the code is kept, in the cache, for a few minutes.
 * Every text costs money, so sending is limited per member and per number,
 * and a code is thrown away after a few wrong guesses.
 */
class PhoneVerification
{
    public const CODE_MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    /** Seconds before another code can be asked for. */
    private const RESEND_SECONDS = 60;

    /** Codes a member, or a number, can receive in 24 hours. */
    private const DAILY_LIMIT = 5;

    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Text a new code to the member's number. Any earlier code stops working.
     *
     * @throws ValidationException when the number cannot be verified or the limits are reached
     */
    public function send(User $user): void
    {
        $this->ensureCanVerify($user);

        $phone = PhoneNumber::international((string) $user->phone);

        foreach ([
            $this->limiterKey('resend', $user->id) => [1, self::RESEND_SECONDS],
            $this->limiterKey('member', $user->id) => [self::DAILY_LIMIT, 86400],
            $this->limiterKey('number', $phone) => [self::DAILY_LIMIT, 86400],
        ] as $key => [$max, $decay]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                throw ValidationException::withMessages(['code' => $seconds <= self::RESEND_SECONDS
                    ? "Vui lòng đợi {$seconds} giây rồi gửi lại mã."
                    : 'Bạn đã yêu cầu quá nhiều mã hôm nay. Vui lòng thử lại sau.']);
            }
        }

        // Counted before sending: a failing gateway must not be retried without limit.
        RateLimiter::hit($this->limiterKey('resend', $user->id), self::RESEND_SECONDS);
        RateLimiter::hit($this->limiterKey('member', $user->id), 86400);
        RateLimiter::hit($this->limiterKey('number', $phone), 86400);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::CODE_MINUTES)->getTimestamp();

        Cache::put($this->cacheKey($user), [
            'phone' => $user->phone,
            'code' => $this->hash($code),
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ], now()->setTimestamp($expiresAt));

        // Plain ASCII: a message with Vietnamese accents costs twice as much per text.
        $this->sms->send($phone, sprintf(
            '%s: ma xac thuc so dien thoai cua ban la %s. Ma het han sau %d phut. Khong chia se ma nay cho bat ky ai.',
            Str::ascii((string) config('app.name')),
            $code,
            self::CODE_MINUTES,
        ));
    }

    /**
     * Check a code typed back by the member and, when right, mark their number verified.
     *
     * @throws ValidationException when the code is wrong, used up or expired
     */
    public function verify(User $user, string $code): void
    {
        $this->ensureCanVerify($user);

        $key = $this->cacheKey($user);
        $pending = Cache::get($key);

        // No code, an expired one, or one sent to a number changed since.
        if (! is_array($pending) || $pending['phone'] !== $user->phone || $pending['expires_at'] <= now()->getTimestamp()) {
            throw ValidationException::withMessages(['code' => 'Mã đã hết hạn. Vui lòng gửi mã mới.']);
        }

        if (! hash_equals($pending['code'], $this->hash($code))) {
            $pending['attempts']++;

            if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
                Cache::forget($key);

                throw ValidationException::withMessages(['code' => 'Bạn đã nhập sai quá nhiều lần. Vui lòng gửi mã mới.']);
            }

            Cache::put($key, $pending, now()->setTimestamp($pending['expires_at']));

            throw ValidationException::withMessages(['code' => 'Mã xác thực không đúng.']);
        }

        Cache::forget($key);

        $user->forceFill(['phone_verified_at' => now()])->save();
    }

    /**
     * A code was sent to the member's current number and can still be typed in.
     */
    public function pending(User $user): bool
    {
        $pending = Cache::get($this->cacheKey($user));

        return is_array($pending) && $pending['phone'] === $user->phone && $pending['expires_at'] > now()->getTimestamp();
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanVerify(User $user): void
    {
        if (blank($user->phone)) {
            throw ValidationException::withMessages(['code' => 'Hãy nhập số điện thoại trong hồ sơ và bấm Lưu trước.']);
        }

        if ($user->hasVerifiedPhone()) {
            throw ValidationException::withMessages(['code' => 'Số điện thoại này đã được xác thực.']);
        }

        // One number proves one account: otherwise a single SIM could vouch for many.
        $taken = User::query()
            ->whereKeyNot($user->id)
            ->whereIn('phone', PhoneNumber::variants($user->phone))
            ->whereNotNull('phone_verified_at')
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['code' => 'Số điện thoại này đã được xác thực cho một tài khoản khác.']);
        }
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function cacheKey(User $user): string
    {
        return "phone-verification.{$user->id}";
    }

    private function limiterKey(string $scope, int|string $id): string
    {
        return "phone-verification-{$scope}:{$id}";
    }
}
