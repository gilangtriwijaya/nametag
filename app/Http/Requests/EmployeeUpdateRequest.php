<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\Employee|null $employee */
        $employee = $this->route('employee');

        if (! $employee) {
            return false;
        }

        return $this->user()?->can('update', $employee) ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();

        // ---- Deteksi role utama ----
        $isSuper = $user?->hasRole('superadmin') ?? false;

        // org_admin + variasi admin_organisasi
        $isOrg = $user?->hasRole('org_admin')
            || $user?->hasRole('admin_organisasi');

        $isAdminOpd = $user?->hasRole('admin_opd')
            || $user?->hasRole('admin opd')
            || $user?->hasRole('Admin OPD');

        $isVerOpd = $user?->hasRole('verifikator_opd')
            || $user?->hasRole('verifikator opd')
            || $user?->hasRole('Verifikator OPD');

        // daftar unit yang dikelola user (kalau ada)
        $managedUnitIds = method_exists($user, 'managedUnitIds')
            ? array_map('intval', (array) $user->managedUnitIds())
            : [];

        /**
         * Akun level-unit:
         * - punya managedUnitIds
         * - BUKAN super / org_admin / admin_opd / verifikator_opd
         *
         * Ini penting supaya admin OPD TIDAK dipaksa punya unit.
         */
        $isUnitLevel = ! empty($managedUnitIds)
            && ! ($isSuper || $isOrg || $isAdminOpd || $isVerOpd);

        // Super / Org admin wajib memilih OPD, lainnya di-set lewat service / context
        $isSuperOrOrg = $isSuper || $isOrg;

        return [
            'opd_id' => [
                Rule::requiredIf($isSuperOrOrg),
                'nullable',
                'integer',
                'exists:opds,id',
            ],

            // akun level unit → wajib; lainnya boleh null (pegawai di level OPD)
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

            'nip'            => ['required', 'string', 'max:25'],
            'nama'           => ['required', 'string', 'max:150'],
            'gelar_depan'    => ['nullable', 'string', 'max:50'],
            'gelar_belakang' => ['nullable', 'string', 'max:50'],
            'jenis_kelamin'  => ['nullable', Rule::in(['L', 'P'])],
            'no_hp'          => ['nullable', 'string', 'max:25'],
            'email'          => ['nullable', 'email', 'max:150'],

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
            'pangkat'     => ['nullable', 'string', 'max:50'],
            'golongan'    => ['nullable', 'string', 'max:10'],
            'gol_darah'   => ['nullable', 'string', 'max:10'],

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

            // Upload foto baru (opsional)
            'foto' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'foto_is_manual' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'opd_id.required'              => 'OPD wajib dipilih untuk akun global.',
            'opd_id.integer'               => 'OPD tidak valid.',
            'opd_id.exists'                => 'OPD tidak ditemukan.',

            'opd_unit_id.required'         => 'Unit OPD wajib dipilih untuk akun level unit.',
            'opd_unit_id.integer'          => 'Unit OPD tidak valid.',
            'opd_unit_id.exists'           => 'Unit OPD tidak ditemukan.',

            'nip.required'                 => 'NIP wajib diisi.',
            'nama.required'                => 'Nama wajib diisi.',

            'jabatan_type.required'        => 'Jenis jabatan wajib diisi.',
            'jabatan_type.in'              => 'Jenis jabatan tidak valid.',

            'status_kepegawaian.required'  => 'Status kepegawaian wajib diisi.',
            'status_kepegawaian.in'        => 'Status kepegawaian tidak valid.',

            'tingkat_pendidikan.in'        => 'Tingkat pendidikan tidak valid.',
            'jenis_kelamin.in'             => 'Jenis kelamin tidak valid.',
            'email.email'                  => 'Format email tidak valid.',

            'foto.image'                   => 'File foto harus berupa gambar.',
            'foto.mimes'                   => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'foto.max'                     => 'Ukuran foto maksimal 2 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();

        // Normalisasi string: trim, kosong -> null
        foreach ($data as $k => $v) {
            if (is_string($v)) {
                $v = trim($v);
                $data[$k] = ($v === '') ? null : $v;
            }
        }

        // Rapikan spasi berlebih di nama & gelar
        foreach (['gelar_depan', 'gelar_belakang', 'nama'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                $data[$key] = preg_replace('/\s+/', ' ', $data[$key]);
            }
        }

        $this->merge($data);
    }
}
