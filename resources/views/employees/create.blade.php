{{-- resources/views/employees/create.blade.php --}}
<x-layouts.admin :title="'Tambah Pegawai – Anambas-ID'">
    <x-slot:header>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Tambah Pegawai
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm">
                    Tambah data pegawai baru ke Anambas-ID.
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('employees.index') }}"
                   class="h-9 px-3 rounded-lg border border-slate-300 text-xs text-slate-700 hover:bg-slate-50">
                    Kembali ke Daftar
                </a>
            </div>
        </div>
    </x-slot:header>

    <div class="w-full">

        {{-- Partial _form SUDAH mencakup <form> dan </form> --}}
        @include('employees._form', [
            'employee'      => null,
            'opd_locked'    => session('opd_locked', false),
            'submitLabel'   => 'Simpan'
        ])
    </div>
</x-layouts.admin>
