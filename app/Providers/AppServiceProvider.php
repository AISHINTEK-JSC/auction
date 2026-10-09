<?php

namespace App\Providers;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
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
