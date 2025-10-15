<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function rules(): array
    {
        $routeUser = $this->route('user');
        $userId = is_object($routeUser) ? ($routeUser->id ?? null) : $routeUser;

        return [
            'name' => ['sometimes','string','max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:254',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'age' => ['sometimes','integer','min:18'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
