<x-layouts.admin :title="'Preview Import Pegawai'">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Preview Import Pegawai</h1>
            <p class="text-sm text-slate-500">Periksa data sebelum konfirmasi.</p>
        </div>
    </x-slot:header>

    <div class="container mx-auto">
        <div class="mb-4">Valid: <strong>{{ $preview['summary']['valid'] }}</strong> — Invalid: <strong>{{ $preview['summary']['invalid'] }}</strong></div>

        <form action="{{ route('employees.import.confirm') }}" method="post">
            @csrf
            <input type="hidden" name="preview_id" value="{{ $preview_id }}">
            <button class="inline-flex items-center px-4 py-2 rounded bg-emerald-600 text-white mb-3">Konfirmasi dan Simpan</button>
        </form>

        <div class="mb-3 text-sm text-slate-700">Catatan: Kolom <strong>opd</strong> (nama OPD) atau <strong>opd_sso_id</strong> harus ada agar baris dapat disimpan. Jika tidak ada, baris akan dilewati.</div>

        <div class="flex gap-2 items-center mb-4">
            <form action="{{ route('employees.import.preview.rerun', ['id' => $preview_id]) }}" method="post" style="display:inline;">
                @csrf
                <button class="inline-flex items-center px-3 py-2 rounded border">Jalankan Ulang Preview</button>
            </form>
            <a href="{{ route('employees.import.show') }}" class="text-sm text-slate-600">Upload Ulang</a>
            <small class="text-muted">Preview ID: {{ $preview_id }}</small>
        </div>

        <h3 class="font-medium mb-2">Baris</h3>
        <div class="overflow-x-auto">
        @php
            // collect all column keys present in preview rows
            $columns = [];
            foreach (($preview['rows'] ?? []) as $r) {
                if (!empty($r['data']) && is_array($r['data'])) {
                    foreach ($r['data'] as $k => $v) {
                        if (!in_array($k, $columns)) {
                            $columns[] = $k;
                        }
                    }
                }
            }
        @endphp

        <table class="w-full text-sm border">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-2 py-1 text-left">Baris</th>
                    @foreach($columns as $col)
                        <th class="px-2 py-1 text-left">{{ str_replace('_',' ', ucfirst($col)) }}</th>
                    @endforeach
                    <th class="px-2 py-1 text-left">Errors</th>
                </tr>
            </thead>
            <tbody>
                @foreach($preview['rows'] as $row)
                    <tr class="@if(!empty($row['errors'])) bg-red-50 @endif">
                        <td class="px-2 py-1 align-top">{{ $row['row'] }}</td>
                        @foreach($columns as $col)
                            <td class="px-2 py-1 align-top">{{ isset($row['data'][$col]) ? $row['data'][$col] : '' }}</td>
                        @endforeach
                        <td class="px-2 py-1 align-top">
                            @if(!empty($row['errors']))
                                <ul class="mb-0 list-disc pl-5 text-sm text-red-700">
                                    @foreach($row['errors'] as $e)
                                        <li>{{ $e }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>

        <div class="mt-4">
            <a href="{{ route('employees.import.show') }}" class="text-slate-600">Kembali</a>
        </div>
    </div>
</x-layouts.admin>
