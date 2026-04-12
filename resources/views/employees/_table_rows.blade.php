@forelse($employees as $idx => $emp)
    @php
        $rowNo = ($employees->firstItem() ?? 1) + $idx;

        $st = $emp->status_aktif ?? '';
        $stClass = [
            'AKTIF'    => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'NONAKTIF' => 'bg-slate-200 text-slate-800 ring-slate-300',
        ][$st] ?? 'bg-slate-100 text-slate-700 ring-slate-200';

        $qrStatus = $emp->latest_qr_status ?? null;
        $qrToken  = $emp->latest_qr_token  ?? null;
        $qrTime   = $emp->latest_qr_created_at
            ? app('\\App\\Support\\ViewHelpers')->datetime($emp->latest_qr_created_at)
            : null;

        $qrLabel = match ($qrStatus) {
            'active'  => 'AKTIF',
            'revoked' => 'DICABUT',
            default   => null,
        };

        $qrClass = match ($qrStatus) {
            'active'  => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'revoked' => 'bg-rose-100 text-rose-800 ring-rose-200',
            default   => 'bg-slate-100 text-slate-700 ring-slate-200',
        };

        // Hanya tampilkan nametag jika employee status AKTIF
        $isActive = $st === 'AKTIF';
        $frontUrl = null;
        $backUrl  = null;

        if ($isActive) {
            $frontPath = public_path("nametag/front/{$emp->id}.png");
            $backPath  = public_path("nametag/back/{$emp->id}.png");
            $frontUrl  = is_file($frontPath)
                ? asset("nametag/front/{$emp->id}.png").'?v='.filemtime($frontPath)
                : null;
            $backUrl   = is_file($backPath)
                ? asset("nametag/back/{$emp->id}.png").'?v='.filemtime($backPath)
                : null;
        }
    @endphp
    @php
        $rowClasses = 'hover:bg-slate-50/60 dark:hover:bg-white/5 bg-white dark:bg-transparent';
        $nt = $emp->nametag_status ?? 'none';
        if ($nt === 'processing') $rowClasses .= ' opacity-60';
    @endphp
    <tr data-emp-id="{{ $emp->id }}" class="{{ $rowClasses }}">
        <td class="px-3 py-2 align-top text-xs text-slate-500 dark:text-slate-300">
            <input type="checkbox"
                   name="ids[]"
                   value="{{ $emp->id }}"
                   class="chkRow rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                   @if(($emp->nametag_status ?? 'none') === 'processing') disabled @endif>
            <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-400">
                {{ $rowNo }}
            </div>
        </td>

        <td class="px-3 py-2 align-top hidden sm:table-cell">
            <div class="font-medium text-slate-800 dark:text-slate-100">
                {{ Str::upper($emp->nama_lengkap ?? $emp->nama) }}
            </div>
            <div class="flex items-center gap-2 mt-1">
                <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                    {{ ($emp->nip_label ?? 'NIP.') . ' ' . ($emp->nip ?? '—') }}
                </div>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 {{ $stClass }}">
                    {{ $st ?: '—' }}
                </span>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                {{ $emp->jabatan ?? '—' }}
            </div>
        </td>

        <td class="px-3 py-2 align-top hidden sm:table-cell">
            <div class="text-sm text-slate-800 dark:text-slate-100">
                {{ $emp->opd->nama ?? '—' }}
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ $emp->opdUnit->nama ?? '—' }}
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                {{ $emp->unitKerja->nama ?? '—' }}
            </div>
        </td>

        <td class="px-3 py-2 align-top">
            @if($qrToken)
                <div class="flex flex-col gap-1">
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $qrClass }}">
                        QR {{ $qrLabel ?? 'TERAKHIR' }}
                    </span>
                    @if($qrTime)
                        <span class="text-[11px] text-slate-500">
                            {{ $qrTime }} WIB
                        </span>
                    @endif
                    <div class="flex flex-wrap gap-1">
                                <a href="{{ url('/t/'.$qrToken) }}"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 dark:bg-navy-700 text-[11px] text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-navy-600">
                            Halaman Scan
                        </a>
                                <a href="{{ route('scan-logs.index', ['q' => $qrToken]) }}"
                                    class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 dark:bg-navy-700 text-[11px] text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-navy-600">
                            Log Scan
                        </a>
                    </div>
                </div>
            @else
                <span class="text-xs text-slate-400">
                    Belum pernah generate QR
                </span>
            @endif
        </td>

        <td class="px-3 py-2 align-top">
            @php
                $ntStatus = $emp->nametag_status ?? 'none';
            @endphp
            <div class="flex flex-col gap-1 items-start">
                {{-- Status Indicator --}}
                @if($ntStatus === 'failed')
                    <div class="text-[10px] text-rose-700 font-semibold">
                        ❌ Generate Gagal
                    </div>
                @elseif($ntStatus === 'processing')
                    <div class="text-[10px] text-blue-700 font-semibold">
                        ⏳ Sedang Diproses
                    </div>
                @elseif($ntStatus === 'ready')
                    <div class="text-[10px] text-emerald-700 font-semibold">
                        ✓ Siap
                    </div>
                @endif

                @if($frontUrl)
                    <button type="button"
                            onclick="openImgPreview('{{ $frontUrl }}')"
                            class="h-7 px-2 rounded border border-slate-300 dark:border-slate-700 text-[11px] hover:bg-slate-50 dark:hover:bg-white/5">
                        Preview Depan
                    </button>
                @endif
                @if($backUrl)
                    <button type="button"
                            onclick="openImgPreview('{{ $backUrl }}')"
                            class="h-7 px-2 rounded border border-slate-300 dark:border-slate-700 text-[11px] hover:bg-slate-50 dark:hover:bg-white/5">
                        Preview Belakang
                    </button>
                @endif
                @unless($frontUrl || $backUrl)
                    @if(!$isActive)
                        <span class="text-xs text-slate-400 dark:text-slate-500">
                            Status nonaktif
                        </span>
                    @else
                        <span class="text-xs text-slate-400 dark:text-slate-500">
                            Belum pernah generate
                        </span>
                    @endif
                @endunless
            </div>
        </td>

        <td class="px-3 py-2 align-top sm:hidden">
            <a href="{{ route('employees.show', $emp) }}" class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-white/5">Detail</a>
        </td>

        <td class="px-3 py-2 align-top text-right">
            <div class="inline-flex flex-wrap gap-2 justify-end">
                <a href="{{ route('employees.show', $emp) }}" title="Detail" class="inline-flex items-center h-8 w-8 justify-center rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-white/5">
                    {{-- Eye icon --}}
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                </a>
                @can('update', $emp)
                    <a href="{{ route('employees.edit', $emp) }}" title="Ubah" class="inline-flex items-center h-8 w-8 justify-center rounded-lg bg-brand-50 text-brand-700 border border-brand-200 hover:bg-brand-100">
                        {{-- Pencil icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M4 20h4.586a1 1 0 00.707-.293l9.414-9.414a1 1 0 000-1.414L15.414 4.586a1 1 0 00-1.414 0L4 14.586V19a1 1 0 001 1z"/></svg>
                    </a>
                @endcan
                @can('forceDelete', $emp)
                    <button type="button" title="Hapus Permanen" onclick="window.deleteEmployee({{ $emp->id }})" class="js-delete-employee inline-flex items-center h-8 w-8 justify-center rounded-lg bg-rose-600 text-white border border-rose-700 hover:bg-rose-700" data-emp-id="{{ $emp->id }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/></svg>
                    </button>
                @endcan
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
            Belum ada data pegawai.
        </td>
    </tr>
@endforelse
