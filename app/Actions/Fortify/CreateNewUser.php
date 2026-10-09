<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\BlockedEmailDomain;
use App\Models\BlockedIp;
use App\Models\User;
use App\Rules\PassesCaptcha;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            ...PassesCaptcha::rules(),
        ], PassesCaptcha::messages())->after(function (ValidatorContract $validator) use ($input): void {
            if ($this->registrationIsBlocked((string) ($input['email'] ?? ''))) {
                $validator->errors()->add('email', 'Không thể đăng ký bằng email hoặc địa chỉ mạng này.');
            }
        })->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }

    /**
     * Admins can block registration by IP address or by email domain.
     */
    private function registrationIsBlocked(string $email): bool
    {
        $domain = Str::lower(Str::after($email, '@'));

        return BlockedIp::query()->where('address', request()->ip())->exists()
            || BlockedEmailDomain::query()->where('domain', $domain)->exists();
    }
}
