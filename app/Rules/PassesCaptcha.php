<?php

namespace App\Rules;

use App\Support\Captcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The submitted captcha token must be genuine. Use rules() to attach it: when
 * the captcha is off there is nothing to check and no field is expected.
 */
class PassesCaptcha implements ValidationRule
{
    /**
     * The rules for the captcha field, keyed by its name; empty when it is off.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return Captcha::enabled() ? [Captcha::FIELD => ['required', 'string', new self]] : [];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [Captcha::FIELD.'.required' => 'Vui lòng xác nhận bạn không phải là robot.'];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Captcha::verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('Xác nhận không thành công. Vui lòng thử lại.');
        }
    }
}
