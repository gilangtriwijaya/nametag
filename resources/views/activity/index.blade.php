<x-layouts.admin :title="'Aktivitas – Log Sistem'">
  @php($q = $q ?? '')
  @php($event = $event ?? '')
  @php($logName = $logName ?? '')
  @php($dateFrom = $dateFrom ?? '')
  @php($dateTo = $dateTo ?? '')

  <x-slot:header>
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Log Aktivitas</h1>
        <p class="text-slate-500 dark:text-slate-400">Semua aktivitas aplikasi yang terekam.</p>
      </div>
    </div>
  </x-slot:header>

  {{-- Filter --}}
  <form method="get" class="mb-5 grid gap-3 md:grid-cols-5">
    <div class="md:col-span-2">
      <input name="q" value="{{ $q }}" placeholder="Cari deskripsi / properti…"
             class="w-full h-12 rounded-xl border border-slate-300 bg-white/90 px-4 text-[15px]
                    shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200"/>
    </div>

    <input name="event" value="{{ $event }}" placeholder="event (created/updated/deleted)"
           class="h-12 rounded-xl border border-slate-300 bg-white/90 px-4 text-[15px]
                  shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200"/>

    <input name="log" value="{{ $logName }}" placeholder="log_name"
           class="h-12 rounded-xl border border-slate-300 bg-white/90 px-4 text-[15px]
                  shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200"/>

    <div class="grid grid-cols-2 gap-2">
      <input type="date" name="d1" value="{{ $dateFrom }}"
             class="h-12 rounded-xl border border-slate-300 bg-white/90 px-3 text-[15px]
                    shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200"/>
      <input type="date" name="d2" value="{{ $dateTo }}"
             class="h-12 rounded-xl border border-slate-300 bg-white/90 px-3 text-[15px]
                    shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200"/>
    </div>

    <div class="flex items-center gap-2">
      <button class="h-12 px-5 rounded-xl bg-slate-900 text-white font-semibold hover:bg-slate-800">Terapkan</button>
      <a href="{{ route('activity.index') }}"
         class="h-12 px-5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50">Reset</a>
    </div>
  </form>

  {{-- Tabel --}}
  <div class="rounded-2xl bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50/90 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300">
          <tr>
            <th class="px-4 py-3 text-left w-[150px]">Waktu</th>
            <th class="px-4 py-3 text-left w-[130px]">Log</th>
            <th class="px-4 py-3 text-left w-[110px]">Event</th>
            <th class="px-4 py-3 text-left">Deskripsi</th>
            <th class="px-4 py-3 text-left w-[160px]">Causer</th>
            <th class="px-4 py-3 text-left w-[180px]">Subject</th>
            <th class="px-4 py-3 text-right w-[110px]">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          @forelse ($logs as $log)
            <tr class="hover:bg-slate-50/60 dark:hover:bg-white/5">
              <td class="px-4 py-3">{{ $log->created_at }}</td>
              <td class="px-4 py-3"><span class="font-medium">{{ $log->log_name ?? '—' }}</span></td>
              <td class="px-4 py-3">
                <span class="inline-flex items-center rounded-lg bg-slate-100 dark:bg-white/10 px-2.5 py-1 text-xs">
                  {{ $log->event ?? '—' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="truncate max-w-[520px]">{{ $log->description ?? '—' }}</div>
              </td>
              <td class="px-4 py-3">
                @if($log->causer_id)
                  ID: {{ $log->causer_id }}
                @else
                  —
                @endif
              </td>
              <td class="px-4 py-3">
                @if($log->subject_type)
                  <div class="truncate max-w-[180px]">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</div>
                @else
                  —
                @endif
              </td>
              <td class="px-4 py-3 text-right">
                <button
                  class="h-9 px-3 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 text-sm"
                  onclick="openLog({{ (int)$log->id }})">
                  Lihat
                </button>
              </td>
            </tr>
          @empty
            <tr><td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada data.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination --}}
  <div class="mt-4 flex flex-col items-center gap-3 md:flex-row md:justify-between">
    <div class="text-sm text-slate-600 dark:text-slate-300">
      Menampilkan
      <span class="font-semibold">{{ $logs->firstItem() ?? ($logs->count() ? 1 : 0) }}</span>–<span class="font-semibold">{{ $logs->lastItem() ?? $logs->count() }}</span>
      dari <span class="font-semibold">{{ $logs->total() }}</span> log
    </div>
    <div>{{ $logs->links() }}</div>
  </div>

  {{-- Modal detail --}}
  <dialog id="logDetail" class="rounded-xl w-[min(900px,95vw)] p-0">
    <div class="p-5 border-b flex items-center justify-between">
      <div class="font-semibold">Detail Log</div>
      <button onclick="document.getElementById('logDetail').close()" class="text-slate-500 hover:text-slate-700">✕</button>
    </div>
    <div class="p-5">
      <pre id="logJson" class="text-xs bg-slate-50 rounded p-3 overflow-auto max-h-[60vh]"></pre>
    </div>
    <div class="p-4 border-t text-right">
      <button onclick="document.getElementById('logDetail').close()" class="h-10 px-4 rounded-lg border">Tutup</button>
    </div>
  </dialog>

  <script>
    async function openLog(id) {
      try {
        const res = await fetch(`{{ route('activity.index') }}`.replace('/logs','/logs') + '/' + id, {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        const pretty = JSON.stringify(data, null, 2);
        document.getElementById('logJson').textContent = pretty;
        document.getElementById('logDetail').showModal();
      } catch (e) {
        alert('Gagal memuat detail log');
      }
    }
  </script>
</x-layouts.admin>
