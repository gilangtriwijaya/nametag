{{-- resources/views/employees/partials/table.blade.php --}}
<div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="sticky top-0 bg-slate-50 z-10 text-[10px] uppercase tracking-wide text-slate-600 dark:text-slate-300">
                <tr>
                    <th class="px-3 py-2 text-left w-[40px]">
                        <input type="checkbox" id="chkAll"
                               class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    </th>
                    <th class="px-3 py-2 text-left w-[260px]">Nama / NIP / Jabatan</th>
                    <th class="px-3 py-2 text-left w-[260px]">OPD / Unit / Status</th>
                    <th class="px-3 py-2 text-left w-[220px] hidden sm:table-cell">QR Terakhir</th>
                    <th class="px-3 py-2 text-left w-[220px] hidden sm:table-cell">Nametag</th>
                    <th class="px-3 py-2 text-left w-[120px] sm:hidden">Detail</th>
                    <th class="px-3 py-2 text-right w-[130px]">Aksi</th>
                </tr>
            </thead>
            <tbody id="employeesTbody" class="divide-y divide-slate-200 dark:divide-slate-700">
                @include('employees._table_rows')
            </tbody>
        </table>
    </div>

    {{-- Pagination footer --}}
    <div class="border-t border-slate-200 dark:border-slate-700 px-4 py-4 bg-slate-50 dark:bg-slate-900/50">
        @include('employees.partials._pagination')
    </div>
</div>
