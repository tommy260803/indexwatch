<?php

namespace App\Http\Requests;

use App\Enums\ServerStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail): void {
                $host = (string) $value;

                $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
                $isHostname = filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

                if (! $isIp && ! $isHostname && $host !== 'localhost') {
                    $fail('El host debe ser una IP o un hostname válido.');
                }
            }],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'status' => ['required', Rule::in(array_map(fn (ServerStatus $status) => $status->value, ServerStatus::cases()))],
            'warning_threshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'critical_threshold' => ['required', 'numeric', 'min:0', 'max:100', 'gt:warning_threshold'],
            'stats_stale_threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'minimum_index_pages' => ['nullable', 'integer', 'min:0'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'connection_options' => ['nullable', 'array'],
            'connection_options.encrypt' => ['nullable', 'boolean'],
            'connection_options.trust_server_certificate' => ['nullable', 'boolean'],
            'connection_options.timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'contacts' => ['nullable', 'array'],
            'contacts.*' => ['integer', 'exists:contacts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'critical_threshold.gt' => 'El umbral crítico debe ser mayor que el umbral de warning.',
            'timezone.in' => 'La zona horaria seleccionada no es válida.',
            'port.min' => 'El puerto debe estar entre 1 y 65535.',
            'port.max' => 'El puerto debe estar entre 1 y 65535.',
            'contacts.*.exists' => 'Uno de los contactos seleccionados no existe.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $options = $validator->getData()['connection_options'] ?? [];

                if (! is_array($options)) {
                    return;
                }

                $allowedKeys = ['encrypt', 'trust_server_certificate', 'timeout'];
                $extraKeys = array_diff(array_keys($options), $allowedKeys);

                if ($extraKeys !== []) {
                    $validator->errors()->add(
                        'connection_options',
                        'connection_options solo admite encrypt, trust_server_certificate y timeout.'
                    );
                }
            },
        ];
    }

}
