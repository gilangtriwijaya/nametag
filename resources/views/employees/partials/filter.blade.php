{{-- resources/views/employees/partials/filter.blade.php --}}
<div class="mb-3 grid gap-2 md:grid-cols-6 items-end">
    <div class="md:col-span-2">
        <label class="block mb-1 text-xs font-semibold text-slate-600">Pencarian</label>
        <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Nama, NIP, jabatan, unit…"
               class="w-full h-9 rounded-lg border border-slate-300 bg-white/90 px-3 text-[13px]
                      shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
    </div>
    <div>
        <label class="block mb-1 text-xs font-semibold text-slate-600">OPD</label>
        <select name="opd_id" id="filterOpd"
                class="w-full h-9 rounded-lg border border-slate-300 bg-white/90 px-3 text-[13px]
                       shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
            <option value="">Semua</option>
            @foreach($opds as $opd)
                <option value="{{ $opd->id }}" @selected((string)($opd_id ?? '') === (string)$opd->id)>{{ $opd->nama }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block mb-1 text-xs font-semibold text-slate-600">Unit OPD</label>
        <select name="opd_unit_id" id="filterUnit"
                class="w-full h-9 rounded-lg border border-slate-300 bg-white/90 px-3 text-[13px]
                       shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
            <option value="">Semua</option>
            <option value="__parent_only__" @selected(($opd_parent_only ?? 0) == 1)>📌 Hanya OPD Induk</option>
            @if($opd_id && isset($opdUnits[(string)$opd_id]))
                @foreach($opdUnits[(string)$opd_id] as $unit)
                    <option value="{{ $unit['id'] }}" @selected((string)($opd_unit_id ?? '') === (string)$unit['id'])>{{ $unit['nama'] }}</option>
                @endforeach
            @endif
        </select>
        <input type="hidden" name="opd_parent_only" value="0" id="hiddenOpdParentOnly">
    </div>

    <div>
        <label class="block mb-1 text-xs font-semibold text-slate-600">Unit Kerja</label>
        <select name="unit_kerja_id" id="filterUnitKerja"
                class="w-full h-9 rounded-lg border border-slate-300 bg-white/90 px-3 text-[13px]
                       shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
            <option value="">Semua</option>
            @if($opd_id && isset($unitKerjas[(string)$opd_id]))
                @foreach($unitKerjas[(string)$opd_id] as $uk)
                    <option value="{{ $uk['id'] }}" @selected((string)($unit_kerja_id ?? '') === (string)$uk['id'])>{{ $uk['nama'] }}</option>
                @endforeach
            @endif
        </select>
    </div>

    {{-- Status --}}
    <div class="md:col-span-1">
        <label class="block mb-1 text-xs font-semibold text-slate-600">Status</label>
        <select name="status" class="w-full h-9 rounded-lg border border-slate-300 bg-white/90 px-3 text-[13px] shadow-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-200">
            <option value="">Semua</option>
            <option value="AKTIF" @selected(($status ?? '') === 'AKTIF')>AKTIF</option>
            <option value="NONAKTIF" @selected(($status ?? '') === 'NONAKTIF')>NONAKTIF</option>
        </select>
    </div>

    {{-- Buttons --}}
    <div class="md:col-span-0">
        <div class="flex items-center justify-start gap-2">
            <button type="submit" class="h-9 px-3 rounded-lg bg-sky-50 text-sky-700 border border-sky-200 text-sm font-semibold">Filter</button>
            <button type="button" id="resetFilters" class="h-9 px-3 rounded-lg bg-white text-sm border border-slate-200">Reset</button>
        </div>
    </div>

</div>

{{-- Batch UI is moved to a single dedicated partial (see batch-ui). Inline progress removed. --}}
