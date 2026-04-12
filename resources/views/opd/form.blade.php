@php($editing = $opd->exists)
<form method="post" enctype="multipart/form-data"
      action="{{ $editing ? route('opd.update', $opd) : route('opd.store') }}"
      class="space-y-6">
  @csrf
  @if($editing) @method('put') @endif

  <div class="grid md:grid-cols-2 gap-6">
    <div>
      <label class="block text-sm mb-2">Nama OPD <span class="text-rose-600">*</span></label>
      <input name="nama" value="{{ old('nama', $opd->nama) }}" required
             class="w-full h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-800/70 px-4 focus:ring-4 focus:ring-sky-200 dark:focus:ring-sky-900">
      @error('nama') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-sm mb-2">Pimpinan</label>
      <input name="pimpinan" value="{{ old('pimpinan', $opd->pimpinan) }}"
             class="w-full h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-800/70 px-4">
      @error('pimpinan') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
        <div>
      <label class="block text-sm mb-2">NIP</label>
      <input name="nip" value="{{ old('nip', $opd->nip) }}"
             class="w-full h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-800/70 px-4">
      @error('nip') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-sm mb-2">Pangkat</label>
      <input name="pangkat" value="{{ old('pangkat', $opd->pangkat) }}"
             class="w-full h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-800/70 px-4">
      @error('pangkat') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-sm mb-2">Golongan</label>
      <input name="golongan" value="{{ old('golongan', $opd->golongan) }}"
             class="w-full h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-800/70 px-4">
      @error('golongan') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-sm mb-2">Telepon</label>
      <input name="telepon" value="{{ old('telepon', $opd->telepon) }}"
             class="w-full h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-800/70 px-4">
      @error('telepon') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="block text-sm mb-2">Alamat</label>
      <input name="alamat" value="{{ old('alamat', $opd->alamat) }}"
             class="w-full h-11 rounded-xl border border-slate-300 dark:border-slate-700 bg-white/90 dark:bg-slate-800/70 px-4">
      @error('alamat') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
  </div>

  <div class="grid md:grid-cols-2 gap-6">
    <div>
      <label class="block text-sm mb-2">Tanda tangan pimpinan (PNG/JPG, maks 512KB)</label>
      <input type="file" name="ttd" accept="image/*"
             class="block w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-slate-900 file:text-white dark:file:bg-sky-700">
      @error('ttd') <p class="text-rose-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
    @if($editing && $opd->ttd_file_path)
      <div>
        <label class="block text-sm mb-2">Pratinjau saat ini</label>
        <img src="{{ asset($opd->ttd_file_path) }}" alt="TTD" class="h-24 rounded border border-slate-200 dark:border-slate-800">
      </div>
    @endif
  </div>

  <div class="flex gap-2">
    <button class="h-11 px-5 rounded-xl bg-slate-900 text-white dark:bg-sky-600">Simpan</button>
    <a href="{{ route('opd.index') }}"
       class="h-11 px-5 rounded-xl bg-slate-200 text-slate-800 dark:bg-slate-700 dark:text-slate-100">Batal</a>
  </div>
</form>
