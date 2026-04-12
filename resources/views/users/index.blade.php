<x-layouts.admin :title="'Pengguna – Anambas-ID'">
  @php($isSuper = auth()->user()->hasRole('superadmin'))
  @php($isImpersonating = session()->has('impersonate.by'))
  @php($myId = auth()->id())

  {{-- Flash / error --}}
  @if (session('ok'))
    <div class="mb-4 rounded-xl bg-emerald-50 text-emerald-700 px-4 py-3 ring-1 ring-emerald-200 text-sm">
      {{ session('ok') }}
    </div>
  @endif

  @if ($errors->any())
    <div class="mb-4 rounded-xl bg-rose-50 text-rose-700 px-4 py-3 ring-1 ring-rose-200 text-sm">
      <ul class="list-disc pl-5 space-y-1">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <x-slot:header>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Daftar Pengguna</h1>
        <p class="text-slate-500 dark:text-slate-400 text-sm">
          Kelola pengguna global, level OPD, maupun level Unit.
        </p>
      </div>
    </div>
  </x-slot:header>

  {{-- Toolbar --}}
  <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <form method="get" class="w-full md:max-w-lg">
      <div class="relative">
        <input
          type="text"
          name="q"
          value="{{ $q }}"
          placeholder="Cari nama / email…"
          class="w-full h-10 rounded-xl border border-slate-300 bg-white/90 pl-10 pr-28 text-sm
                 shadow-sm outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
        />
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
        </svg>

        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex gap-2">
          @if (filled($q))
            <a href="{{ route('users.index') }}"
               class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 text-xs">
              Reset
            </a>
          @endif
          <button
            class="inline-flex items-center h-8 px-4 rounded-lg bg-slate-900 text-white font-semibold hover:bg-slate-800 text-xs">
            Cari
          </button>
        </div>
      </div>
    </form>

    <a href="{{ route('users.create') }}"
       class="inline-flex items-center h-10 px-4 rounded-xl bg-brand-600 text-white text-sm font-semibold shadow-sm hover:bg-brand-700">
      <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v12m6-6H6"/>
      </svg>
      Tambah Pengguna
    </a>
  </div>

  {{-- Table --}}
  <div class="rounded-2xl bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 shadow-card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50/90 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300">
          <tr>
            <th class="px-4 py-2.5 text-left">Pengguna</th>
            <th class="px-4 py-2.5 text-left">OPD / Unit</th>
            <th class="px-4 py-2.5 text-left w-32">Status</th>
            <th class="px-4 py-2.5 text-right w-44">Aksi</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
          @forelse ($users as $u)
            <tr class="hover:bg-slate-50/70 dark:hover:bg-white/5">
              {{-- Pengguna (nama + email + level) --}}
              <td class="px-4 py-2.5 align-top">
                <div class="flex items-start gap-3">
                  <div class="h-8 w-8 shrink-0 rounded-full bg-brand-600 text-white grid place-items-center text-xs font-semibold">
                    {{ strtoupper(\Illuminate\Support\Str::substr($u->name, 0, 1)) }}
                  </div>
                  <div class="min-w-0 space-y-0.5">
                    <div class="font-medium truncate text-slate-900 dark:text-slate-50">
                      {{ $u->name }}
                    </div>
                    <div class="text-[11px] text-slate-500 truncate">
                      {{ $u->email }}
                    </div>
                    <div class="text-[11px] text-slate-400">
                      @if ($u->hasRole('superadmin'))
                        Superadmin
                      @elseif ($u->opd_unit_id)
                        Level Unit
                      @elseif ($u->opd_id)
                        Level OPD
                      @else
                        Global
                      @endif
                    </div>
                  </div>
                </div>
              </td>

              {{-- OPD + Unit --}}
              <td class="px-4 py-2.5 align-top">
                <div class="min-w-0 space-y-0.5">
                  <div class="truncate text-slate-900 dark:text-slate-50">
                    {{ $u->opd->nama ?? '—' }}
                  </div>
                  <div class="text-[11px] text-slate-500 truncate">
                    @if ($u->opd_unit_id && optional($u->opdUnit)->nama)
                      {{ $u->opdUnit->nama }}
                    @elseif ($u->opd_id)
                      — Level OPD (tanpa unit)
                    @else
                      —
                    @endif
                  </div>
                </div>
              </td>

              {{-- Status --}}
              <td class="px-4 py-2.5 align-top">
                @if ($u->is_active)
                  <span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 px-2.5 py-0.5 text-[11px] font-medium border border-emerald-100">
                    Aktif
                  </span>
                @else
                  <span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 px-2.5 py-0.5 text-[11px] font-medium border border-rose-100">
                    Nonaktif
                  </span>
                @endif
              </td>

              {{-- Actions --}}
              <td class="px-4 py-2.5 text-right align-top">
                <div class="inline-flex items-center gap-1.5 whitespace-nowrap">

                  {{-- Impersonate / Kembali --}}
                  @if ($isSuper)
                    @if ($isImpersonating && $u->id === $myId)
                      <form method="POST" action="{{ route('impersonate.stop') }}">
                        @csrf
                        <button type="submit"
                          class="inline-flex items-center h-8 px-3 rounded-md bg-indigo-600 text-white text-[11px] hover:bg-indigo-700">
                          Kembali
                        </button>
                      </form>
                    @elseif (!$u->hasRole('superadmin') && $u->id !== $myId)
                      <form method="POST" action="{{ route('impersonate.start', $u->id) }}"
                            onsubmit="return confirm('Masuk sebagai {{ $u->name }}?')">
                        @csrf
                        <button type="submit"
                          class="inline-flex items-center h-8 px-3 rounded-md border border-indigo-300 text-indigo-700 text-[11px] hover:bg-indigo-50">
                          Masuk
                        </button>
                      </form>
                    @endif
                  @endif

                  {{-- Ubah --}}
                  <a href="{{ route('users.edit', $u) }}"
                     class="inline-flex items-center h-8 px-3 rounded-md bg-amber-500 text-white text-[11px] hover:bg-amber-600">
                    Ubah
                  </a>

                  {{-- Hapus --}}
                  <form method="post" action="{{ route('users.destroy', $u) }}"
                        onsubmit="return confirm('Hapus pengguna ini?')">
                    @csrf
                    @method('DELETE')
                    <button
                      class="inline-flex items-center h-8 px-3 rounded-md bg-rose-600 text-white text-[11px] hover:bg-rose-700">
                      Hapus
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="px-4 py-10">
                <div class="flex flex-col items-center justify-center text-center space-y-1.5">
                  <div class="h-10 w-10 rounded-full bg-slate-100 dark:bg-white/10 grid place-items-center text-slate-500 mb-1.5">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6" d="M16 11V7a4 4 0 10-8 0v4m-2 0h12l1 9H3l1-9z"/>
                    </svg>
                  </div>
                  <div class="text-slate-600 dark:text-slate-300 font-medium text-sm">Belum ada pengguna.</div>
                  <div class="text-slate-500 text-xs">Mulai dengan menambahkan pengguna baru.</div>
                  <a href="{{ route('users.create') }}"
                     class="mt-2 inline-flex items-center h-9 px-4 rounded-lg bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700">
                    + Tambah Pengguna
                  </a>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Pagination summary + links --}}
  <div class="mt-4 flex flex-col items-center gap-2 md:flex-row md:justify-between">
    <div class="text-xs md:text-sm text-slate-600 dark:text-slate-300">
      Menampilkan
      <span class="font-semibold">{{ $users->firstItem() ?? ($users->count() ? 1 : 0) }}</span>–<span class="font-semibold">
        {{ $users->lastItem() ?? $users->count() }}
      </span>
      dari <span class="font-semibold">{{ $users->total() }}</span> pengguna
    </div>
    <div class="text-sm">
      {{ $users->links() }}
    </div>
  </div>
</x-layouts.admin>
