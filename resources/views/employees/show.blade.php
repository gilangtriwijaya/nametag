{{-- resources/views/employees/show.blade.php --}}
<x-layouts.admin :title="'Detail Pegawai'">
    @php
        /** @var \App\Models\Employee $employee */

        $statusClasses = [
            'AKTIF'    => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'NONAKTIF' => 'bg-slate-200 text-slate-800 ring-slate-300',
        ];

        $st      = $employee->status_aktif ?? '';
        $stClass = $statusClasses[$st] ?? 'bg-slate-100 text-slate-700 ring-slate-200';

        // QR terakhir (di-passing dari controller)
        $latestQr = $latestQr ?? null;

        $qrToken  = $latestQr->token  ?? null;
        $qrStatus = $latestQr->status ?? null;
        $qrTime   = $latestQr?->created_at
            ? app('\\App\\Support\\ViewHelpers')->datetime($latestQr->created_at, 'd M Y H:i:s')
            : null;

        $qrLabel = match ($qrStatus) {
            'active'  => 'AKTIF',
            'revoked' => 'DICABUT',
            default   => 'BELUM ADA',
        };

        $qrClass = match ($qrStatus) {
            'active'  => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'revoked' => 'bg-rose-100 text-rose-800 ring-rose-200',
            default   => 'bg-slate-100 text-slate-700 ring-slate-200',
        };

        // Foto pegawai
        $photoUrl = null;
        if (!empty($employee->foto_path)) {
            $photoUrl = asset(ltrim($employee->foto_path, '/'));
        } elseif (!empty($employee->foto)) {
            $photoUrl = asset(ltrim($employee->foto, '/'));
        } elseif (!empty($employee->avatar_url)) {
            $photoUrl = $employee->avatar_url;
        }

        // Dokumen SK – uploads/employee_sk/xxxx.pdf
        $skUrl = null;
        if (!empty($employee->sk_file_path)) {
            $skUrl = asset(ltrim($employee->sk_file_path, '/'));
        }

        // Flag dari controller (EmployeeController@show)
        $canToggleStatus = $canToggleStatus ?? false;

        // Nametag front/back (png) kalau sudah pernah di-generate
        // Hanya tampilkan jika employee status AKTIF
        $frontUrl = null;
        $backUrl  = null;

        $isActive = ($employee->status_aktif ?? '') === 'AKTIF';
        
        if ($isActive) {
            $frontPath = public_path("nametag/front/{$employee->id}.png");
            $backPath  = public_path("nametag/back/{$employee->id}.png");

            if (is_file($frontPath)) {
                $frontUrl = asset("nametag/front/{$employee->id}.png") . '?v=' . filemtime($frontPath);
            }
            if (is_file($backPath)) {
                $backUrl = asset("nametag/back/{$employee->id}.png") . '?v=' . filemtime($backPath);
            }
        }

        // helper kecil untuk gelar supaya tidak tampil "— ,"
        $gelarDepan    = trim((string) $employee->gelar_depan);
        $gelarBelakang = trim((string) $employee->gelar_belakang);
    @endphp

    <x-slot:header>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ Str::upper($employee->nama_lengkap ?? $employee->nama) }}
                </h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm">
                    Detail pegawai, status keaktifan, QR, dan dokumen pendukung.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('employees.index') }}"
                   class="h-9 px-3 inline-flex items-center rounded-lg border border-slate-300 text-xs text-slate-700 hover:bg-slate-50">
                    Kembali ke Daftar
                </a>
                @can('update', $employee)
                    <a href="{{ route('employees.edit', $employee) }}"
                       class="h-9 px-3 inline-flex items-center rounded-lg bg-brand-600 text-xs text-white hover:bg-brand-700">
                        Ubah Data
                    </a>
                @endcan
            </div>
        </div>
    </x-slot:header>

    {{-- Alert sukses / error --}}
    @if (session('ok'))
        <div class="mt-4 mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('ok') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mt-4 mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom kiri: data, QR, nametag --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Data Pegawai --}}
            <div class="rounded-2xl bg-white dark:bg-navy-800 ring-1 ring-slate-200 dark:ring-slate-700 p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-100">
                        Data Pegawai
                    </h2>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold ring-1 {{ $stClass }}">
                        STATUS: {{ $st ?: '—' }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-slate-500 text-xs">Nama Lengkap</dt>
                        <dd class="font-medium text-slate-900 dark:text-slate-50">
                            <strong>{{ $displayData['nama_display'] ?? Str::upper($employee->nama_lengkap ?? $employee->nama ?? '—') }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">NIP</dt>
                        <dd class="font-mono text-xs">
                            <strong>{{ ($employee->nip_label ?? 'NIP.') . ' ' . ($employee->nip ?? '—') }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Gelar Depan / Belakang</dt>
                        <dd>
                            @if(($displayData['gelar_depan_display'] ?? '') === '' && ($displayData['gelar_belakang_display'] ?? '') === '')
                                —
                            @else
                                <strong>{{ $displayData['gelar_depan_display'] ?? '' }}</strong>
                                @if(($displayData['gelar_depan_display'] ?? '') && ($displayData['gelar_belakang_display'] ?? ''))
                                    <strong>,</strong> 
                                @endif
                                <strong>{{ $displayData['gelar_belakang_display'] ?? '' }}</strong>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Jabatan</dt>
                        <dd>
                            <strong>{{ $displayData['jabatan_display'] ?? $employee->jabatan ?? '—' }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Tipe Jabatan</dt>
                        <dd>
                            {{-- FIX: gunakan kolom jabatan_type agar sama dengan index & DB --}}
                            <strong>{{ $employee->jabatan_type ?? '—' }}</strong>
                        </dd>
                    </div>

                    <div class="space-y-1">
                        <dt class="text-slate-500 text-xs">Pangkat / Golongan</dt>
                        <dd>
                            <strong>{{ $employee->pangkat ?? '—' }}{{ $employee->pangkat && $employee->golongan ? ' / ' : '' }}{{ $employee->golongan ?? '' }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Status Kepegawaian</dt>
                        <dd>
                            <strong>{{ $employee->status_kepegawaian ?? '—' }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Golongan Darah</dt>
                        <dd>
                            {{-- FIX: gunakan kolom gol_darah agar sama dengan index & DB --}}
                            <strong>{{ $displayData['gol_darah_display'] ?? $employee->gol_darah ?? '—' }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">OPD</dt>
                        <dd>
                            <strong>{{ $employee->opd->nama ?? '—' }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Unit OPD</dt>
                        <dd>
                            <strong>{{ $employee->opdUnit->nama ?? '—' }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Unit Kerja</dt>
                        <dd>
                            <strong>{{ $displayData['unit_kerja_display'] ?? $employee->unitKerja->nama ?? $employee->nama_unit_opd ?? '—' }}</strong>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Dibuat</dt>
                        <dd class="text-xs text-slate-600">
                            @datetime($employee->created_at) WIB
                        </dd>
                    </div>

                    <div>
                        <dt class="text-slate-500 text-xs">Terakhir Diperbarui</dt>
                        <dd class="text-xs text-slate-600">
                            @datetime($employee->updated_at) WIB
                        </dd>
                    </div>
                </dl>

                {{-- Tombol toggle status --}}
                @if ($canToggleStatus)
                    <div class="mt-4 border-t border-slate-100 pt-3 flex flex-wrap gap-2">
                        @if ($st === 'AKTIF')
                            <form method="POST"
                                  action="{{ route('employees.deactivate', $employee) }}"
                                  onsubmit="return confirm('Nonaktifkan data pegawai ini?');">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center h-8 px-3 rounded-lg bg-rose-600 text-[11px] text-white hover:bg-rose-700">
                                    Nonaktifkan Data
                                </button>
                            </form>
                        @else
                            <form method="POST"
                                  action="{{ route('employees.activate', $employee) }}"
                                  onsubmit="return confirm('Aktifkan kembali data pegawai ini?');">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center h-8 px-3 rounded-lg bg-emerald-600 text-[11px] text-white hover:bg-emerald-700">
                                    Aktifkan Data
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            {{-- QR & Verifikasi --}}
            <div class="rounded-2xl bg-white dark:bg-navy-800 ring-1 ring-slate-200 dark:ring-slate-700 p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-100">
                        QR & Verifikasi
                    </h2>
                    @can('update', $employee)
                        @if(empty($qrToken))
                            <form method="POST" action="{{ route('qr.store', $employee) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center h-9 px-3 rounded-lg bg-brand-600 text-xs text-white font-semibold hover:bg-brand-700">
                                    Buat QR
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>

                <div class="flex flex-wrap gap-4">
                    <div class="min-w-[140px]">
                        <div class="mb-2 text-xs text-slate-500">Status QR</div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $qrClass }}">
                            {{ $qrLabel }}
                        </span>
                        @if($qrTime)
                            <div class="mt-2 text-[11px] text-slate-500">
                                Dibuat: {{ $qrTime }} WIB
                            </div>
                        @endif
                        @if($latestQr?->revoked_at)
                            <div class="text-[11px] text-slate-500 mt-1">
                                Dicabut: {{ \Carbon\Carbon::parse($latestQr->revoked_at)->format('d M Y H:i:s') }} WIB
                            </div>
                        @endif
                    </div>

                    <div class="flex-1 min-w-[220px]">
                        <div class="mb-2 text-xs text-slate-500">Aksi Cepat</div>
                        @if($qrToken)
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ url('/t/'.$qrToken) }}"
                                   target="_blank" rel="noreferrer"
                                   class="inline-flex items-center h-9 px-3 rounded-lg bg-slate-100 text-xs text-slate-800 hover:bg-slate-200">
                                    Halaman Scan Publik
                                </a>
                                <a href="{{ route('scan-logs.index', ['q' => $qrToken]) }}"
                                   class="inline-flex items-center h-9 px-3 rounded-lg bg-slate-100 text-xs text-slate-800 hover:bg-slate-200">
                                    Lihat Log Scan
                                </a>
                            </div>
                            <div class="mt-2 text-[11px] text-slate-500 font-mono break-all">
                                Token: {{ $qrToken }}
                            </div>
                        @else
                            <p class="text-xs text-slate-500">
                                Pegawai ini belum memiliki QR. Klik <span class="font-semibold">Perbarui QR</span> untuk membuat.
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Tanda Pengenal (Nametag) --}}
            <div class="rounded-2xl bg-white dark:bg-navy-800 ring-1 ring-slate-200 dark:ring-slate-700 p-5">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-100">
                        Tanda Pengenal (Nametag)
                    </h2>

                    @can('update', $employee)
                        <form method="POST"
                              action="{{ route('employees.nametag.store', $employee) }}"
                              onsubmit="return confirm('Generate / perbarui nametag untuk pegawai ini?');">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center h-9 px-3 rounded-lg bg-brand-600 text-xs text-white hover:bg-brand-700">
                                Proses / Perbarui
                            </button>
                        </form>
                    @endcan
                </div>

                <div class="flex flex-wrap gap-4 items-start">
                    <div class="flex flex-col gap-2 min-w-[170px]">
                        <div class="text-xs text-slate-500 mb-1">Preview</div>
                        <div class="flex flex-wrap gap-2">
                            @if($frontUrl)
                                <button type="button"
                                        onclick="openImgPreview('{{ $frontUrl }}')"
                                        class="h-8 px-3 rounded-lg border border-slate-300 text-[11px] hover:bg-slate-50">
                                    Preview Depan
                                </button>
                            @endif
                            @if($backUrl)
                                <button type="button"
                                        onclick="openImgPreview('{{ $backUrl }}')"
                                        class="h-8 px-3 rounded-lg border border-slate-300 text-[11px] hover:bg-slate-50">
                                    Preview Belakang
                                </button>
                            @endif
                        </div>
                        @unless($frontUrl || $backUrl)
                            @if(!$isActive)
                                <p class="text-xs text-slate-500">
                                    Employee status nonaktif — nametag tidak dapat ditampilkan.
                                </p>
                            @else
                                <p class="text-xs text-slate-500">
                                    Nametag belum dibuat atau file belum tersedia. Gunakan tombol
                                    <span class="font-semibold">Generate / Perbarui</span>.
                                </p>
                            @endif
                        @endunless
                    </div>

                    @if($frontUrl || $backUrl)
                        <div class="flex-1 min-w-[220px]">
                            <div class="text-xs text-slate-500 mb-1">File</div>
                            <ul class="space-y-1 text-xs text-slate-600">
                                @if($frontUrl)
                                    <li>
                                        Depan:
                                        <a href="{{ $frontUrl }}" target="_blank" class="text-brand-600 hover:underline">
                                            {{ "nametag/front/{$employee->id}.png" }}
                                        </a>
                                    </li>
                                @endif
                                @if($backUrl)
                                    <li>
                                        Belakang:
                                        <a href="{{ $backUrl }}" target="_blank" class="text-brand-600 hover:underline">
                                            {{ "nametag/back/{$employee->id}.png" }}
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Kolom kanan: foto & SK --}}
        <div class="space-y-4">
            {{-- Foto --}}
            <div class="rounded-2xl bg-white dark:bg-navy-800 ring-1 ring-slate-200 dark:ring-slate-700 p-5 flex flex-col items-center">
                <div class="w-full flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-100">
                        Foto Pegawai
                    </h2>
                </div>

                @if($photoUrl)
                    <button type="button"
                            onclick="window.open('{{ $photoUrl }}','_blank')"
                            class="relative group w-40 h-40 focus:outline-none">
                        <img src="{{ $photoUrl }}"
                             alt="Foto {{ $employee->nama_lengkap ?? $employee->nama }}"
                             class="w-40 h-40 rounded-2xl object-cover border border-slate-200 shadow-sm">
                        <div class="absolute inset-0 rounded-2xl bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-[11px] text-white font-medium transition">
                            Klik untuk perbesar (tab baru)
                        </div>
                    </button>
                @else
                    <div class="w-40 h-48 rounded-2xl bg-slate-50 border border-dashed border-slate-300 flex flex-col items-center justify-center text-xs text-slate-400 text-center">
                        Belum ada foto pegawai.
                        <span class="mt-1 text-[11px]">
                            Unggah foto bisa dilakukan saat mengubah data.
                        </span>
                    </div>
                @endif
            </div>

            {{-- SK --}}
            <div class="rounded-2xl bg-white dark:bg-navy-800 ring-1 ring-slate-200 dark:ring-slate-700 p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-100">
                        Dokumen SK Kepegawaian
                    </h2>
                </div>

                @if($skUrl)
                    <div class="mt-3 h-64 border border-slate-200 rounded-xl overflow-hidden bg-slate-50 relative group">
                        <iframe src="{{ $skUrl }}#toolbar=0"
                                class="w-full h-full"
                                title="Preview SK Pegawai"></iframe>
                        <button type="button"
                                onclick="window.open('{{ $skUrl }}','_blank')"
                                class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 text-[11px] text-white font-medium transition focus:outline-none">
                            Klik untuk buka SK di tab baru
                        </button>
                    </div>
                @else
                    <p class="text-xs text-slate-500 mb-2">
                        Belum ada dokumen SK yang diunggah. Unggah / ganti SK dapat dilakukan saat mengubah data pegawai.
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal preview gambar --}}
    <div id="imgPreviewModal"
         class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/70 p-4"
         onclick="if (event.target.id === 'imgPreviewModal') closeImgPreview();">
        <img id="imgPreviewTarget" src="" alt="Preview"
             class="max-h-[90vh] max-w-[90vw] rounded shadow-2xl bg-white">
    </div>

    <script>
        function openImgPreview(src) {
            if (!src) return;
            const m = document.getElementById('imgPreviewModal');
            const i = document.getElementById('imgPreviewTarget');
            i.src = src;
            m.classList.remove('hidden');
            m.classList.add('flex');
            document.addEventListener('keydown', escCloseImg);
        }
        function closeImgPreview() {
            const m = document.getElementById('imgPreviewModal');
            const i = document.getElementById('imgPreviewTarget');
            i.src = '';
            m.classList.add('hidden');
            m.classList.remove('flex');
            document.removeEventListener('keydown', escCloseImg);
        }
        function escCloseImg(e) {
            if (e.key === 'Escape') closeImgPreview();
        }
    </script>
</x-layouts.admin>
