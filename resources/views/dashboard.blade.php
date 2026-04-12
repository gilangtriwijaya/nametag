{{-- resources/views/dashboard.blade.php --}}
<x-layouts.admin :title="'Dashboard'">

  {{-- ====== GRID KPI ====== --}}
  <div class="grid gap-4 lg:gap-6 sm:grid-cols-2 md:grid-cols-4">
    @if($isGlobal)
      <x-kpi.card title="Total OPD" :value="$kpi['total_opd'] ?? 0" icon="office" />
      <x-kpi.card title="Unit OPD" :value="$kpi['total_units'] ?? 0" icon="collection" />
      <x-kpi.card title="Pengguna" :value="$kpi['total_users'] ?? 0" icon="users" />
      <x-kpi.card title="Login Hari Ini" :value="$kpi['login_today'] ?? 0" icon="users" />
      <x-kpi.card title="Total Pegawai" :value="$kpi['emp_all'] ?? 0" icon="id" />
      <x-kpi.card title="Pegawai Aktif" :value="$kpi['emp_active'] ?? 0" icon="check" tone="emerald" />
      <x-kpi.card title="Pegawai Nonaktif" :value="$kpi['emp_inactive'] ?? 0" icon="x" tone="rose" />
      <x-kpi.card title="Sudah Generate Nametag" :value="$kpi['nametag_done'] ?? 0" icon="qrcode" tone="indigo" />
    @else
      <x-kpi.card title="Total Pegawai" :value="$kpi['emp_all'] ?? 0" icon="id" />
      <x-kpi.card title="Pegawai Aktif" :value="$kpi['emp_active'] ?? 0" icon="check" tone="emerald" />
      <x-kpi.card title="Pegawai Nonaktif" :value="$kpi['emp_inactive'] ?? 0" icon="x" tone="rose" />
      <x-kpi.card title="Sudah Generate Nametag" :value="$kpi['nametag_done'] ?? 0" icon="qrcode" tone="indigo" />
    @endif
  </div>

  {{-- ====== GRAFIK ====== --}}
  <div class="mt-6 grid gap-6 xl:grid-cols-3">
    {{-- Tren pegawai 12 bulan --}}
    <div class="xl:col-span-2 rounded-2xl p-5 bg-white ring-1 ring-slate-200 shadow-card
                dark:bg-navy-800 dark:ring-slate-700">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-semibold">Tren Pegawai (12 bulan)</h3>
      </div>
      <div class="relative">
        <canvas id="chartEmployees" height="120"></canvas>
      </div>
    </div>

    {{-- Login 7 hari terakhir --}}
    <div class="rounded-2xl p-5 bg-white ring-1 ring-slate-200 shadow-card
                dark:bg-navy-800 dark:ring-slate-700">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-lg font-semibold">Login 7 Hari Terakhir</h3>
      </div>
      <div class="relative">
        <canvas id="chartLogins" height="120"></canvas>
      </div>
    </div>
  </div>

  {{-- ====== LOG TERAKHIR + REKAP ====== --}}
  <div class="mt-6 grid gap-6 lg:grid-cols-3">
    {{-- Aktivitas terakhir --}}
    <div class="lg:col-span-2 rounded-2xl bg-white ring-1 ring-slate-200 shadow-card
                dark:bg-navy-800 dark:ring-slate-700">
      <div class="flex items-center justify-between px-5 pt-5">
        <h3 class="text-lg font-semibold">Aktivitas Terakhir</h3>
      </div>

      <div class="px-5 pb-5 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
              <th class="py-3">Waktu</th>
              <th class="py-3">Pengguna</th>
              <th class="py-3">Event</th>
              <th class="py-3">Deskripsi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse ($logs ?? [] as $log)
              <tr>
                <td class="py-3 whitespace-nowrap">@datetime($log->created_at)</td>
                <td class="py-3 whitespace-nowrap">{{ optional($log->causer)->name ?? '—' }}</td>
                <td class="py-3">
                  @php
                    $ev = strtolower((string) $log->event);
                    $badge = match (true) {
                      str_contains($ev,'create') || str_contains($ev,'generated') => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
                      str_contains($ev,'update') => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                      str_contains($ev,'delete') => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                      default => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                    };
                  @endphp
                  <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                    {{ $log->event ?? 'log' }}
                  </span>
                </td>
                <td class="py-3 text-slate-700 dark:text-slate-200">
                  {{ $log->description ?? '—' }}
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Rekap OPD/Unit --}}
    <div class="rounded-2xl bg-white ring-1 ring-slate-200 shadow-card
                dark:bg-navy-800 dark:ring-slate-700">
      <div class="flex items-center justify-between px-5 pt-5">
        <h3 class="text-lg font-semibold">{{ $listTitle ?? ($isGlobal ? 'Ringkasan' : 'Ringkasan Unit OPD') }}</h3>
      </div>
      <div class="px-5 pb-5 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
              <th class="py-3">{{ $isGlobal ? 'OPD' : 'Unit OPD' }}</th>
              <th class="py-3">Aktif</th>
              <th class="py-3">Nonaktif</th>
              <th class="py-3">Nametag</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            @forelse ($list ?? [] as $row)
              <tr>
                <td class="py-3">{{ $row->nama ?? $row['nama'] ?? '—' }}</td>
                <td class="py-3">{{ number_format($row->aktif ?? $row['aktif'] ?? 0) }}</td>
                <td class="py-3">{{ number_format($row->nonaktif ?? $row['nonaktif'] ?? 0) }}</td>
                <td class="py-3">{{ number_format($row->nametag ?? $row['nametag'] ?? 0) }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ====== SCRIPTS: Chart.js ====== --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <script>
    // Data dari controller
    const emp = @json($chartEmployees ?? ['labels'=>[], 'series'=>[]]);
    const log = @json($chartLogins ?? ['labels'=>[], 'series'=>[]]);

    // Chart pegawai (line)
    new Chart(document.getElementById('chartEmployees').getContext('2d'), {
      type: 'line',
      data: {
        labels: emp.labels,
        datasets: [{
          label: 'Pegawai Baru / Bulan',
          data: emp.series,
          borderWidth: 2,
          tension: .3
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: true, position: 'bottom' }},
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });

    // Chart login (bar)
    new Chart(document.getElementById('chartLogins').getContext('2d'), {
      type: 'bar',
      data: {
        labels: log.labels,
        datasets: [{
          label: 'Login',
          data: log.series,
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }},
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  </script>
</x-layouts.admin>
