<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsSender;
use App\Services\Sms\SnsSmsSender;
use App\Support\EmailTemplate;
use App\Support\IntegrationSettings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Resolved on each use: an admin can switch the driver at runtime (IntegrationSettings).
        $this->app->bind(SmsSender::class, fn () => match (config('services.sms.driver')) {
            'sns' => SnsSmsSender::fromConfig((array) config('services.sns')),
            'log' => new LogSmsSender,
            default => throw new InvalidArgumentException('Unknown SMS driver ['.config('services.sms.driver').'].'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // SMTP and auction engine settings saved in the admin panel take over from .env.
        IntegrationSettings::apply();

        $this->useSiteEmailTemplate();
    }

    /**
     * The two emails the framework writes itself (confirm your address, reset
     * your password) go out in the site's own layout and wording too.
     */
    protected function useSiteEmailTemplate(): void
    {
        VerifyEmail::toMailUsing(fn (User $notifiable, string $url) => EmailTemplate::compose(
            'verify_email', [], $notifiable->name, $url,
        ));

        ResetPassword::toMailUsing(fn (User $notifiable, string $token) => EmailTemplate::compose(
            'reset_password',
            ['phut' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire')],
            $notifiable->name,
            url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false)),
        ));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // The policy is edited by admins at runtime (Filament > Chính sách mật khẩu).
        Password::defaults(function (): Password {
            $policy = Setting::passwordPolicy();

            $rule = Password::min($policy['min_length']);

            if ($policy['mixed_case']) {
                $rule->mixedCase();
            }

            if ($policy['numbers']) {
                $rule->numbers();
            }

            if ($policy['symbols']) {
                $rule->symbols();
            }

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
