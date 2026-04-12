<x-layouts.admin :title="'Detail Log Scan – Anambas-ID'">

@php
    // Formatter NIP – helper kecil
    if (!function_exists('formatNipScanLog')) {
        function formatNipScanLog(?string $nip): string {
            $nip = preg_replace('/\D+/', '', (string) $nip);
            $len = strlen($nip);
            if ($len === 18) {
                return trim(sprintf(
                    '%s %s %s %s',
                    substr($nip, 0, 8),
                    substr($nip, 8, 6),
                    substr($nip, 14, 1),
                    substr($nip, 15, 3)
                ));
            }
            return $nip === '' ? '—' : trim(implode(' ', str_split($nip, 3)));
        }
    }

    $resultLabels = [
        'ok'                 => 'VALID',
        'revoked'            => 'DICABUT',
        'expired'            => 'KADALUARSA',
        'inactive_employee'  => 'PEGAWAI NONAKTIF',
        'not_found'          => 'TIDAK DITEMUKAN',
    ];

    $resultClasses = [
        'ok'                 => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'revoked'            => 'bg-rose-100 text-rose-800 ring-rose-200',
        'expired'            => 'bg-amber-100 text-amber-800 ring-amber-200',
        'inactive_employee'  => 'bg-slate-200 text-slate-800 ring-slate-300',
        'not_found'          => 'bg-gray-100 text-gray-700 ring-gray-200',
    ];

    $label = $resultLabels[$log->result] ?? strtoupper($log->result ?? '-');
    $cls   = $resultClasses[$log->result] ?? 'bg-gray-100 text-gray-700 ring-gray-200';

    $scannedAt = $log->scanned_at
        ? \Carbon\Carbon::parse($log->scanned_at)->format('d M Y H:i:s')
        : null;

    $scanUrl = $log->token ? url('/t/'.$log->token) : null;
@endphp

<div class="container mx-auto p-4 space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Detail Log Scan QR</h1>
            <p class="text-xs text-slate-500">
                ID Log: {{ $log->id }}
            </p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('scan-logs.index') }}"
               class="px-3 py-2 rounded bg-slate-200 text-xs hover:bg-slate-300">
                Kembali ke daftar
            </a>
            @if($log->employee_id)
                <a href="{{ route('employees.show', $log->employee_id) }}"
                   class="px-3 py-2 rounded bg-blue-600 text-xs text-white hover:bg-blue-700">
                    Lihat Pegawai
                </a>
            @endif
            @if($scanUrl)
                <a href="{{ $scanUrl }}" target="_blank" rel="noreferrer"
                   class="px-3 py-2 rounded bg-slate-900 text-xs text-white hover:bg-black">
                    Buka Halaman Scan
                </a>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Panel utama --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Ringkasan hasil --}}
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 sm:p-5">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1 {{ $cls }}">
                        {{ $label }}
                    </span>
                    @if($scannedAt)
                        <span class="text-xs text-slate-500">
                            Waktu scan: {{ $scannedAt }} WIB
                        </span>
                    @endif
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-slate-500 text-xs">IP Address</dt>
                        <dd class="font-mono text-sm">{{ $log->ip_address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 text-xs">Token</dt>
                        <dd class="font-mono text-xs break-all">
                            {{ $log->token ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 text-xs">Status Token</dt>
                        <dd class="text-sm">
                            {{ $log->token_status ?? '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 text-xs">Token dibuat</dt>
                        <dd class="text-sm">
                            @if(!empty($log->token_created_at))
                                {{ \Carbon\Carbon::parse($log->token_created_at)->format('d M Y H:i:s') }} WIB
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 text-xs">Kadaluarsa Token</dt>
                        <dd class="text-sm">
                            @if(!empty($log->token_expires_at))
                                {{ \Carbon\Carbon::parse($log->token_expires_at)->format('d M Y H:i:s') }} WIB
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            {{-- User Agent --}}
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 sm:p-5">
                <h2 class="text-sm font-semibold mb-2 text-slate-700">Informasi Perangkat</h2>
                <dl class="text-sm space-y-1">
                    <div>
                        <dt class="text-slate-500 text-xs">User Agent</dt>
                        <dd class="font-mono text-[11px] break-words leading-relaxed">
                            {{ $log->user_agent ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Panel pegawai --}}
        <div class="space-y-4">
            <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 sm:p-5">
                <h2 class="text-sm font-semibold mb-3 text-slate-700">Data Pegawai Terkait</h2>

                @if($log->employee_id)
                    <dl class="text-sm space-y-2">
                        <div>
                            <dt class="text-slate-500 text-xs">Nama</dt>
                            <dd class="font-medium text-slate-800">
                                {{ $log->nama ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs">NIP</dt>
                            <dd class="font-mono text-xs">
                                {{ formatNipScanLog($log->nip ?? null) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs">Status Pegawai</dt>
                            <dd class="text-xs">
                                @if(($log->status_aktif ?? null) === 'AKTIF')
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] ring-1 ring-emerald-200">
                                        AKTIF
                                    </span>
                                @elseif($log->status_aktif)
                                    <span class="inline-flex px-2 py-0.5 rounded-full bg-slate-200 text-slate-800 text-[11px] ring-1 ring-slate-300">
                                        {{ $log->status_aktif }}
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400">—</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs">OPD</dt>
                            <dd class="text-sm">
                                {{ $log->opd_name ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('employees.show', $log->employee_id) }}"
                           class="px-3 py-1.5 rounded bg-blue-600 text-xs text-white hover:bg-blue-700">
                            Buka Profil Pegawai
                        </a>
                        @if($scanUrl)
                            <button type="button"
                                    class="px-3 py-1.5 rounded bg-slate-200 text-xs hover:bg-slate-300"
                                    data-copy="{{ $scanUrl }}"
                                    onclick="navigator.clipboard.writeText(this.dataset.copy).then(()=>{this.innerText='URL Disalin'; setTimeout(()=>this.innerText='Salin URL',1500)})">
                                Salin URL Scan
                            </button>
                        @endif
                    </div>
                @else
                    <p class="text-xs text-slate-500">
                        Log ini tidak terhubung ke data pegawai tertentu
                        (kemungkinan token tidak valid atau sudah tidak ada).
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

</x-layouts.admin>
