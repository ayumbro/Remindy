<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
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
        $validDateFormats = ['Y-m-d', 'm/d/Y', 'd/m/Y'];
        $validLocales = ['en', 'zh-CN'];

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

            'date_format' => ['required', 'string', 'in:'.implode(',', $validDateFormats)],
            'locale' => ['required', 'string', 'in:'.implode(',', $validLocales)],
        ];
    }
}
