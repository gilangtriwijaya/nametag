@props([
  'action', 'method' => 'POST',
  'user',
  'opds' => collect(),
  'roles' => collect(),
  'currentRoleIds' => [],
  'unitMap' => collect(),   {{-- map: { opd_id: [ {id,opd_id,nama,status}, ... ] } --}}
])

@php
  $rolesBase        = ($roles instanceof \Illuminate\Database\Eloquent\Collection) ? $roles->toBase() : collect($roles);
  $globalRoles      = $rolesBase->whereNull('opd_id')->values();
  $opdRoles         = $rolesBase->whereNotNull('opd_id')->values();
  $selectedRoleIds  = collect(old('roles', $currentRoleIds ?? []))->map(fn($v)=> (int)$v)->all();

  $selectedOpd      = old('opd_id', $user->opd_id);
  $selectedUnit     = old('opd_unit_id', $user->opd_unit_id);
@endphp

@if (session('ok'))
  <div class="mb-6 rounded-xl bg-emerald-50 text-emerald-700 px-4 py-3 ring-1 ring-emerald-200">
    {{ session('ok') }}
  </div>
@endif

@if ($errors->any())
  <div class="mb-6 rounded-xl bg-rose-50 text-rose-700 px-4 py-3 ring-1 ring-rose-200">
    <ul class="list-disc pl-5 space-y-1">
      @foreach ($errors->all() as $error)
        <li>{{ $error }}</li>
      @endforeach
    </ul>
  </div>
@endif

<div class="rounded-2xl bg-white dark:bg-navy-800 border border-slate-200 dark:border-slate-700 shadow-card p-6 md:p-8"
     x-data="{
        selectedOpd: '{{ (string)$selectedOpd }}',
        selectedUnit: '{{ (string)$selectedUnit }}',
        unitMap: @js($unitMap)
     }">

  <form method="POST" action="{{ $action }}" class="space-y-10">
    @csrf
    @if (strtoupper($method) !== 'POST') @method($method) @endif

    {{-- =================== Bagian: Akun =================== --}}
    <section>
      <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Akun</h3>
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium mb-2">Nama <span class="text-rose-600">*</span></label>
          <input type="text" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name"
                 class="w-full h-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/90 px-4 text-[15px] shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Email <span class="text-rose-600">*</span></label>
          <input type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email"
                 class="w-full h-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/90 px-4 text-[15px] shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200">
        </div>
      </div>
    </section>

    {{-- =================== Bagian: Keamanan =================== --}}
    <section>
      <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Keamanan</h3>
      <div class="grid md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium mb-2">
            Kata sandi @if(!$user->exists)<span class="text-rose-600">*</span>@endif
          </label>
          <input type="password" name="password" autocomplete="new-password"
                 class="w-full h-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/90 px-4 text-[15px] shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200"
                 placeholder="{{ $user->exists ? 'Biarkan kosong bila tidak diubah' : '' }}">
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Konfirmasi kata sandi</label>
          <input type="password" name="password_confirmation" autocomplete="new-password"
                 class="w-full h-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/90 px-4 text-[15px] shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200">
        </div>
      </div>
    </section>

    {{-- =================== Bagian: Organisasi =================== --}}
    <section>
      <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-4">Organisasi</h3>

      <div class="grid md:grid-cols-2 gap-6">
        {{-- OPD --}}
        @if (auth()->user()->hasRole('superadmin'))
          <div>
            <label class="block text-sm font-medium mb-2">OPD</label>
            <select name="opd_id" x-model="selectedOpd"
                    class="w-full h-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/90 px-4 text-[15px] shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200">
              <option value="">— Tanpa OPD (Global)</option>
              @foreach ($opds as $o)
                <option value="{{ $o->id }}" @selected((string)old('opd_id', $user->opd_id) === (string)$o->id)>{{ $o->nama }}</option>
              @endforeach
            </select>
            <p class="text-xs text-slate-500 mt-1">Pilih OPD untuk mengaktifkan pilihan Unit OPD dan role OPD.</p>
          </div>
        @else
          <input type="hidden" name="opd_id" value="{{ $selectedOpd }}">
          <div>
            <label class="block text-sm font-medium mb-2">OPD</label>
            <div class="w-full h-12 grid items-center rounded-xl border border-slate-200 dark:border-slate-700 px-4 text-[15px]">
              {{ optional($opds->firstWhere('id',$selectedOpd))->nama ?? '—' }}
            </div>
          </div>
        @endif

        {{-- UNIT OPD --}}
        <div>
          <label class="block text-sm font-medium mb-2">Unit OPD</label>
          <select name="opd_unit_id"
                  :disabled="!selectedOpd"
                  class="w-full h-12 rounded-xl border border-slate-300 dark:border-slate-600 bg-white/90 px-4 text-[15px] shadow-sm outline-none focus:border-sky-500 focus:ring-4 focus:ring-sky-200">
            <option value="">— Level OPD (tanpa unit)</option>
            <template x-if="selectedOpd">
              <template x-for="u in (unitMap[selectedOpd] || [])" :key="u.id">
                <option :value="u.id"
                        :selected="String(selectedUnit) === String(u.id)"
                        x-text="u.nama + (u.status === 'NONAKTIF' ? ' (nonaktif)' : '')"></option>
              </template>
            </template>
          </select>
          <p class="text-xs text-slate-500 mt-1">
            Memilih <strong>Unit OPD</strong> menjadikan akun sebagai operator level Unit (akses hanya pada unit tersebut).
            Kosongkan untuk akun level OPD.
          </p>
        </div>

        {{-- =================== Bagian: Role =================== --}}
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-2">Role</label>

          {{-- Panel GLOBAL --}}
          <div class="rounded-xl border dark:border-slate-700 p-4 mb-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Global</div>

            <template x-if="!selectedOpd">
              <div class="flex flex-wrap gap-2">
                @forelse ($globalRoles as $r)
                  <label class="cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $r->id }}" class="peer sr-only"
                           @checked(in_array($r->id, $selectedRoleIds))>
                    <span class="px-3 py-1.5 rounded-full border text-sm
                                border-slate-300 dark:border-slate-600
                                peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600
                                hover:bg-slate-50 dark:hover:bg-white/5">
                      {{ $r->name }}
                    </span>
                  </label>
                @empty
                  <p class="text-xs text-slate-500">Tidak ada role global.</p>
                @endforelse
              </div>
            </template>

            <template x-if="selectedOpd">
              <p class="text-xs text-slate-500">Role global disembunyikan karena Anda memilih OPD.</p>
            </template>
          </div>

          {{-- Panel ROLE PER OPD --}}
          <div class="rounded-xl border dark:border-slate-700 p-4">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Role pada OPD</div>

            <template x-if="selectedOpd">
              <div class="flex flex-wrap gap-2">
                @foreach ($opdRoles as $r)
                  <template x-if="String(selectedOpd) === '{{ (string)$r->opd_id }}'">
                    <label class="cursor-pointer">
                      <input type="checkbox" name="roles[]" value="{{ $r->id }}" class="peer sr-only"
                             @checked(in_array($r->id, $selectedRoleIds))>
                      <span class="px-3 py-1.5 rounded-full border text-sm
                                  border-slate-300 dark:border-slate-600
                                  peer-checked:bg-brand-600 peer-checked:text-white peer-checked:border-brand-600
                                  hover:bg-slate-50 dark:hover:bg-white/5">
                        {{ $r->name }}
                      </span>
                    </label>
                  </template>
                @endforeach
              </div>
            </template>

            <template x-if="!selectedOpd">
              <p class="text-xs text-slate-500">Untuk memilih role OPD, pilih OPD terlebih dahulu.</p>
            </template>
          </div>

          <p class="text-xs text-slate-500 mt-2">
            Pilih satu atau beberapa role. Non-superadmin tidak boleh menggabungkan role global dan role OPD.
          </p>
        </div>

        {{-- Status aktif --}}
        <div class="md:col-span-2">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? 1))
                   class="h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
            Aktif
          </label>
        </div>
      </div>
    </section>

    {{-- Tombol --}}
    <div class="flex items-center gap-3">
      <a href="{{ route('users.index') }}"
         class="inline-flex items-center h-12 px-5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/5">
        Batal
      </a>
      <button class="inline-flex items-center h-12 px-5 rounded-xl bg-brand-600 text-white font-semibold shadow-soft hover:bg-brand-700">
        Simpan
      </button>
    </div>
  </form>
</div>
