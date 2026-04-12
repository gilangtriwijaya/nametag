{{-- resources/views/logs/index.blade.php --}}
<x-layouts.admin :title="'Aktivitas – Log Sistem'">
    @php
        $q        = $q        ?? '';
        $event    = $event    ?? '';
        $logName  = $logName  ?? '';
        $userId   = $userId   ?? 0;
        $dateFrom = $dateFrom ?? '';
        $dateTo   = $dateTo   ?? '';

        // Label & warna event
        $eventLabels = [
            'created'  => 'DIBUAT',
            'updated'  => 'DIUBAH',
            'deleted'  => 'DIHAPUS',
            'restored' => 'DIPULIHKAN',
            'login'    => 'LOGIN',
            'logout'   => 'LOGOUT',
        ];

        $eventClasses = [
            'created'  => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'updated'  => 'bg-sky-100 text-sky-800 ring-sky-200',
            'deleted'  => 'bg-rose-100 text-rose-800 ring-rose-200',
            'restored' => 'bg-amber-100 text-amber-800 ring-amber-200',
            'login'    => 'bg-indigo-100 text-indigo-800 ring-indigo-200',
            'logout'   => 'bg-slate-100 text-slate-800 ring-slate-300',
        ];
    @endphp

    <x-slot:header>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Log Aktivitas</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm">
                    Rekam jejak tindakan penting di dalam aplikasi.
                </p>
            </div>
        </div>
    </x-slot:header>

    <form method="get"
          class="mb-5 flex flex-wrap items-end gap-4">
    
        {{-- Pencarian --}}
        <div class="flex-1 min-w-[220px]">
            <label class="block mb-1 text-xs font-semibold text-slate-600">Pencarian</label>
            <input name="q" value="{{ $q }}" placeholder="Deskripsi, properti, ID, dll."
                   class="w-full h-11 rounded-xl border border-slate-300 bg-white/90 px-3 text-[13px]
                          shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200"/>
        </div>
    
        {{-- Event --}}
        <div class="w-[160px]">
            <label class="block mb-1 text-xs font-semibold text-slate-600">Event</label>
            <select name="event"
                    class="h-11 w-full rounded-xl border border-slate-300 bg-white/90 px-3 text-[13px]
                           shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
                <option value="">Semua</option>
                @foreach($events ?? [] as $evt)
                    <option value="{{ $evt }}" @selected($event === $evt)>
                        {{ $eventLabels[$evt] ?? strtoupper($evt) }}
                    </option>
                @endforeach
            </select>
        </div>
    
        {{-- Causer (User) --}}
        <div class="w-[180px]">
            <label class="block mb-1 text-xs font-semibold text-slate-600">Causer</label>
            <select name="user_id"
                    class="h-11 w-full rounded-xl border border-slate-300 bg-white/90 px-3 text-[13px]
                           shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
                <option value="">Semua User</option>
                @foreach($users ?? [] as $u)
                    <option value="{{ $u->id }}" @selected($userId == $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
    
        {{-- Nama Log --}}
        <div class="w-[200px]">
            <label class="block mb-1 text-xs font-semibold text-slate-600">Nama Log</label>
            <input name="log" value="{{ $logName }}" placeholder="cth: auth, qr, system"
                   class="h-11 w-full rounded-xl border border-slate-300 bg-white/90 px-3 text-[13px]
                          shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200"/>
        </div>
    
        {{-- Rentang Tanggal --}}
        <div class="flex items-end gap-2 w-[260px]">
            <div class="flex-1">
                <label class="block mb-1 text-xs font-semibold text-slate-600">Dari</label>
                <input type="date" name="d1" value="{{ $dateFrom }}"
                       class="h-11 rounded-xl border border-slate-300 bg-white/90 px-2 text-[13px]
                              shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200"/>
            </div>
            <div class="flex-1">
                <label class="block mb-1 text-xs font-semibold text-slate-600">Sampai</label>
                <input type="date" name="d2" value="{{ $dateTo }}"
                       class="h-11 rounded-xl border border-slate-300 bg-white/90 px-2 text-[13px]
                              shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200"/>
            </div>
        </div>
    
        {{-- Tombol --}}
        <div class="flex gap-2 ml-auto">
            <button
                class="h-11 px-4 rounded-xl bg-slate-900 text-white text-sm font-semibold hover:bg-slate-800">
                Terapkan
            </button>
            <a href="{{ route('logs.index') }}"
               class="inline-flex items-center h-11 px-4 rounded-xl border border-slate-300 text-sm text-slate-700 hover:bg-slate-50">
                Reset
            </a>
        </div>
    
    </form>


    {{-- Ringkasan filter kecil --}}
    @if($q || $event || $logName || $userId || $dateFrom || $dateTo)
        <div class="mb-4 text-xs text-slate-500 flex flex-wrap gap-2">
            <span class="font-semibold text-slate-600">Filter aktif:</span>
            @if($q)
                <span class="px-2 py-0.5 rounded-full bg-slate-100">q: "{{ $q }}"</span>
            @endif
            @if($event)
                <span class="px-2 py-0.5 rounded-full bg-slate-100">event: {{ $eventLabels[$event] ?? $event }}</span>
            @endif
            @if($userId)
                @php
                    $causerUser = $users->firstWhere('id', $userId);
                @endphp
                <span class="px-2 py-0.5 rounded-full bg-slate-100">causer: {{ $causerUser?->name ?? 'ID: ' . $userId }}</span>
            @endif
            @if($logName)
                <span class="px-2 py-0.5 rounded-full bg-slate-100">log: {{ $logName }}</span>
            @endif
            @if($dateFrom || $dateTo)
                <span class="px-2 py-0.5 rounded-full bg-slate-100">
                    tanggal:
                    {{ $dateFrom ?: '…' }} – {{ $dateTo ?: '…' }}
                </span>
            @endif
        </div>
    @endif

    {{-- Tabel --}}
    <div class="rounded-2xl bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 shadow-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50/90 dark:bg-slate-900/40 text-[11px] uppercase tracking-wide text-slate-600 dark:text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left w-[170px]">Waktu</th>
                        <th class="px-4 py-3 text-left w-[130px]">Log</th>
                        <th class="px-4 py-3 text-left w-[110px]">Event</th>
                        <th class="px-4 py-3 text-left">Deskripsi</th>
                        <th class="px-4 py-3 text-left w-[190px]">Causer</th>
                        <th class="px-4 py-3 text-left w-[220px]">Subject</th>
                        <th class="px-4 py-3 text-right w-[110px]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse ($logs as $log)
                        @php
                            $created = null;
                            $timeLabel = $log->created_at ? app('\\App\\Support\\ViewHelpers')->datetime($log->created_at) : '—';
                            $timeDiff  = $log->created_at ? \Carbon\Carbon::parse($log->created_at)->diffForHumans() : 'waktu tidak diketahui';

                            $evKey     = $log->event ?? '';
                            $evLabel   = $eventLabels[$evKey]  ?? strtoupper($evKey ?: '—');
                            $evClass   = $eventClasses[$evKey] ?? 'bg-slate-100 text-slate-700 ring-slate-200';

                            // nama causer: prefer relasi causer, kalau nggak ada baru fallback ke alias
                            $causerName = $log->causer->name
                                ?? ($log->causer_name ?? null);
                        @endphp
                        <tr class="hover:bg-slate-50/60 dark:hover:bg-white/5">
                            {{-- Waktu --}}
                            <td class="px-4 py-3 align-top whitespace-nowrap">
                                <div class="text-xs font-medium text-slate-800 dark:text-slate-100">
                                    {{ $timeLabel }}{{ $timeLabel !== '—' ? ' WIB' : '' }}
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    {{ $timeDiff }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    ID: {{ $log->id }}
                                </div>
                            </td>

                            {{-- Log --}}
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex items-center rounded-full bg-slate-100 dark:bg-slate-800 px-2.5 py-1 text-[11px] font-medium text-slate-700 dark:text-slate-200">
                                    {{ $log->log_name ?? 'default' }}
                                </span>
                            </td>

                            {{-- Event --}}
                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $evClass }}">
                                    {{ $evLabel }}
                                </span>
                            </td>

                            {{-- Deskripsi --}}
                            <td class="px-4 py-3 align-top">
                                <div class="text-sm text-slate-800 dark:text-slate-100 truncate max-w-[520px]">
                                    {{ $log->description ?: '—' }}
                                </div>
                                @if(!empty($log->short_properties))
                                    <div class="mt-1 text-[11px] text-slate-500 truncate max-w-[520px]">
                                        {{ $log->short_properties }}
                                    </div>
                                @endif
                            </td>

                            {{-- Causer --}}
                            <td class="px-4 py-3 align-top">
                                @if($log->causer_id)
                                    <div class="text-sm text-slate-800 dark:text-slate-100">
                                        {{ $causerName ?: ('User #'.$log->causer_id) }}
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        ID: {{ $log->causer_id }}
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Sistem / tidak diketahui</span>
                                @endif
                            </td>

                            {{-- Subject --}}
                            <td class="px-4 py-3 align-top">
                                @if($log->subject_type)
                                    <div class="text-sm text-slate-800 dark:text-slate-100 truncate max-w-[210px]">
                                        {{ class_basename($log->subject_type) }}
                                        @if($log->subject_id)
                                            #{{ $log->subject_id }}
                                        @endif
                                    </div>
                                    @if(!empty($log->subject_label))
                                        <div class="text-[11px] text-slate-500 truncate max-w-[210px]">
                                            {{ $log->subject_label }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-xs text-slate-400">Tidak ada subject</span>
                                @endif
                            </td>

                            {{-- Aksi --}}
                            <td class="px-4 py-3 align-top text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center h-8 px-3 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-xs font-semibold"
                                    onclick="openLog({{ (int) $log->id }})">
                                    Detail
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada data log.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    <div class="mt-4 flex flex-col items-center gap-3 md:flex-row md:justify-between">
        <div class="text-sm text-slate-600 dark:text-slate-300">
            Menampilkan
            <span class="font-semibold">
                {{ $logs->firstItem() ?? ($logs->count() ? 1 : 0) }}
            </span>
            –
            <span class="font-semibold">
                {{ $logs->lastItem() ?? $logs->count() }}
            </span>
            dari
            <span class="font-semibold">{{ $logs->total() }}</span>
            log
        </div>
        <div class="text-sm">
            {{ $logs->onEachSide(1)->links() }}
        </div>
    </div>

    {{-- Modal detail (JSON mentah / ringkas) --}}
    <dialog id="logDetail" class="rounded-xl w-[min(900px,95vw)] p-0 shadow-2xl border border-slate-200">
        <div class="p-4 border-b flex items-center justify-between bg-slate-50">
            <div class="font-semibold text-sm">Detail Log</div>
            <button type="button"
                    onclick="document.getElementById('logDetail').close()"
                    class="text-slate-500 hover:text-slate-700 text-sm">
                ✕
            </button>
        </div>
        <div class="p-4">
            <pre id="logJson" class="text-xs bg-slate-900 text-slate-100 rounded p-3 overflow-auto max-h-[60vh]"></pre>
        </div>
        <div class="p-3 border-t bg-slate-50 text-right">
            <button type="button"
                    onclick="document.getElementById('logDetail').close()"
                    class="h-9 px-4 rounded-lg border border-slate-300 text-sm hover:bg-white">
                Tutup
            </button>
        </div>
    </dialog>

    <script>
        async function openLog(id) {
            try {
                const url = "{{ route('logs.show', ['activity' => '__ID__']) }}"
                    .replace('__ID__', id);

                const res = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }

                const data   = await res.json();
                const pretty = JSON.stringify(data, null, 2);
                document.getElementById('logJson').textContent = pretty;
                document.getElementById('logDetail').showModal();
            } catch (e) {
                console.error(e);
                alert('Gagal memuat detail log.');
            }
        }
    </script>
</x-layouts.admin>
