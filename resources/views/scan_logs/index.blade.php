<x-layouts.admin :title="'Log Scan QR – Anambas-ID'">
@php
    // Formatter NIP 18 digit (8 6 1 3); selain itu pecah per 3 digit.
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

    // Label & kelas badge hasil scan
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

    // Pastikan variabel filter selalu ada (fallback ke request)
    $filterQ       = $q       ?? request('q', '');
    $filterResult  = $result  ?? request('result', '');
    $filterFrom    = $from    ?? request('from', '');
    $filterTo      = $to      ?? request('to', '');
@endphp

<div class="container mx-auto p-4">
    <h1 class="text-xl font-semibold mb-4">Log Scan QR Pegawai</h1>

    {{-- Filter --}}
    <form method="GET" action="{{ route('scan-logs.index') }}"
          class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        {{-- q --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Cari</label>
            <input type="text" name="q" value="{{ $filterQ }}"
                   placeholder="Nama/NIP/token/IP…"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>

        {{-- result --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Hasil</label>
            <select name="result"
                    class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                <option value="">Semua</option>
                @foreach($resultLabels as $key => $lab)
                    <option value="{{ $key }}" @selected($filterResult === $key)>{{ $lab }}</option>
                @endforeach
            </select>
        </div>

        {{-- from --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Dari tanggal</label>
            <input type="date" name="from" value="{{ $filterFrom }}"
                   class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
        </div>

        {{-- to + buttons --}}
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Sampai tanggal</label>
            <div class="flex gap-2">
                <input type="date" name="to" value="{{ $filterTo }}"
                       class="flex-1 rounded border border-slate-300 px-3 py-2 text-sm">
                <button class="px-3 py-2 rounded bg-slate-900 text-white text-xs">
                    Terapkan
                </button>
                @if($filterQ || $filterResult || $filterFrom || $filterTo)
                    <a href="{{ route('scan-logs.index') }}"
                       class="px-3 py-2 rounded bg-slate-200 text-xs">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    {{-- Tabel --}}
    <div class="overflow-auto border border-slate-200 rounded-lg bg-white">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-600">
                <tr>
                    <th class="text-left px-3 py-2 w-40">Waktu Scan</th>
                    <th class="text-left px-3 py-2 w-32">Hasil</th>
                    <th class="text-left px-3 py-2 w-64">Pegawai</th>
                    <th class="text-left px-3 py-2 w-64">NIP &amp; OPD</th>
                    <th class="text-left px-3 py-2 w-56">Token</th>
                    <th class="text-left px-3 py-2 w-40">IP</th>
                    <th class="text-left px-3 py-2">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                @php
                    $label = $resultLabels[$log->result] ?? strtoupper($log->result ?? '-');
                    $cls   = $resultClasses[$log->result] ?? 'bg-gray-100 text-gray-700 ring-gray-200';
                    $time  = $log->scanned_at
                        ? app('\\App\\Support\\ViewHelpers')->datetime($log->scanned_at)
                        : '—';
                @endphp
                <tr class="border-t border-slate-100">
                    {{-- Waktu --}}
                    <td class="px-3 py-2 align-top whitespace-nowrap">
                        <div class="text-xs text-slate-500">{{ $time }} WIB</div>
                        <div class="text-[10px] text-slate-400">ID: {{ $log->id }}</div>
                    </td>

                    {{-- Hasil --}}
                    <td class="px-3 py-2 align-top">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1 {{ $cls }}">
                            {{ $label }}
                        </span>
                    </td>

                    {{-- Pegawai --}}
                    <td class="px-3 py-2 align-top">
                        @if($log->nama)
                            <div class="font-medium text-slate-800 truncate max-w-[16rem]">
                                {{ $log->nama }}
                            </div>
                            <div class="text-[11px] text-slate-500">
                                {{ $log->status_aktif === 'AKTIF' ? 'AKTIF' : 'NONAKTIF' }}
                            </div>
                        @else
                            <span class="text-xs text-slate-400">Tidak terkait pegawai</span>
                        @endif
                    </td>

                    {{-- NIP & OPD --}}
                    <td class="px-3 py-2 align-top">
                        <div class="font-mono text-xs">
                            {{ formatNipScanLog($log->nip ?? null) }}
                        </div>
                        <div class="text-[11px] text-slate-500 truncate max-w-[14rem]">
                            {{ $log->opd_name ?? '—' }}
                        </div>
                    </td>

                    {{-- Token --}}
                    <td class="px-3 py-2 align-top">
                        @if($log->token)
                            <div class="flex flex-col gap-1">
                                <div class="font-mono text-xs truncate max-w-[14rem]">
                                    {{ \Illuminate\Support\Str::limit($log->token, 20, '…') }}
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    <a href="{{ url('/t/'.$log->token) }}"
                                       target="_blank" rel="noreferrer"
                                       class="px-2 py-1 rounded bg-slate-100 text-[11px] text-slate-700 hover:bg-slate-200">
                                        Buka Halaman Scan
                                    </a>
                                    <button type="button"
                                            class="px-2 py-1 rounded bg-slate-100 text-[11px] text-slate-700 hover:bg-slate-200"
                                            data-copy="{{ url('/t/'.$log->token) }}"
                                            onclick="navigator.clipboard.writeText(this.dataset.copy).then(()=>{this.innerText='Disalin'; setTimeout(()=>this.innerText='Salin',1500)})">
                                        Salin URL
                                    </button>
                                </div>
                            </div>
                        @else
                            <span class="text-xs text-slate-400">Token tidak tercatat</span>
                        @endif
                    </td>

                    {{-- IP --}}
                    <td class="px-3 py-2 align-top whitespace-nowrap">
                        <div class="text-xs font-mono">{{ $log->ip_address ?? '—' }}</div>
                    </td>

                    {{-- Aksi --}}
                    <td class="px-3 py-2 align-top">
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('scan-logs.show', $log->id) }}"
                               class="px-2 py-1 rounded bg-blue-600 text-white text-xs hover:bg-blue-700">
                                Detail
                            </a>
                            @if($log->employee_id)
                                <a href="{{ route('employees.show', $log->employee_id) }}"
                                   class="px-2 py-1 rounded bg-slate-200 text-xs hover:bg-slate-300">
                                    Lihat Pegawai
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-3 py-4 text-center text-sm text-slate-500">
                        Belum ada log scan yang tercatat.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
</div>
</x-layouts.admin>
