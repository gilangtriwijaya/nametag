<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id ?? null;

        return [
            'name'  => ['required','string','max:191'],
            'email' => ['required','email','max:255', Rule::unique('users','email')->ignore($userId)],

            'password' => ['nullable','string','min:6','confirmed'],

            'opd_id'      => ['nullable','integer','exists:opds,id'],
            'opd_unit_id' => [
                'nullable','integer',
                Rule::exists('opd_units','id')
                    ->where(function ($q) {
                        $opdId = (int) $this->input('opd_id');
                        if ($opdId <= 0) {
                            $q->whereRaw('0 = 1');
                        } else {
                            $q->where('opd_id', $opdId);
                        }
                    }),
            ],

            'roles'     => ['array'],
            'roles.*'   => ['integer','exists:roles,id'],
            'is_active' => ['nullable','boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opd_id'      => $this->filled('opd_id') ? (int) $this->input('opd_id') : null,
            'opd_unit_id' => $this->filled('opd_unit_id') ? (int) $this->input('opd_unit_id') : null,
            'is_active'   => $this->boolean('is_active'),
        ]);
    }

    public function messages(): array
    {
        return [
            'opd_unit_id.exists' => 'Unit OPD tidak sesuai dengan OPD yang dipilih.',
        ];
    }
}
