@php
    use Illuminate\Support\Str;
    use Illuminate\Database\Eloquent\Model;
    use Carbon\Carbon;

    /** ===== Sumber data & helper nilai ===== */
    $employee   = $employee   ?? null;
    $isModel    = $employee instanceof Model;
    $sourceData = $isModel ? $employee->toArray() : (is_array($employee) ? $employee : []);
    $isEdit     = $isModel && ($employee->exists ?? false);

    $val = function (string $field, $default = null) use ($sourceData) {
        $old = old($field);
        if (!is_null($old)) return $old;
        $raw = data_get($sourceData, $field, $default);
        if ($raw instanceof Carbon) return $raw->format('Y-m-d');
        return $raw;
    };

    $invalid = fn(string $name) =>
        $errors->has($name)
            ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
            : 'border-slate-300 focus:ring-slate-400 focus:border-slate-400';

    /** ===== Konteks pengguna & penguncian ===== */
    /** @var \App\Models\User $me */
    $me = auth()->user();
    $isSuper    = $me?->hasRole('superadmin');
    $myOpdId    = (int) ($me->opd_id ?? 0);
    $myUnitId   = (int) ($me->opd_unit_id ?? 0);
    $lockOpd    = !$isSuper && $myOpdId > 0;   // admin OPD / admin Unit: OPD lock
    $lockUnit   = $myUnitId > 0;               // admin Unit: Unit lock

    // SSO allowed OPD ids for this app (if any). Used for global-type accounts that are limited.
    $ssoAllowed = [];
    if ($me && method_exists($me, 'ssoAllowedOpdIds')) {
      try { $ssoAllowed = $me->ssoAllowedOpdIds(); } catch (\Throwable $e) { $ssoAllowed = []; }
    }

    /** ===== Nilai terpilih awal OPD & Unit untuk form ===== */
    $selectedOpd  = (string) ($val('opd_id') ?? ($lockOpd ? $myOpdId : ''));
    $selectedUnit = (string) ($val('opd_unit_id') ?? ($lockUnit ? $myUnitId : ''));
    $selectedUnitKerja = (string) ($val('unit_kerja_id') ?? '');

    /** ===== Sediakan daftar OPD & Unit map ===== */
    $opdQuery = \App\Models\Opd::query()->orderBy('nama');
    // If user is non-super and bound to a single OPD via local opd_id, restrict to that one
    if (!$isSuper && $myOpdId) {
      $opdQuery->where('id', $myOpdId);
    }
    // If SSO provided an allowed list (multiple OPD ids), and user is not bound to single opd,
    // limit the dropdown to those OPDs so CRUD form shows only allowed choices.
    if (!empty($ssoAllowed) && !$myOpdId) {
      $opdQuery->whereIn('id', $ssoAllowed);
    }
    $opds = $opdQuery->get(['id','nama']);

    $unitMap = [];
    if ($opds->isNotEmpty()) {
        $unitQuery = \App\Models\OpdUnit::query()
            ->whereIn('opd_id', $opds->pluck('id'))
            ->orderBy('nama');

        if ($lockUnit) {
            $unitQuery->where('id', $myUnitId);
        }

        $units = $unitQuery->get(['id','opd_id','nama','status']);

        foreach ($units as $u) {
            $unitMap[(string) $u->opd_id][] = [
                'id'     => (int) $u->id,
                'opd_id' => (int) $u->opd_id,
                'nama'   => $u->nama,
                'status' => $u->status,
            ];
        }
    }

      // ===== Unit Kerja (normalized) map =====
      $unitKerjaMap = [];
      if ($opds->isNotEmpty()) {
        $ukQuery = \App\Models\UnitKerja::query()
          ->whereIn('opd_id', $opds->pluck('id'))
          ->orderBy('nama');

        $unitKerjas = $ukQuery->get(['id','opd_id','nama','status']);
        foreach ($unitKerjas as $uk) {
          $unitKerjaMap[(string) $uk->opd_id][] = [
            'id'     => (int) $uk->id,
            'opd_id' => (int) $uk->opd_id,
            'nama'   => $uk->nama,
            'status' => $uk->status,
          ];
        }
      }

    /** ===== Foto saat ini (kalau edit) ===== */
    $currentFoto = $val('foto_path');
    $currentFotoSrc = null;
    if ($currentFoto) {
        // di DB: "uploads/employees/namafile.png" (di dalam public_html/anambas-id/)
        $currentFotoSrc = Str::startsWith($currentFoto, ['http://','https://'])
            ? $currentFoto
            : asset(ltrim($currentFoto, '/'));
    }

    /** ===== SK saat ini (kalau edit) ===== */
    $currentSkPath = $val('sk_file_path');
    $currentSkUrl  = null;
    if ($currentSkPath) {
        $currentSkUrl = Str::startsWith($currentSkPath, ['http://','https://'])
            ? $currentSkPath
            : asset(ltrim($currentSkPath, '/'));
    }

    /** ===== Ukuran standar foto nametag (px) ===== */
    // Ubah ke square 3x3 (display/crop square) — gunakan nilai lebar existing
    $photoWidth  = 560;
    $photoHeight = 560;
@endphp

<form method="POST"
  action="{{ $isEdit ? route('employees.update', $employee) : route('employees.store') }}"
  enctype="multipart/form-data"
  class="space-y-2">
  @csrf
  @if($isEdit) @method('PUT') @endif

  {{-- Error global --}}
  @if($errors->any())
    <div class="p-3 rounded bg-red-100 text-red-800">
      <ul class="list-disc list-inside">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  {{-- Main layout: Form (75%) + Guidance (25%) --}}
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
    {{-- FORM AREA (75%) --}}
    <div class="lg:col-span-3">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        {{-- ===================== OPD ===================== --}}
        @if($isSuper || (!empty($ssoAllowed) && !$myOpdId))
      <div
        x-data="{ selectedOpd: '{{ $selectedOpd }}', selectedUnit: '{{ $selectedUnit }}', selectedUnitKerja: '{{ $selectedUnitKerja }}', unitMap: @js($unitMap), unitKerjaMap: @js($unitKerjaMap) }">
        <label class="block text-sm mb-1">OPD <span class="text-red-600">*</span></label>
        <select name="opd_id"
                x-model="selectedOpd"
                class="w-full px-3 py-2 rounded {{ $invalid('opd_id') }}"
                required>
          <option value="">— Pilih OPD —</option>
          @foreach($opds as $opd)
            <option value="{{ $opd->id }}">{{ $opd->nama }}</option>
          @endforeach
        </select>
        @error('opd_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

        {{-- Unit OPD (tergantung OPD) --}}
        <div class="mt-4">
                <label class="block text-sm mb-1">Unit OPD</label>
          <select name="opd_unit_id"
                  :disabled="!selectedOpd"
                  class="w-full px-3 py-2 rounded {{ $invalid('opd_unit_id') }}">
            <option value="">— Tanpa Unit (level OPD) —</option>
            <template x-if="selectedOpd">
              <template x-for="u in (unitMap[selectedOpd] || [])" :key="u.id">
                <option :value="u.id"
                        x-text="u.nama + (u.status === 'NONAKTIF' ? ' (nonaktif)' : '')"
                        :selected="String(selectedUnit) === String(u.id)"></option>
              </template>
            </template>
          </select>
          @error('opd_unit_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
          <p class="text-xs text-slate-500 mt-1">Pilih Unit jika pegawai melekat ke sub-OPD tertentu.</p>
        </div>

        {{-- Unit Kerja (normalized) --}}
        <div class="mt-4">
          <label class="block text-sm mb-1">Unit Kerja</label>
          <select name="unit_kerja_id"
                  :disabled="!selectedOpd"
                  class="w-full px-3 py-2 rounded {{ $invalid('unit_kerja_id') }}">
            <option value="">— Tanpa Unit Kerja —</option>
            <template x-if="selectedOpd">
              <template x-for="uk in (unitKerjaMap[selectedOpd] || [])" :key="uk.id">
                <option :value="uk.id"
                        x-text="uk.nama + (uk.status === 'NONAKTIF' ? ' (nonaktif)' : '')"
                        :selected="String(selectedUnitKerja) === String(uk.id)"></option>
              </template>
            </template>
          </select>
          @error('unit_kerja_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
          <p class="text-xs text-slate-500 mt-1">Pilih Unit Kerja yang sesuai dengan OPD.</p>
        </div>
      </div>
    @else
      {{-- Non-super: OPD terkunci --}}
      <input type="hidden" name="opd_id" value="{{ $myOpdId ?: $selectedOpd }}">
      <div>
        <label class="block text-sm mb-1">OPD</label>
        <div class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
          {{ optional($opds->first())->nama ?? '—' }}
        </div>
      </div>

      {{-- Unit OPD --}}
      @if($lockUnit)
        {{-- Admin Unit: Unit dikunci --}}
        <input type="hidden" name="opd_unit_id" value="{{ $myUnitId }}">
        <div>
            <label class="block text-sm mb-1">Unit OPD</label>
          <div class="w-full px-3 py-2 rounded border border-slate-200 bg-slate-50">
            {{ data_get($unitMap, $myOpdId.'.0.nama')
                  ?? optional(\App\Models\OpdUnit::find($myUnitId))->nama
                  ?? '—' }}
          </div>
          <p class="text-xs text-slate-500 mt-1">Unit dikunci sesuai akun Anda.</p>
        </div>
      @else
        {{-- Admin OPD: boleh pilih salah satu Unit di OPD-nya --}}
        <div>
            <label class="block text-sm mb-1">Unit OPD</label>
          <select name="opd_unit_id" class="w-full px-3 py-2 rounded {{ $invalid('opd_unit_id') }}">
            <option value="">— Tanpa Unit (level OPD) —</option>
            @foreach($unitMap[(string) $myOpdId] ?? [] as $u)
              <option value="{{ $u['id'] }}" @selected((string) $selectedUnit === (string) $u['id'])>
                {{ $u['nama'] }}{{ $u['status'] === 'NONAKTIF' ? ' (nonaktif)' : '' }}
              </option>
            @endforeach
          </select>
          @error('opd_unit_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
      @endif
    @endif

    {{-- ===================== NIP & identitas dasar ===================== --}}
    <div>
      <label class="block text-sm mb-1">NIP <span class="text-red-600">*</span></label>
      <input type="text" name="nip" value="{{ $val('nip') }}"
             class="w-full px-3 py-2 rounded {{ $invalid('nip') }}" required>
      @error('nip') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm mb-1">Gelar Depan</label>
      <input type="text" name="gelar_depan" value="{{ $val('gelar_depan') }}"
             placeholder='Contoh: Dr, Ir, H, Hj, "DR", "APT"'
             class="w-full px-3 py-2 rounded {{ $invalid('gelar_depan') }}">
      <p class="text-xs text-gray-500 mt-1">Gunakan kutip dua untuk preserve case: <code class="bg-gray-100 px-1 rounded">"DR"</code> akan tetap DR, bukan Dr</p>
      @error('gelar_depan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- ===================== Gelar Belakang ===================== --}}
    <div>
      <label class="block text-sm mb-1">Gelar Belakang</label>
      <input type="text" 
             name="gelar_belakang" 
             value="{{ old('gelar_belakang') ?? $val('gelar_belakang_input') ?? $val('gelar_belakang') }}"
             placeholder='Contoh: S.Psi, S.I.P, M.Kom, "S.IP"'
             class="w-full px-3 py-2 rounded {{ $invalid('gelar_belakang') }}">
      <p class="text-xs text-gray-500 mt-1">Gunakan kutip dua untuk preserve case: <code class="bg-gray-100 px-1 rounded">"S.IP"</code> akan tetap S.IP, bukan S.Ip</p>
      @error('gelar_belakang') 
        <p class="text-xs text-red-600 mt-1">{{ $message }}</p> 
      @enderror
    </div>
    {{-- ===================== /Gelar Belakang ===================== --}}

    <div>
      <label class="block text-sm mb-1">Nama <span class="text-red-600">*</span></label>
      <input type="text" name="nama" value="{{ $val('nama') }}"
             placeholder="Nama tanpa gelar"
             class="w-full px-3 py-2 rounded {{ $invalid('nama') }}" required>
      @error('nama') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Golongan Darah</label>
      <select name="gol_darah"
              class="w-full rounded border px-3 py-2 {{ $invalid('gol_darah') }}">
        @php
          $golVal = $val('gol_darah', '');
          $opt = ['' => '—', 'A-' => 'A-', 'A+' => 'A+', 'B-' => 'B-', 'B+' => 'B+', 'AB-' => 'AB-', 'AB+' => 'AB+', 'O-' => 'O-', 'O+' => 'O+'];
        @endphp
        @foreach($opt as $k => $v)
          <option value="{{ $k }}" @selected($golVal === $k)>{{ $v }}</option>
        @endforeach
      </select>
      @error('gol_darah') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm mb-1">Tipe Jabatan <span class="text-red-600">*</span></label>
      <select name="jabatan_type" class="w-full px-3 py-2 rounded {{ $invalid('jabatan_type') }}" required>
        @foreach(['PELAKSANA','FUNGSIONAL','PENGAWAS','ADMINISTRATOR','PIMPINAN TINGGI PRATAMA'] as $jt)
          <option value="{{ $jt }}" {{ $val('jabatan_type','PELAKSANA') === $jt ? 'selected' : '' }}>
            {{ $jt }}
          </option>
        @endforeach
      </select>
      @error('jabatan_type') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
      <label class="block text-sm mb-1">Nama Jabatan</label>
      <input type="text" name="jabatan" value="{{ $val('jabatan') }}"
             class="w-full px-3 py-2 rounded {{ $invalid('jabatan') }}">
      @error('jabatan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Status, Golongan, Pangkat: moved so Status sits under Name and golongan/pangkat react to it --}}
    @php
      // PNS golongan -> pangkat mapping (from provided screenshot/list)
      $pnsMap = [
        'Ia' => 'Juru Muda',
        'Ib' => 'Juru Muda Tk.I',
        'Ic' => 'Juru',
        'Id' => 'Juru Tk.I',
        'IIa' => 'Pengatur Muda',
        'IIb' => 'Pengatur Muda Tk.I',
        'IIc' => 'Pengatur',
        'IId' => 'Pengatur Tk.I',
        'IIIa' => 'Penata Muda',
        'IIIb' => 'Penata Muda Tk.I',
        'IIIc' => 'Penata',
        'IIId' => 'Penata Tk.I',
        'IVa' => 'Pembina',
        'IVb' => 'Pembina Tk.I',
        'IVc' => 'Pembina Utama Muda',
        'IVd' => 'Pembina Utama Madya',
        'IVe' => 'Pembina Utama',
      ];
      $pppkList = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI'];
      $initialStatus = $val('status_kepegawaian', 'PNS');
      $initialGol = $val('golongan', '');
      $initialPangkat = $val('pangkat', '');
    @endphp

    <div x-data="employeeRankComponent(@js($pnsMap), @js($pppkList), '{{ $initialStatus }}', '{{ $initialGol }}', '{{ $initialPangkat }}')">
      <div>
        <label class="block text-sm mb-1">Status Kepegawaian <span class="text-red-600">*</span></label>
        <select name="status_kepegawaian" x-model="status" x-on:change="onStatusChange()" class="w-full px-3 py-2 rounded {{ $invalid('status_kepegawaian') }}" required>
          <option value="PNS" @selected($initialStatus === 'PNS')>PNS</option>
          <option value="PPPK" @selected($initialStatus === 'PPPK')>PPPK</option>
        </select>
        @error('status_kepegawaian') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
      </div>

      <div class="mt-2 grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm mb-1">Golongan</label>
          <select name="golongan" x-model="golongan" @change="onGolonganChange()" class="w-full px-3 py-2 rounded {{ $invalid('golongan') }}">
            <template x-for="opt in golonganOptions" :key="opt">
              <option x-bind:value="opt" x-text="opt" x-bind:selected="opt === golongan"></option>
            </template>
          </select>
          @error('golongan') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
          <label class="block text-sm mb-1">Pangkat</label>
          <input type="text" name="pangkat" x-model="pangkat" readonly class="w-full px-3 py-2 rounded {{ $invalid('pangkat') }} bg-slate-50">
          @error('pangkat') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>
      </div>
    </div>

    {{-- ===================== Foto + Crop ===================== --}}
        <div class="md:col-span-2"
          x-data="fotoCropperComponent(@js($currentFotoSrc), @js($currentFoto))">
      <label class="block text-sm mb-1">Foto</label>

      {{-- Preview foto yang saat ini tersimpan (jika edit & belum ada crop baru) --}}
      <template x-if="!croppedPreview && initialPreview">
        <div class="mb-2">
          <img :src="initialPreview" alt="Foto pegawai" class="h-24 w-24 rounded border object-cover">
        </div>
      </template>

      {{-- Input file asli --}}
      <input type="file"
             name="foto"
             x-ref="fileInput"
             @change="onFileChange"
             accept="image/*"
             class="w-full px-3 py-2 rounded {{ $invalid('foto') }}">

      {{-- Hidden field untuk menyimpan data crop --}}
      <input type="hidden" name="crop_x"      x-ref="cropX">
      <input type="hidden" name="crop_y"      x-ref="cropY">
      <input type="hidden" name="crop_width"  x-ref="cropWidth">
      <input type="hidden" name="crop_height" x-ref="cropHeight">
      {{-- Hidden field to persist cleaned image path for server save --}}
      <input type="hidden" name="foto_path" x-ref="fotoPath" value="">

      <p class="text-xs mt-1 {{ $errors->has('foto') ? 'text-red-600' : 'text-slate-500' }}">
        Format: JPG/PNG/WebP, maks 2 MB. {{ $isEdit ? 'Kosongkan jika tidak mengganti.' : '' }}
      </p>
      @error('foto') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror

      {{-- Area cropper --}}
      <div x-show="showCropper"
           x-cloak
           class="mt-3 border rounded p-3 bg-slate-50">
        <p class="text-xs text-slate-600 mb-2">
          Atur area foto yang diinginkan, lalu klik <strong>Terapkan</strong>.
          Pengaturan ini akan dipakai untuk foto di nametag.
        </p>
        <div class="max-h-80 overflow-auto flex items-center justify-center">
          <img x-ref="cropperImage" class="max-w-full">
        </div>
        <div class="mt-3 flex gap-2">
          <button type="button"
                  @click="applyCrop"
                  class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700">
            Terapkan
          </button>
          <button type="button"
                  @click="cancelCrop"
                  class="px-3 py-1.5 rounded bg-gray-200 text-sm hover:bg-gray-300">
            Batal
          </button>
        </div>
      </div>

      {{-- Aksi pembersihan background: tampil jika ada sumber (crop preview atau foto tersimpan) --}}
       <div x-show="croppedPreview || originalFilename"
         x-cloak
         class="mt-3">
        <template x-if="croppedPreview">
          <div>
            <p class="text-xs text-slate-600 mb-1">
              Preview komposisi foto (perkiraan tampilan di nametag):
            </p>
            <img :src="croppedPreview" alt="Preview foto"
                 class="h-24 w-24 rounded border object-cover">
          </div>
        </template>
        
          <div class="mt-2">
          <template x-if="cleanedUrl">
            <a :href="cleanedUrl" target="_blank" class="ml-3 text-sm text-blue-600 underline">Lihat hasil</a>
          </template>

              <div x-show="showProgress" x-cloak class="mt-3">
                <div class="w-full bg-slate-200 h-2 rounded overflow-hidden">
                  <div :style="`width:${progressPercent}%`" class="h-2 bg-blue-600"></div>
                </div>
                <p class="text-xs text-slate-600 mt-1">Progress: <span x-text="progressPercent"></span>% — <span x-text="progressMessage"></span></p>
              </div>

          <!-- cleanedUrl link remains; inline gray simulation preview removed -->

          <p x-show="cleanError" class="text-xs text-red-600 mt-1" x-text="cleanError"></p>
        </div>
      </div>
    </div>
    {{-- ===================== /Foto ===================== --}}

    {{-- ===================== Dokumen SK (PDF) ===================== --}}
    <div class="md:col-span-2">
      <label class="block text-sm mb-1">Dokumen SK Kepegawaian (PDF)</label>

      @if($currentSkUrl)
        <div class="mb-2 text-xs text-slate-600">
          SK saat ini:
          <a href="{{ $currentSkUrl }}" target="_blank" rel="noreferrer"
             class="underline text-blue-600 hover:text-blue-800">
            {{ basename($currentSkPath) }}
          </a>
        </div>
      @endif

      <input type="file"
             name="sk_file"
             accept="application/pdf"
             class="w-full px-3 py-2 rounded {{ $invalid('sk_file') }}">

      <p class="text-xs mt-1 text-slate-500">
        Format: PDF. Kosongkan jika tidak menambah / mengganti dokumen SK.
      </p>
      @error('sk_file') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    {{-- ===================== /Dokumen SK ===================== --}}
      </div> {{-- End of form grid (col-span-3) --}}
    </div> {{-- End of form area (lg:col-span-3) --}}

    {{-- GUIDANCE AREA (25%) --}}
    <div class="lg:col-span-1">
      <div class="sticky top-4 space-y-4">
        {{-- Panduan Gelar --}}
        <div class="p-3 bg-blue-50 rounded border border-blue-200">
          <p class="text-xs text-slate-700 font-semibold mb-2">📝 Gelar Belakang</p>
          
          <ul class="text-xs text-slate-700 space-y-2 list-disc list-inside">
            <li>Format normal: <span class="font-mono text-blue-600">S.Psi, M.Kom, S.T</span></li>
            <li>Otomatis: Huruf pertama besar, sisanya kecil setelah titik.</li>
            <li>
              Preserve case dengan kutip ganda: <span class="font-mono text-blue-600">S."IP"</span> 
              <br><span class="text-blue-600">→ S.IP</span>
            </li>
            <li>
              Contoh: <span class="font-mono text-blue-600">S."Tr"."IP"</span> 
              <br><span class="text-blue-600">→ S.Tr.IP</span>
            </li>
          </ul>
        </div>

        {{-- Info umum --}}
        <div class="p-3 bg-slate-50 rounded border border-slate-200">
          <p class="text-xs text-slate-700 font-semibold mb-2">ℹ️ Info Umum</p>
          <ul class="text-xs text-slate-600 space-y-1 list-disc list-inside">
            <li>Form bertanda <span class="text-red-600">*</span> wajib diisi</li>
            <li>Foto: Upload JPG/PNG, format square</li>
            <li>SK: Upload file PDF (opsional)</li>
          </ul>
        </div>
      </div>
    </div> {{-- End of guidance area (lg:col-span-1) --}}
  </div> {{-- End of main layout grid --}}

  <div class="pt-2 flex gap-2">
    <button type="submit" class="px-4 py-2 rounded bg-blue-700 text-white hover:bg-blue-800">
      {{ $submitLabel ?? ($isEdit ? 'Simpan Perubahan' : 'Simpan') }}
    </button>
    <a href="{{ route('employees.index') }}"
       class="px-4 py-2 rounded bg-gray-200 hover:bg-gray-300">
      Batal
    </a>
  </div>
</form>

@push('styles')
  {{-- Cropper.js CSS --}}
  <link rel="stylesheet"
        href="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.css">
@endpush

@push('scripts')
  {{-- Cropper.js --}}
  <script src="https://unpkg.com/cropperjs@1.6.2/dist/cropper.min.js"></script>

  <script>
    function employeeRankComponent(pnsMap, pppkList, initialStatus, initialGol, initialPangkat) {
      return {
        status: initialStatus || 'PNS',
        golongan: initialGol || '',
        pangkat: initialPangkat || '',
        pnsMap: pnsMap || {},
        pppkList: pppkList || [],
        golonganOptions: [],

        init() {
          this.buildOptions();
          if (this.status === 'PNS' && this.golongan) {
            this.pangkat = this.pnsMap[this.golongan] ?? this.pangkat;
          }
        },

        buildOptions() {
          if (this.status === 'PNS') {
            this.golonganOptions = Object.keys(this.pnsMap);
            if (!this.golonganOptions.includes(this.golongan)) this.golongan = '';
          } else {
            this.golonganOptions = this.pppkList;
            if (!this.golonganOptions.includes(this.golongan)) this.golongan = '';
          }
        },

        onGolonganChange() {
          if (this.status === 'PNS') {
            this.pangkat = this.pnsMap[this.golongan] ?? '';
          } else {
            this.pangkat = '';
          }
        },

        onStatusChange() {
          this.buildOptions();
          if (this.status === 'PPPK') {
            this.pangkat = '';
          } else {
            if (this.golongan && this.pnsMap[this.golongan]) {
              this.pangkat = this.pnsMap[this.golongan];
            }
          }
        }
      }
    }
  </script>

  <script>
    function fotoCropperComponent(initialPreview = null, initialFilename = null) {
      return {
        initialPreview: initialPreview,
        originalFilename: initialFilename,
        cleanUploadUrl: '{{ url('/rembg/clean-upload') }}',
        cleanEmployeeUrl: '{{ url('/rembg/clean-employee') }}',
          progressUrl: '{{ url('/rembg/progress') }}',
        cleanedUrl: '',
        cleaning: false,
        cleanError: null,
        // progress UI
        progressKey: null,
        progressPercent: 0,
        progressMessage: null,
        progressPolling: null,
        showProgress: false,
        progressUrl: '{{ url('/rembg/progress') }}',
        cropper: null,
        showCropper: false,
        croppedPreview: '',

        onFileChange(event) {
          const [file] = event.target.files || [];
          if (!file) return;

          if (!file.type.startsWith('image/')) {
            alert('File harus berupa gambar.');
            event.target.value = '';
            this.clearCropData();
            return;
          }

          const reader = new FileReader();
          reader.onload = (e) => {
            this.showCropper = true;
            this.croppedPreview = '';
            this.clearCropData();

            this.$nextTick(() => {
              const img = this.$refs.cropperImage;
              img.src = e.target.result;

              if (this.cropper) {
                this.cropper.destroy();
              }

              this.cropper = new Cropper(img, {
                aspectRatio: {{ $photoWidth }} / {{ $photoHeight }},
                viewMode: 1,
                autoCropArea: 1,
                dragMode: 'move',
              });
            });
          };
          reader.readAsDataURL(file);
        },

        setCropData(data) {
          if (!data) return;
          const setVal = (refName, value) => {
            if (this.$refs[refName]) {
              this.$refs[refName].value = value ?? '';
            }
          };

          setVal('cropX',      data.x);
          setVal('cropY',      data.y);
          setVal('cropWidth',  data.width);
          setVal('cropHeight', data.height);
        },

        clearCropData() {
          ['cropX', 'cropY', 'cropWidth', 'cropHeight'].forEach(refName => {
            if (this.$refs[refName]) {
              this.$refs[refName].value = '';
            }
          });
        },

        async applyCrop() {
          if (!this.cropper) return;

          const data = this.cropper.getData(true);
          this.setCropData(data);

          const canvas = this.cropper.getCroppedCanvas({
            width:  {{ $photoWidth }},
            height: {{ $photoHeight }},
          });

          // Produce PNG to align with server-side decoder and filename
          this.croppedPreview = canvas.toDataURL('image/png');

          this.showCropper = false;
          this.cropper.destroy();
          this.cropper = null;

          // Auto-trigger background cleaning on applied crop
          await this.cleanBg();
        },

        cancelCrop() {
          this.showCropper = false;
          this.croppedPreview = '';
          this.clearCropData();

          if (this.cropper) {
            this.cropper.destroy();
            this.cropper = null;
          }
        }

        ,
        async cleanBg() {
          this.cleaning = true;
          this.cleanError = null;
          this.progressPercent = 0; this.progressMessage = 'starting'; this.showProgress = true;
          const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
          try {
            if (this.croppedPreview) {
              const blob = await (await fetch(this.croppedPreview)).blob();
              const fd = new FormData();
              fd.append('image', blob, 'preview.png');

              await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', this.cleanUploadUrl, true);
                xhr.withCredentials = true;
                xhr.setRequestHeader('X-CSRF-TOKEN', token);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.onprogress = (ev) => {
                  if (ev.lengthComputable) {
                    const up = Math.round((ev.loaded / ev.total) * 100);
                    // map upload progress to 0-70%
                    this.progressPercent = Math.min(70, Math.round(up * 0.7));
                    this.progressMessage = 'uploading';
                  }
                };
                xhr.onload = () => {
                  try {
                    const json = JSON.parse(xhr.responseText || '{}');
                    if (!json.ok) { throw new Error(json.error || 'processing_failed'); }
                    this.cleanedUrl = json.url;
                    const rel = json.path || null;
                    if (rel && this.$refs.fotoPath) this.$refs.fotoPath.value = rel;
                    this.progressPercent = 100; this.progressMessage = 'done';
                    if (window.showToast) window.showToast('success', 'Pembersihan background berhasil');
                    resolve(json);
                  } catch (e) { reject(e); }
                };
                xhr.onerror = () => reject(new Error('network'));
                xhr.send(fd);
              });
              return;
            }

            if (this.originalFilename) {
              const fd = new FormData();
              const base = (this.originalFilename || '').split('/').pop();
              fd.append('filename', base);

              const xhr = new XMLHttpRequest();
              xhr.open('POST', this.cleanEmployeeUrl, true);
              xhr.withCredentials = true;
              xhr.setRequestHeader('X-CSRF-TOKEN', token);
              xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
              xhr.onload = () => {
                try {
                  const json = JSON.parse(xhr.responseText || '{}');
                  if (!json.ok) throw new Error(json.error || 'processing_failed');
                  this.cleanedUrl = json.url;
                  const u = new URL(this.cleanedUrl, window.location.origin);
                  let p = u.pathname || '';
                  if (p.startsWith('/')) p = p.slice(1);
                  if (this.$refs.fotoPath) this.$refs.fotoPath.value = p;
                  this.progressPercent = 100; this.progressMessage = 'done';
                  if (window.showToast) window.showToast('success', 'Pembersihan background berhasil');
                } catch (e) { this.cleanError = e.message || 'Network error'; }
              };
              xhr.onerror = () => { this.cleanError = 'Network error'; if (window.showToast) window.showToast('error', this.cleanError); };
              xhr.send(fd);
              return;
            }

            this.cleanError = 'Tidak ada foto sumber';
            this.cleanedUrl = '';
          } catch (e) {
            this.cleanError = e.message || 'Network error';
            if (window.showToast) window.showToast('error', this.cleanError);
          } finally {
            this.cleaning = false;
            setTimeout(() => { this.showProgress = false; }, 400);
          }
        }

        // no-op: progress polling removed to preserve cropper stability
      }
    }
  </script>

  <script>
    // extend fotoCropperComponent with background-clean action
    document.addEventListener('alpine:init', () => {
      // nothing global — component has its own method below
    });
  </script>
@endpush
