<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();

        $isSuper    = $user?->hasRole('superadmin') ?? false;
        $isOrgAdmin = $user?->hasAnyRole(['org_admin', 'admin_organisasi']) ?? false;
        $isAdminOpd = $user?->hasAnyRole(['admin_opd', 'admin opd', 'Admin OPD']) ?? false;

        $managedUnitIds = method_exists($user, 'managedUnitIds')
            ? (array) $user->managedUnitIds()
            : [];

        $isUnitLevel = !empty($managedUnitIds)
            && !($isSuper || $isOrgAdmin || $isAdminOpd);

        return [
            'opd_id' => [
                Rule::requiredIf($isSuper || $isOrgAdmin),
                'nullable',
                'integer',
                'exists:opds,id',
            ],

            'opd_unit_id' => [
                Rule::requiredIf($isUnitLevel),
                'nullable',
                'integer',
                'exists:opd_units,id',
            ],

            'unit_kerja_id' => [
                'nullable',
                'integer',
                'exists:unit_kerja,id',
            ],

            'nip'  => ['required', 'string', 'max:25'],
            'nama' => ['required', 'string', 'max:150'],

            'gelar_depan'    => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'jenis_kelamin'  => ['nullable', Rule::in(['L', 'P'])],

            'email' => ['nullable', 'email', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:25'],

            'jabatan_type' => [
                'required',
                Rule::in([
                    'PELAKSANA',
                    'FUNGSIONAL',
                    'PENGAWAS',
                    'ADMINISTRATOR',
                    'PIMPINAN TINGGI PRATAMA',
                ]),
            ],

            'jabatan'     => ['nullable', 'string', 'max:150'],
            'tmt_jabatan' => ['nullable', 'date'],
            'nama_unit_opd'  => ['nullable', 'string', 'max:150'],

            'pangkat'   => ['nullable', 'string', 'max:50'],
            'golongan'  => ['nullable', 'string', 'max:10'],
            'gol_darah' => ['nullable', 'string', 'max:10'],

            'status_kepegawaian' => [
                'required',
                Rule::in(['PNS', 'PPPK', 'HONOR', 'KONTRAK', 'LAINNYA']),
            ],

            'nama_pendidikan' => ['nullable', 'string', 'max:150'],
            'tingkat_pendidikan' => [
                'nullable',
                Rule::in([
                    'SD', 'SLTP', 'SLTP Kejuruan', 'SLTA', 'SLTA Kejuruan',
                    'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3',
                ]),
            ],

            'tgl_lahir' => ['nullable', 'date'],
            'alamat'    => ['nullable', 'string'],

            'foto' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            'sk_file' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:5120',
            ],

        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value      = trim(preg_replace('/\s+/', ' ', $value));
                $data[$key] = $value === '' ? null : $value;
            }
        }

        $this->merge($data);
    }
}
