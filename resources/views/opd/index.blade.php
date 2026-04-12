<x-layouts.admin :title="'OPD – Anambas-ID'">
  <x-slot:header>
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Daftar OPD</h1>
        <p class="text-slate-500 dark:text-slate-400">
          Kelola organisasi perangkat daerah dan unit (sub-OPD) di bawahnya.
        </p>
      </div>
    </div>
  </x-slot:header>

  {{-- Toolbar --}}
  <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <form method="get" class="w-full md:max-w-xl">
      <div class="relative">
        <input
          type="text" name="q" value="{{ $q }}" placeholder="Cari nama / pimpinan / alamat…"
          class="w-full h-12 rounded-xl border border-slate-300 bg-white/90 pl-11 pr-28 text-[15px]
                 shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200"/>
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
        </svg>

        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex gap-2">
          @if (filled($q))
            <a href="{{ route('opd.index') }}"
               class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-sm">
              Reset
            </a>
          @endif
          <button class="inline-flex items-center h-9 px-4 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800 text-sm">
            Cari
          </button>
        </div>
      </div>
    </form>

    <a href="{{ route('opd.create') }}"
       class="inline-flex items-center h-12 px-4 rounded-xl bg-brand-600 text-white font-semibold shadow-soft hover:bg-brand-700">
      <svg class="mr-2 h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m6-6H6"/>
      </svg>
      Tambah OPD
    </a>
  </div>

  <div class="rounded-2xl bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50/90 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300">
          <tr>
            <th class="px-4 py-3 text-left">Nama</th>
            <th class="px-4 py-3 text-left hidden lg:table-cell">Alamat</th>
            <th class="px-4 py-3 text-right">Aksi</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
          @forelse ($opds as $opd)
            @php
              $rowId = 'opdUnitsRow_'.$opd->id;
              // Ambil SEMUA unit (urut nama). Jika controller sudah eager-load 'units', pakai itu.
              $allUnits = $opd->relationLoaded('units')
                ? $opd->units->sortBy('nama')->values()
                : \App\Models\OpdUnit::where('opd_id', $opd->id)->orderBy('nama')->get(['id','opd_id','nama','status']);
              $unitsCount = $allUnits->count();
            @endphp

            {{-- Row OPD --}}
            <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/40 align-top">
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="font-medium">{{ $opd->nama }}</div>
                  <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 text-slate-700 px-2 py-0.5 text-xs">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
                    </svg>
                    {{ $unitsCount }} unit
                  </span>
                </div>
                <div class="text-xs text-slate-500 md:hidden">/{{ $opd->slug }}</div>
              </td>
              <td class="px-4 py-3 hidden lg:table-cell">{{ $opd->alamat ?: '—' }}</td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <button type="button"
                          class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm"
                          onclick="toggleUnits('{{ $rowId }}')">
                    <svg class="mr-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 9l-7 7-7-7"/>
                    </svg>
                    Kelola Unit
                  </button>

                  <a href="{{ route('opd.edit', $opd) }}"
                     class="inline-flex items-center h-9 px-3 rounded-lg bg-amber-500 text-white hover:bg-amber-600 text-sm">
                    <svg class="mr-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                            d="M15.232 5.232l3.536 3.536M4 20h4l10-10-4-4L4 16v4z"/>
                    </svg>
                    Ubah
                  </a>

                  <form method="post" action="{{ route('opd.destroy', $opd) }}"
                        onsubmit="return confirm('Hapus OPD ini? Unit di bawahnya ikut terhapus, dan pegawai akan kehilangan keterikatan unit.')">
                    @csrf @method('delete')
                    <button class="inline-flex items-center h-9 px-3 rounded-lg bg-rose-600 text-white hover:bg-rose-700 text-sm">
                      <svg class="mr-1.5 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862A2 2 0 016.867 19.142L6 7m3 0V5a2 2 0 012-2h2a2 2 0 012 2v2m-9 0h12"/>
                      </svg>
                      Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>

            {{-- Collapsible: daftar SEMUA Unit --}}
            <tr id="{{ $rowId }}" class="hidden bg-slate-50/50 dark:bg-slate-900/30">
              <td colspan="7" class="px-4 py-4">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                  <div>
                    <div class="font-semibold">Unit (Sub-OPD) di bawah: {{ $opd->nama }}</div>
                    <p class="text-xs text-slate-500 mt-1">
                      Unit dipakai untuk pengelompokan & pembatasan akses admin unit. OPD induk tetap pemilik data.
                    </p>
                  </div>
                  <div class="flex gap-2">
                    <a href="{{ route('opd-units.index', ['opd_id' => $opd->id]) }}"
                       class="inline-flex items-center h-9 px-3 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm">
                      Lihat semua unit
                    </a>

                    {{-- Quick Add --}}
                    <form method="POST" action="{{ route('opd-units.store') }}" class="flex items-center gap-2">
                      @csrf
                      <input type="hidden" name="opd_id" value="{{ $opd->id }}">
                      <input type="text" name="nama" placeholder="Nama unit baru…"
                             class="h-9 w-56 rounded-lg border border-slate-300 px-3 text-sm focus:border-sky-500 focus:ring-4 focus:ring-sky-200"
                             required>
                      <button class="inline-flex items-center h-9 px-3 rounded-lg bg-sky-600 text-white hover:bg-sky-700 text-sm">
                        Tambah Unit
                      </button>
                    </form>
                  </div>
                </div>

                <div class="mt-3 overflow-auto rounded-lg border border-slate-200 dark:border-slate-800">
                  <table class="min-w-full text-xs">
                    <thead class="bg-white/70 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300">
                      <tr>
                        <th class="px-3 py-2 text-left">Nama Unit</th>
                        <th class="px-3 py-2 text-left">Status</th>
                        <th class="px-3 py-2 text-right">Aksi</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                      @forelse($allUnits as $u)
                        {{-- VIEW --}}
                        <tr id="unit-view-{{ $u->id }}">
                          <td class="px-3 py-2 font-medium">{{ $u->nama }}</td>
                          <td class="px-3 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px]
                              {{ $u->status === 'AKTIF' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                              {{ $u->status === 'AKTIF' ? 'Aktif' : 'Nonaktif' }}
                            </span>
                          </td>
                          <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-2">
                              {{-- Toggle Aktif/Nonaktif: KIRIMKAN opd_id --}}
                              <form method="POST" action="{{ route('opd-units.update', $u) }}" class="inline">
                                @csrf @method('PUT')
                                <input type="hidden" name="opd_id" value="{{ $u->opd_id }}">
                                <input type="hidden" name="nama" value="{{ $u->nama }}">
                                <input type="hidden" name="is_active" value="{{ $u->status === 'AKTIF' ? 0 : 1 }}">
                                <button class="inline-flex items-center h-8 px-2.5 rounded border text-xs
                                  {{ $u->status === 'AKTIF'
                                      ? 'border-amber-300 text-amber-700 hover:bg-amber-50'
                                      : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' }}">
                                  {{ $u->status === 'AKTIF' ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                              </form>

                              {{-- Edit inline --}}
                              <button type="button"
                                class="inline-flex items-center h-8 px-2.5 rounded bg-amber-500 text-white hover:bg-amber-600"
                                onclick="toggleEditUnit({{ $u->id }}, true)">
                                Ubah
                              </button>

                              {{-- Hapus --}}
                              <form method="POST" action="{{ route('opd-units.destroy', $u) }}"
                                    onsubmit="return confirm('Hapus unit ini? Pegawai tetap di OPD induk, keterikatan unit dikosongkan.')">
                                @csrf @method('DELETE')
                                <button class="inline-flex items-center h-8 px-2.5 rounded bg-rose-600 text-white hover:bg-rose-700">
                                  Hapus
                                </button>
                              </form>
                            </div>
                          </td>
                        </tr>

                        {{-- EDIT --}}
                        <tr id="unit-edit-{{ $u->id }}" class="hidden bg-slate-50/70">
                          <td colspan="3" class="px-3 py-3">
                            <form method="POST" action="{{ route('opd-units.update', $u) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                              @csrf @method('PUT')
                              <input type="hidden" name="opd_id" value="{{ $u->opd_id }}">
                              <div class="md:col-span-2">
                                <label class="block text-[11px] text-slate-500 mb-1">Nama</label>
                                <input name="nama" value="{{ $u->nama }}" required
                                       class="w-full h-9 rounded border border-slate-300 px-2 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
                              </div>
                              <div>
                                <label class="block text-[11px] text-slate-500 mb-1">Status</label>
                                <select name="is_active"
                                        class="w-full h-9 rounded border border-slate-300 px-2 text-sm focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
                                  <option value="1" {{ $u->status === 'AKTIF' ? 'selected' : '' }}>Aktif</option>
                                  <option value="0" {{ $u->status !== 'AKTIF' ? 'selected' : '' }}>Nonaktif</option>
                                </select>
                              </div>

                              <div class="md:col-span-3 flex items-center justify-end gap-2">
                                <button type="button"
                                        class="inline-flex items-center h-8 px-3 rounded border border-slate-300 text-slate-700 hover:bg-slate-100"
                                        onclick="toggleEditUnit({{ $u->id }}, false)">
                                  Batal
                                </button>
                                <button class="inline-flex items-center h-8 px-3 rounded bg-emerald-600 text-white hover:bg-emerald-700">
                                  Simpan
                                </button>
                              </div>
                            </form>
                          </td>
                        </tr>
                      @empty
                        <tr>
                          <td colspan="3" class="px-3 py-6 text-center text-slate-500">Belum ada unit.</td>
                        </tr>
                      @endforelse
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="px-4 py-10 text-center text-slate-500">Belum ada data.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-4">
    {{ $opds->links() }}
  </div>

  <script>
    function toggleUnits(rowId) {
      const el = document.getElementById(rowId);
      if (!el) return;
      el.classList.toggle('hidden');
    }
    function toggleEditUnit(id, on) {
      const view = document.getElementById('unit-view-' + id);
      const edit = document.getElementById('unit-edit-' + id);
      if (!view || !edit) return;
      if (on) { view.classList.add('hidden'); edit.classList.remove('hidden'); }
      else    { edit.classList.add('hidden'); view.classList.remove('hidden'); }
    }
    @if(!empty($focus_opd))
      (function() {
        const rowId = 'opdUnitsRow_{{ (int)$focus_opd }}';
        const el = document.getElementById(rowId);
        if (el && el.classList.contains('hidden')) el.classList.remove('hidden');
        setTimeout(() => el?.scrollIntoView({behavior:'smooth', block:'start'}), 50);
      })();
    @endif
  </script>
</x-layouts.admin>
