<?php

namespace App\Http\Requests;

use App\Enums\ContactRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'regex:/^\+[1-9]\d{1,14}$/', 'unique:contacts,phone_number'],
            'role' => ['required', Rule::in(array_map(fn (ContactRole $role) => $role->value, ContactRole::cases()))],
            'active' => ['boolean'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('contacts', 'user_id')->whereNotNull('user_id')],
            'allowed_since' => ['nullable', 'date'],
            'servers' => ['nullable', 'array'],
            'servers.*' => ['integer', 'exists:servers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.regex' => 'El número de teléfono debe tener formato E.164 válido (ej. +51999999999).',
            'phone_number.unique' => 'Este número de teléfono ya está registrado.',
            'user_id.unique' => 'Este usuario ya está vinculado a otro contacto.',
        ];
    }
}
