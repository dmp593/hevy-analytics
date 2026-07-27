<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'hevy_api_key' => ['nullable', 'string', 'max:255'],
            'sex' => ['nullable', 'in:male,female'],
            'age' => ['nullable', 'integer', 'between:10,100'],
            'height_cm' => ['nullable', 'numeric', 'between:100,250'],
            'activity_level' => ['nullable', 'numeric', 'between:1.2,1.9'],
            'body_fat_source' => ['nullable', 'in:scale,navy,manual'],
            // Validated against the real zone list: this value ends up in
            // date arithmetic, so an arbitrary string must not reach it.
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            // Only languages the app actually ships translations for.
            'locale' => ['nullable', 'string', Rule::in(Locales::codes())],
        ];
    }
}
