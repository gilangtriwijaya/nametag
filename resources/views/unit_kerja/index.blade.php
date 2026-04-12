<x-layouts.admin :title="'Unit Kerja – Anambas-ID'">
  <x-slot:header>
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Daftar Unit Kerja</h1>
        <p class="text-slate-500 dark:text-slate-400">Kelola entitas Unit Kerja terpisah.</p>
      </div>

      <div>
        <a href="{{ route('unit-kerja.create') }}" class="inline-flex items-center h-10 px-4 rounded-lg bg-brand-600 text-white">Tambah Unit Kerja</a>
      </div>
    </div>
  </x-slot:header>

  <div class="mb-4">
    <form method="GET" class="flex gap-2">
      <input name="q" value="{{ $q ?? '' }}" placeholder="Cari nama unit…" class="rounded border px-3 py-2 w-full" />
      <button class="px-4 rounded bg-slate-800 text-white">Cari</button>
    </form>
  </div>

  <div class="rounded-2xl bg-white p-4">
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-slate-600">
            <th class="px-3 py-2">Nama</th>
            <th class="px-3 py-2 hidden lg:table-cell">OPD</th>
            <th class="px-3 py-2">Status</th>
            <th class="px-3 py-2 text-right">Aksi</th>
          </tr>
        </thead>
        <tbody class="divide-y">
          @forelse($units as $u)
            <tr>
              <td class="px-3 py-2 font-medium">{{ $u->nama }}</td>
              <td class="px-3 py-2 hidden lg:table-cell">{{ optional($u->opd)->nama ?? '—' }}</td>
              <td class="px-3 py-2">{{ $u->status }}</td>
              <td class="px-3 py-2 text-right">
                <a href="{{ route('unit-kerja.edit', $u) }}" class="mr-2 text-amber-600">Ubah</a>
                <form method="POST" action="{{ route('unit-kerja.destroy', $u) }}" style="display:inline" onsubmit="return confirm('Hapus unit kerja?')">
                  @csrf @method('DELETE')
                  <button class="text-rose-600">Hapus</button>
                </form>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">Belum ada unit kerja.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-4">{{ $units->withQueryString()->links() }}</div>
  </div>
</x-layouts.admin>
