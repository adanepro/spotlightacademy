<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $this->user->id,
            'phone' => 'nullable|string|max:255|unique:users,phone,' . $this->user->id,
            'username' => 'nullable|string|max:255|unique:users,username,' . $this->user->id,
            'roles' => 'nullable|array',
            'roles.*' => 'nullable|exists:roles,uuid',
        ];
    }
}
