<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // sudah ada policy akses di controller; izinkan di sini
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required','string','max:191'],
            'email'    => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:6','confirmed'],

            // organisasi
            'opd_id'      => ['nullable','integer','exists:opds,id'],
            'opd_unit_id' => [
                'nullable','integer',
                // valid hanya jika unit milik OPD yang dipilih
                Rule::exists('opd_units','id')
                    ->where(function ($q) {
                        // kalau tidak ada opd_id, unit tidak boleh dipilih
                        $opdId = (int) $this->input('opd_id');
                        if ($opdId <= 0) {
                            // paksa rule gagal kalau user mencoba kirim unit tanpa OPD
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
