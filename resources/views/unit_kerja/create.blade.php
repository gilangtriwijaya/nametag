<x-layouts.admin :title="'Tambah Unit Kerja'">
  <x-slot:header>
    <div>
      <h1 class="text-2xl font-semibold">Tambah Unit Kerja</h1>
    </div>
  </x-slot:header>

  <form method="POST" action="{{ route('unit-kerja.store') }}" class="max-w-2xl">
    @csrf
    <div class="grid grid-cols-1 gap-3">
      <label>Nama</label>
      <input name="nama" required class="w-full rounded border px-2 py-2" />

      <label>OPD (opsional)</label>
      <select name="opd_id" class="w-full rounded border px-2 py-2">
        <option value="">—</option>
        @foreach(\App\Models\Opd::orderBy('nama')->get() as $opd)
          <option value="{{ $opd->id }}">{{ $opd->nama }}</option>
        @endforeach
      </select>

      <label>Alamat</label>
      <input name="alamat" class="w-full rounded border px-2 py-2" />

      <div class="flex gap-2 justify-end">
        <a href="{{ route('unit-kerja.index') }}" class="px-3 py-2 border rounded">Batal</a>
        <button class="px-4 py-2 bg-emerald-600 text-white rounded">Simpan</button>
      </div>
    </div>
  </form>
</x-layouts.admin>
