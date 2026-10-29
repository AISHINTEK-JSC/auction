<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use App\Enums\Gender;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()->id),
            'phone' => ['nullable', 'string', PhoneNumber::RULE],
            'address' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'before:today', 'after_or_equal:1900-01-01'],

            // The shipping address is all or nothing: half an address cannot be delivered to.
            'shipping_name' => ['nullable', 'string', 'max:255', 'required_with:shipping_phone,shipping_city_id,shipping_address'],
            'shipping_phone' => ['nullable', 'string', PhoneNumber::RULE, 'required_with:shipping_name,shipping_city_id,shipping_address'],
            'shipping_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id'), 'required_with:shipping_name,shipping_phone,shipping_address'],
            'shipping_address' => ['nullable', 'string', 'max:255', 'required_with:shipping_name,shipping_phone,shipping_city_id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'gender' => 'giới tính',
            'birthday' => 'ngày sinh',
            'shipping_name' => 'tên người nhận',
            'shipping_phone' => 'số điện thoại nhận hàng',
            'shipping_city_id' => 'tỉnh / thành phố',
            'shipping_address' => 'địa chỉ nhận hàng',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'birthday.before' => 'Ngày sinh phải là một ngày trong quá khứ.',
            'birthday.after_or_equal' => 'Ngày sinh không hợp lệ.',
            'shipping_name.required_with' => 'Nhập tên người nhận để lưu địa chỉ nhận hàng.',
            'shipping_phone.required_with' => 'Nhập số điện thoại nhận hàng để lưu địa chỉ nhận hàng.',
            'shipping_city_id.required_with' => 'Chọn tỉnh / thành phố để lưu địa chỉ nhận hàng.',
            'shipping_address.required_with' => 'Nhập địa chỉ chi tiết để lưu địa chỉ nhận hàng.',
        ];
    }

    /**
     * The member's own columns, without the shipping address.
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->safe()->only(['name', 'email', 'phone', 'address', 'gender', 'birthday']);
    }

    /**
     * The shipping address as submitted, or null when the member left it empty.
     *
     * @return array{recipient_name: string, phone: string, city_id: int, address: string}|null
     */
    public function shippingAddress(): ?array
    {
        if (blank($this->validated('shipping_address'))) {
            return null;
        }

        return [
            'recipient_name' => (string) $this->validated('shipping_name'),
            'phone' => (string) $this->validated('shipping_phone'),
            'city_id' => (int) $this->validated('shipping_city_id'),
            'address' => (string) $this->validated('shipping_address'),
        ];
    }
}
