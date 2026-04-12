{{-- resources/views/employees/edit.blade.php --}}
<x-layouts.admin :title="'Edit Pegawai – Anambas-ID'">
    <x-slot:header>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Ubah Pegawai
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm">
                    Perbarui data pegawai Anambas-ID.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('employees.show', $employee) }}"
                   class="h-9 px-3 rounded-lg border border-slate-300 text-xs text-slate-700 hover:bg-slate-50">
                    Detail
                </a>
                <a href="{{ route('employees.index') }}"
                   class="h-9 px-3 rounded-lg border border-slate-300 text-xs text-slate-700 hover:bg-slate-50">
                    Kembali ke Daftar
                </a>
            </div>
        </div>
    </x-slot:header>

    <div class="w-full">
        {{-- Partial _form SUDAH mengandung <form> dan @csrf/@method --}}
        @include('employees._form', [
            'employee'    => $employee,
            'opd_locked'  => $opd_locked ?? false,
            'submitLabel' => 'Simpan Perubahan',
        ])

        {{-- Tombol status hanya untuk role yang memang boleh manageStatus --}}
        @can('manageStatus', $employee)
            <div class="mt-6 flex gap-3">
                @if($employee->status_aktif === 'AKTIF')
                    <form method="POST"
                          action="{{ route('employees.deactivate', $employee) }}"
                          onsubmit="return confirm('Nonaktifkan pegawai ini?');">
                        @csrf
                        <button type="submit"
                                class="px-3 py-2 rounded-lg bg-rose-600 text-white text-sm hover:bg-rose-700">
                            Nonaktifkan
                        </button>
                    </form>
                @else
                    <form method="POST"
                          action="{{ route('employees.activate', $employee) }}"
                          onsubmit="return confirm('Aktifkan pegawai ini? Pastikan tidak ada entri AKTIF lain untuk NIP ini.');">
                        @csrf
                        <button type="submit"
                                class="px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">
                            Aktifkan
                        </button>
                    </form>
                @endif
            </div>
        @endcan
    </div>
</x-layouts.admin>
