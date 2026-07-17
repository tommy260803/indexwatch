<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateContactRequest extends StoreContactRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['phone_number'] = ['required', 'string', 'regex:/^\+[1-9]\d{1,14}$/', Rule::unique('contacts', 'phone_number')->ignore($this->route('contact'))];
        $rules['user_id'] = ['nullable', 'integer', 'exists:users,id', Rule::unique('contacts', 'user_id')->whereNotNull('user_id')->ignore($this->route('contact'))];

        return $rules;
    }
}
