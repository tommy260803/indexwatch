<?php

namespace App\Http\Requests;

class UpdateServerRequest extends StoreServerRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['password'] = ['nullable', 'string'];

        return $rules;
    }
}