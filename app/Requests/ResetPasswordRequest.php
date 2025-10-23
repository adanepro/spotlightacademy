<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',    // must contain at least one uppercase letter
                'regex:/[a-z]/',    // must contain at least one lowercase letter
                'regex:/[0-9]/',    // must contain at least one number
                'regex:/[!@#$%^&*(),.?":{}|<>]/', // must contain at least one special character
            ],
            'confirm_new_password' => 'same:new_password',
        ];
    }
}
