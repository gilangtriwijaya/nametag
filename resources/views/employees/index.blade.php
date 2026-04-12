{{-- resources/views/employees/index.blade.php --}}
<x-layouts.admin>
    @php
        // Hanya role global yang boleh lihat tombol import
        $canImport = auth()->user()
            ? (auth()->user()->hasRole('superadmin')
                || auth()->user()->hasAnyRole(['org_admin', 'admin-organisasi', 'admin_organisasi', 'admin organisasi', 'admin bagian organisasi'])
                || auth()->user()->hasAnyRole(['verifikator global', 'verifikator-global', 'verifikator_global'])
            )
            : false;
    @endphp

    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-lg font-semibold text-slate-800">Pegawai</h1>

        <div class="flex items-center gap-2">
            {{-- + Pegawai --}}
            <a href="{{ route('employees.create') }}"
            class="inline-flex items-center h-9 px-4 rounded-lg
                    bg-brand-600 text-white
                    hover:bg-brand-700
                    shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 mr-2"
                    viewBox="0 0 24 24"
                    fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 5v14m7-7H5"/>
                </svg>
                <span class="text-sm font-medium">Pegawai</span>
            </a>

            {{-- Import (hanya untuk role global) --}}
            @if ($canImport)
            <a href="{{ route('employees.import.show') }}"
            class="inline-flex items-center h-9 px-3 rounded-lg
                    border border-slate-200
                    bg-white text-slate-700
                    hover:bg-slate-50
                    transition">
                <svg xmlns="http://www.w3.org/2000/svg"
                    class="h-4 w-4 mr-2 text-slate-500"
                    viewBox="0 0 24 24"
                    fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 16V4m0 0l-4 4m4-4l4 4M4 20h16"/>
                </svg>
                <span class="text-sm">Import</span>
            </a>
            @endif

            {{-- Group: Proses Massal + Unduh --}}
            <div class="inline-flex rounded-lg overflow-hidden border border-slate-200 bg-white">
                {{-- Proses Massal --}}
                <button id="generateBatchBtn"
                        type="button"
                        class="inline-flex items-center h-9 px-4
                            bg-slate-700 text-white
                            hover:bg-slate-800
                            transition">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4 mr-2"
                        viewBox="0 0 24 24"
                        fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="text-sm font-medium">Proses Massal</span>
                </button>

                {{-- Divider --}}
                <div class="w-px bg-slate-200"></div>

                {{-- Unduh --}}
                <button id="downloadSelected"
                        type="button"
                        class="inline-flex items-center h-9 px-3
                            text-slate-700
                            hover:bg-slate-50
                            transition">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4 mr-2 text-slate-500"
                        viewBox="0 0 24 24"
                        fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/>
                    </svg>
                    <span class="text-sm">Unduh</span>
                </button>
            </div>
        </div>
    </div>


    {{-- Filters form (GET) --}}
    <form id="filtersForm" method="GET" class="mb-4">
        @include('employees.partials.filter')
    </form>

    {{-- Inline batch UI --}}
    @include('employees.partials.batch-ui')

    {{-- Batch form + table --}}
    <form id="batchForm" method="POST" action="{{ route('nametag.batch.dispatch') }}">
        @csrf
        <div class="mb-3">
            <div id="batchInfo" class="text-sm text-slate-600">Tidak ada pegawai yang dipilih.</div>
        </div>

        <div id="employeesContainer">
            <div id="employeesLoading" class="hidden p-6 text-center text-sm text-slate-500">Memuat...</div>
            @include('employees.partials.table')
        </div>

        <div id="employeesPagination" class="mt-3 text-sm"></div>
    </form>

    {{-- Image preview modal --}}
    <div id="imgPreviewModal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/70 p-4" onclick="if (event.target.id === 'imgPreviewModal') closeImgPreview();">
        <img id="imgPreviewTarget" src="" alt="Preview" class="max-h-[90vh] max-w-[90vw] rounded shadow-2xl bg-white">
    </div>

    {{-- Force delete confirmation modal --}}
    <div id="forceDeleteModal" class="fixed inset-0 z-[110] hidden items-center justify-center bg-black/40 p-4">
        <div class="bg-white rounded shadow-lg w-full max-w-md p-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold">Konfirmasi Hapus</h3>
                <button id="forceDeleteModalClose" class="text-slate-500">✕</button>
            </div>
            <div id="forceDeleteModalBody" class="mt-3 text-sm text-slate-700">Hapus pegawai?</div>
            <div class="mt-4 flex justify-end gap-2">
                <button id="forceDeleteCancel" class="px-3 py-1 rounded border">Batal</button>
                <button id="forceDeleteConfirm" class="px-3 py-1 rounded bg-rose-600 text-white">Hapus</button>
            </div>
        </div>
    </div>

    <script>
        window.EMP_INDEX_CONFIG = {
            initialFilters: {!! json_encode([ 'q' => $q ?? '', 'status' => $status ?? '', 'opd_id' => $opd_id ?? '', 'opd_unit_id' => $opd_unit_id ?? '', 'unit_kerja_id' => $unit_kerja_id ?? '' ]) !!},
            opdUnits: {!! json_encode($opdUnits) !!},
            unitKerjas: {!! json_encode($unitKerjas ?? []) !!},
            urls: {
                batchEmployeeStatus: '{{ url('/nametag/batch/employee-status') }}',
                opdUnits: '{{ url('employees/opd-units') }}',
                resetFilters: '{{ route('employees.filters.reset') }}',
                batchRetryFailed: '{{ url('/nametag/batch/retry-failed') }}',
                nametagBatchQueued: '{{ route('nametag.batch.queued') }}',
                nametagBatchDispatch: '{{ route('nametag.batch.dispatch') }}',
                nametagBatchDownload: '{{ route('nametag.batch.download') }}'
            }
        };
        console.log('EMP_INDEX_CONFIG.opdUnits loaded:', window.EMP_INDEX_CONFIG.opdUnits);
        console.log('Available OPD keys in opdUnits:', Object.keys(window.EMP_INDEX_CONFIG.opdUnits));
    </script>
    <script>
        // image preview helpers for index page (used by Preview buttons)
        function openImgPreview(src) {
            if (!src) return;
            const m = document.getElementById('imgPreviewModal');
            const i = document.getElementById('imgPreviewTarget');
            if (!m || !i) return;
            i.src = src;
            m.classList.remove('hidden');
            m.classList.add('flex');
            document.addEventListener('keydown', escCloseImg);
        }
        function closeImgPreview() {
            const m = document.getElementById('imgPreviewModal');
            const i = document.getElementById('imgPreviewTarget');
            if (!m || !i) return;
            i.src = '';
            m.classList.add('hidden');
            m.classList.remove('flex');
            document.removeEventListener('keydown', escCloseImg);
        }
        function escCloseImg(e) { if (e.key === 'Escape') closeImgPreview(); }
        // attach click-to-close on backdrop (already set inline on modal element), and close button if present
        try { document.getElementById('imgPreviewModal')?.addEventListener('click', function(e){ if (e.target.id === 'imgPreviewModal') closeImgPreview(); }); } catch(e){}
    </script>
    <script>
// inlined employees-index.js (restored pre-refactor)
(function(){
    // Expect a global config: window.EMP_INDEX_CONFIG
    const cfg = window.EMP_INDEX_CONFIG || {};
    const urls = cfg.urls || {};
    const initialFilters = cfg.initialFilters || {};
    const opdUnits = cfg.opdUnits || {};

    function ensureContainer() {
        let c = document.getElementById('globalToastContainer');
        if (!c) {
            c = document.createElement('div');
            c.id = 'globalToastContainer';
            c.style.position = 'fixed';
            c.style.right = '1rem';
            c.style.top = '1rem';
            c.style.zIndex = '9999';
            document.body.appendChild(c);
        }
        return c;
    }

    if (!window.showToast) {
        window.showToast = function (type, message, opts = {}) {
            try {
                const c = ensureContainer();
                const el = document.createElement('div');
                el.className = 'mb-2 rounded px-3 py-2 text-sm shadow';
                el.style.minWidth = '220px';
                el.style.opacity = '0';
                el.style.transition = 'opacity .25s ease, transform .25s ease';
                el.style.transform = 'translateY(-6px)';
                if (type === 'success') {
                    el.style.background = '#ecfdf5';
                    el.style.color = '#065f46';
                    el.style.border = '1px solid #bbf7d0';
                } else if (type === 'error') {
                    el.style.background = '#fff1f2';
                    el.style.color = '#7f1d1d';
                    el.style.border = '1px solid #fecaca';
                } else {
                    el.style.background = '#eff6ff';
                    el.style.color = '#1e40af';
                    el.style.border = '1px solid #bfdbfe';
                }
                el.innerText = message || '';
                c.appendChild(el);
                requestAnimationFrame(() => { el.style.opacity = '1'; el.style.transform = 'translateY(0)'; });
                const ms = opts.duration || 4000;
                setTimeout(() => {
                    el.style.opacity = '0'; el.style.transform = 'translateY(-6px)';
                    setTimeout(() => el.remove(), 300);
                }, ms);
                return el;
            } catch (e) { console.warn('toast fallback failed', e); }
        };
    }

    // Global delete handler - uses custom modal dialog
    window.deleteEmployee = async function(empId) {
        console.log('[Delete] Handler called with empId:', empId);
        
        if (!empId) {
            console.error('[Delete] No empId');
            return;
        }
        
        // Show custom modal instead of native confirm
        const modal = document.getElementById('forceDeleteModal');
        if (!modal) {
            console.error('[Delete] Modal not found');
            return;
        }
        
        // Store delete function for modal confirm button
        window._pendingDelete = async function() {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrf) {
                console.error('[Delete] CSRF token not found');
                return false;
            }
            
            const url = window.location.origin + '/anambas-id/employees/' + empId + '/force-delete';
            console.log('[Delete] URL:', url);
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                console.log('[Delete] Response:', response.status);
                
                if (response.ok) {
                    window.showToast?.('success', 'Data berhasil dihapus.');
                    // Refresh table immediately
                    setTimeout(() => {
                        window.nametag_fetchEmployees?.();
                    }, 100);
                    return true;
                } else {
                    const data = await response.json().catch(() => ({}));
                    window.showToast?.('error', data.message || 'Gagal menghapus.');
                    return false;
                }
            } catch(e) {
                console.error('[Delete] Error:', e);
                window.showToast?.('error', 'Error: ' + e.message);
                return false;
            }
        };
        
        // Show the modal
        const modalBody = document.getElementById('forceDeleteModalBody');
        if (modalBody) {
            modalBody.textContent = `Hapus pegawai ini secara permanen?`;
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };
    console.log('[Init] window.deleteEmployee ready');

    // Add direct event listener to all delete buttons
    // (backup for inline onclick, ensures we capture all clicks)
    function attachDeleteHandlers() {
        document.querySelectorAll('.js-delete-employee').forEach(btn => {
            btn.removeEventListener('click', handleDeleteClick);
            btn.addEventListener('click', handleDeleteClick);
        });
    }
    
    function handleDeleteClick(e) {
        e.preventDefault();
        e.stopPropagation();
        const empId = this.dataset.empId || this.getAttribute('data-emp-id');
        console.log('[Delete Button] Clicked, empId from data attr:', empId);
        if (empId) {
            window.deleteEmployee(empId);
        }
    }
    
    // Attach on page load
    const DOMcontent = document.readyState === 'loading' 
        ? document.addEventListener('DOMContentLoaded', attachDeleteHandlers)
        : attachDeleteHandlers();

    // Main module
    document.addEventListener('DOMContentLoaded', function(){
        const STORAGE_KEY = 'employees_filters_v1';
        const form = document.getElementById('filtersForm');
        if (!form) return;
        const inputQ = form.querySelector('input[name="q"]');
        const selectStatus = form.querySelector('select[name="status"]');
        const selectOpd = document.getElementById('filterOpd');
        const selectUnit = document.getElementById('filterUnit');
        const selectUnitKerja = document.getElementById('filterUnitKerja');
        const hiddenOpdParentOnly = document.getElementById('hiddenOpdParentOnly');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        console.log('Filter initialized - OPD options:', selectOpd.options.length, selectOpd.innerHTML.substring(0, 200));

        function populateUnits(opdId, selectedUnit) {
            console.log('populateUnits called with opdId:', opdId, 'selectedUnit:', selectedUnit);
            
            selectUnit.innerHTML = '';
            const optAll = document.createElement('option');
            optAll.value = '';
            optAll.textContent = 'Semua';
            selectUnit.appendChild(optAll);
            
            // Add special "Hanya OPD Induk" option
            const optParent = document.createElement('option');
            optParent.value = '__parent_only__';
            optParent.textContent = '📌 Hanya OPD Induk';
            selectUnit.appendChild(optParent);
            
            if (!opdId) return;
            const list = (window.EMP_INDEX_CONFIG.opdUnits || {})[opdId] || [];
            console.log('Units found for OPD', opdId, ':', list);
            if (list.length) {
                list.forEach(u => {
                    const o = document.createElement('option');
                    o.value = u.id;
                    o.textContent = u.nama;
                    if (selectedUnit && String(selectedUnit) === String(u.id)) o.selected = true;
                    selectUnit.appendChild(o);
                });
                return;
            }
            if (!window.EMP_INDEX_CONFIG.urls.opdUnits) return;
            fetch(window.EMP_INDEX_CONFIG.urls.opdUnits + '/' + opdId)
                .then(r => r.ok ? r.json() : [])
                .then(data => {
                    (data || []).forEach(u => {
                        const o = document.createElement('option');
                        o.value = u.id;
                        o.textContent = u.nama;
                        if (selectedUnit && String(selectedUnit) === String(u.id)) o.selected = true;
                        selectUnit.appendChild(o);
                    });
                })
                .catch(() => {});
        }

        function loadSaved() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return;
                const s = JSON.parse(raw);
                if (s.q && inputQ) inputQ.value = s.q;
                if (s.status && selectStatus) selectStatus.value = s.status;
                if (s.opd_id && selectOpd) selectOpd.value = s.opd_id;
                
                // Handle opd_parent_only - select "__parent_only__" option in Unit OPD
                const hiddenOpdParentOnly = document.getElementById('hiddenOpdParentOnly');
                if (s.opd_parent_only) {
                    if (hiddenOpdParentOnly) hiddenOpdParentOnly.value = '1';
                } else {
                    if (hiddenOpdParentOnly) hiddenOpdParentOnly.value = '0';
                }
                
                populateUnits(selectOpd.value || s.opd_id, s.opd_unit_id || null);
                populateUnitKerjas(selectOpd.value || s.opd_id, s.unit_kerja_id || null);
                
                // If opd_parent_only is set, select the special option
                if (s.opd_parent_only && selectUnit) {
                    console.log('[Filter Load] Setting Hanya OPD Induk from saved filters');
                    selectUnit.value = '__parent_only__';
                } else if (s.opd_unit_id && selectUnit) {
                    selectUnit.value = s.opd_unit_id;
                }
                
                if (s.unit_kerja_id && selectUnitKerja) selectUnitKerja.value = s.unit_kerja_id;
                
                console.log('[Filter Load] Restored - Unit OPD value:', selectUnit.value);
            } catch (e) { console.warn('Could not load saved filters', e); }
        }

        (function saveFromUrl() {
            try {
                const params = new URLSearchParams(window.location.search);
                const has = ['q', 'status', 'opd_id', 'opd_unit_id', 'unit_kerja_id', 'opd_parent_only'].some(k => params.has(k) && params.get(k) !== null && params.get(k) !== '');
                if (!has) return;
                const p = {
                    q: params.get('q') || '',
                    status: params.get('status') || '',
                    opd_id: params.get('opd_id') || '',
                    opd_unit_id: params.get('opd_unit_id') || '',
                    unit_kerja_id: params.get('unit_kerja_id') || '',
                    opd_parent_only: params.get('opd_parent_only') || 0,
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(p));
            } catch (e) {}
        })();

        function saveNow() {
            // Determine if "Hanya OPD Induk" is selected
            const isOpdParentOnly = selectUnit.value === '__parent_only__' || (hiddenOpdParentOnly && hiddenOpdParentOnly.value === '1');
            
            const payload = {
                q: inputQ ? inputQ.value : '',
                status: selectStatus ? selectStatus.value : '',
                opd_id: selectOpd ? selectOpd.value : '',
                opd_unit_id: isOpdParentOnly ? '' : (selectUnit ? selectUnit.value : ''),
                unit_kerja_id: selectUnitKerja ? selectUnitKerja.value : '',
                opd_parent_only: isOpdParentOnly ? 1 : 0,
            };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        }

        function populateUnitKerjas(opdId, selectedUnitKerja) {
            if (!selectUnitKerja) return;
            selectUnitKerja.innerHTML = '';
            const optAll = document.createElement('option');
            optAll.value = '';
            optAll.textContent = 'Semua';
            selectUnitKerja.appendChild(optAll);
            if (!opdId) return;
            const list = (window.EMP_INDEX_CONFIG.unitKerjas || {})[opdId] || [];
            if (list.length) {
                list.forEach(u => {
                    const o = document.createElement('option');
                    o.value = u.id; o.textContent = u.nama;
                    if (selectedUnitKerja && String(selectedUnitKerja) === String(u.id)) o.selected = true;
                    selectUnitKerja.appendChild(o);
                });
            }
        }

        selectOpd.addEventListener('change', () => { 
            console.log('OPD changed - new value:', selectOpd.value);
            // When OPD changes, always reset unit dropdown and repopulate
            // The user explicitly changed OPD, so clear unit context
            selectUnit.value = '';
            populateUnits(selectOpd.value, null); 
            populateUnitKerjas(selectOpd.value, null);
            
            // Reset hidden value when OPD changes
            if (hiddenOpdParentOnly) hiddenOpdParentOnly.value = '0';
            console.log('[OPD Change] Reset unit dropdown and parent-only flag');
        });
        
        selectUnit.addEventListener('change', () => {
            console.log('Unit OPD changed:', selectUnit.value);
            
            // Handle "Hanya OPD Induk" special option
            const hiddenOpdParentOnly = document.getElementById('hiddenOpdParentOnly');
            if (selectUnit.value === '__parent_only__') {
                // Special option selected - set hidden checkbox
                if (hiddenOpdParentOnly) hiddenOpdParentOnly.value = '1';
                // NOTE: Keep selectUnit.value as '__parent_only__' so user sees their selection
            } else {
                // Normal unit or "Semua" selected - uncheck hidden checkbox
                if (hiddenOpdParentOnly) hiddenOpdParentOnly.value = '0';
            }
            
            populateUnitKerjas(selectOpd.value, null);
            
            // Save the filter state immediately when user changes unit
            saveNow();
            console.log('[Unit Change] Saved filters - hiddenOpdParentOnly:', hiddenOpdParentOnly?.value);
        });

        // master checkbox: select/deselect all visible rows
        // NEW: Also handle "select all filtered" via session
        if (!window.selectAllFilteredState) {
            window.selectAllFilteredState = null;  // Global: accessible from other scopes
        }
        
        const chkAll = document.getElementById('chkAll');
        if (chkAll) {
            chkAll.addEventListener('change', async () => {
                console.log('[chkAll] Changed to:', chkAll.checked);
                
                if (!chkAll.checked) {
                    // Unchecking: deselect visible + clear session state
                    console.log('[chkAll] Unchecking - deselect all');
                    const rows = Array.from(document.querySelectorAll('.chkRow'));
                    rows.forEach(r => { if (!r.disabled) r.checked = false; });
                    
                    // Clear session state on backend
                    if (window.selectAllFilteredState) {
                        try {
                            console.log('[chkAll] Clearing session state');
                            await fetch('{{ route('employees.clear_select_all') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': document.querySelector('[name="_token"]').value,
                                }
                            });
                        } catch (e) { console.warn('Clear select-all failed:', e); }
                        window.selectAllFilteredState = null;
                    }
                } else {
                    // Checking: select all filtered employees via session
                    console.log('[chkAll] Checking - select all filtered');
                    const formData = new FormData(form);
                    const filterParams = new URLSearchParams(formData);
                    console.log('[chkAll] Filter params:', filterParams.toString());
                    
                    try {
                        const url = '{{ route('employees.select_all_filtered') }}?' + filterParams.toString();
                        console.log('[chkAll] Fetching URL:', url);
                        
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': document.querySelector('[name="_token"]').value,
                            }
                        });
                        
                        console.log('[chkAll] Response status:', response.status);
                        
                        if (!response.ok) throw new Error('Network response failed: ' + response.status);
                        
                        const data = await response.json();
                        console.log('[chkAll] Response data:', data);
                        
                        if (data.ok) {
                            window.selectAllFilteredState = {
                                total: data.total_count,
                                ids: data.ids,
                                filters: filterParams.toString()
                            };
                            console.log('[chkAll] Selected:', data.total_count, 'employees, IDs count:', data.ids.length);
                            
                            // Also check visible rows for immediate UI feedback
                            const rows = Array.from(document.querySelectorAll('.chkRow'));
                            console.log('[chkAll] Found', rows.length, 'visible rows - checking all');
                            rows.forEach(r => { if (!r.disabled) r.checked = true; });
                        } else {
                            console.warn('selectAllFiltered failed:', data.message);
                            chkAll.checked = false;
                        }
                    } catch (e) {
                        console.error('selectAllFiltered error:', e);
                        chkAll.checked = false;
                    }
                }
                
                refreshBatchInfo();
            });
        }

        form.addEventListener('submit', (e) => {
            // CRITICAL: Sync hidden field BEFORE form submission
            const isOpdParentOnly = selectUnit.value === '__parent_only__' || (hiddenOpdParentOnly && hiddenOpdParentOnly.value === '1');
            if (hiddenOpdParentOnly) {
                hiddenOpdParentOnly.value = isOpdParentOnly ? '1' : '0';
                console.log('[Form Submit] Synced hiddenOpdParentOnly to:', hiddenOpdParentOnly.value);
            }
            
            saveNow();
            e.preventDefault();
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            const url = `${window.location.pathname}?${params.toString()}`;
            console.log('Filter submit - URL:', url);
            console.log('Form data:', Object.fromEntries(formData));
            console.log('[Form Submit] Final check - opd_unit_id:', params.get('opd_unit_id'), 'opd_parent_only:', params.get('opd_parent_only'));
            fetchEmployees(url);
        });

        document.addEventListener('click', function (ev) {
            const a = ev.target.closest && ev.target.closest('a');
            if (!a) return;
            const parent = a.closest && a.closest('#employeesPagination');
            if (!parent) return;
            ev.preventDefault();
            const url = a.href;
            try {
                const params = new URL(url).searchParams;
                const p = {
                    q: params.get('q') || '',
                    status: params.get('status') || '',
                    opd_id: params.get('opd_id') || '',
                    opd_unit_id: params.get('opd_unit_id') || '',
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(p));
            } catch (e) {}
            fetchEmployees(url);
        });

        function refreshBatchInfo() {
            const batchInfo = document.getElementById('batchInfo');
            const rows = Array.from(document.querySelectorAll('.chkRow'));
            const count = rows.filter(c => c.checked).length;
            if (!batchInfo) return;
            
            // Show info based on selection source
            if (window.selectAllFilteredState && window.selectAllFilteredState.total > 0) {
                // Select all filtered mode
                batchInfo.textContent = `✓ ${window.selectAllFilteredState.total} pegawai dipilih (filter aktif) untuk batch nametag.`;
            } else if (count) {
                // Manual selection mode
                batchInfo.textContent = `${count} pegawai dipilih untuk batch nametag.`;
            } else {
                // No selection
                batchInfo.textContent = 'Tidak ada pegawai yang dipilih.';
            }
            
            // sync "select all" checkbox state
            try {
                const chkAll = document.getElementById('chkAll');
                if (chkAll) {
                    if (rows.length === 0) {
                        chkAll.checked = false; chkAll.indeterminate = false;
                    } else if (count === 0) {
                        chkAll.checked = false; chkAll.indeterminate = false;
                    } else if (count === rows.length) {
                        chkAll.checked = true; chkAll.indeterminate = false;
                    } else {
                        chkAll.checked = false; chkAll.indeterminate = true;
                    }
                }
            } catch (e) {}
        }

        function buildRow(emp, index) {
            const rowNo = index;
            const st = emp.status_aktif || '';
            const stClass = st === 'AKTIF' ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : 'bg-slate-100 text-slate-700 ring-slate-200';
            const qrLabel = emp.latest_qr_status === 'active' ? 'AKTIF' : (emp.latest_qr_status === 'revoked' ? 'DICABUT' : null);
            const qrClass = emp.latest_qr_status === 'active' ? 'bg-emerald-100 text-emerald-800 ring-emerald-200' : (emp.latest_qr_status === 'revoked' ? 'bg-rose-100 text-rose-800 ring-rose-200' : 'bg-slate-100 text-slate-700 ring-slate-200');

            function renderNametagActions(emp) {
                const status = emp.nametag_status || 'none';
                const safeFront = emp.front_url || '';
                const safeBack = emp.back_url || '';
                const csrf = csrfToken;

                if (status === 'processing' || (status === 'queued' && !safeFront && !safeBack)) {
                    return `<div class="text-sm text-slate-600"><span class="inline-flex items-center px-2 py-1 rounded bg-amber-50 text-amber-800 text-xs">⏳ Diproses…</span></div>`;
                }
                if (status === 'ready') {
                    return `\n<div class="flex flex-wrap gap-2">\n${safeFront ? `<button type="button" onclick="openImgPreview('${safeFront}')" class="h-8 px-2 rounded border border-slate-300 text-[11px] hover:bg-slate-50">Preview Depan</button>` : ''}\n                                ${safeBack ? `<button type="button" onclick="openImgPreview('${safeBack}')" class="h-8 px-2 rounded border border-slate-300 text-[11px] hover:bg-slate-50">Preview Belakang</button>` : ''}\n                                <button type="button" data-id="${emp.id}" data-force="1" class="js-generate h-8 px-3 rounded bg-brand-600 text-[11px] text-white hover:bg-brand-700">Regenerate</button>\n                            </div>`;
                }
                if (status === 'failed') {
                    return `\n<div class="flex items-center gap-2">\n<span class="text-xs text-rose-600">Gagal</span>\n                                <button type="button" data-id="${emp.id}" data-force="1" class="js-generate h-8 px-3 rounded bg-rose-600 text-[11px] text-white hover:bg-rose-700">Retry</button>\n                            </div>`;
                }
                return `\n<button type="button" data-id="${emp.id}" data-force="1" class="js-generate h-8 px-3 rounded bg-brand-600 text-[11px] text-white hover:bg-brand-700">Generate</button>`;
            }

            return `
                    <tr data-emp-id="${emp.id}" class="hover:bg-slate-50/60 dark:hover:bg-white/5">
                        <td class="px-4 py-3 align-top text-xs text-slate-500 dark:text-slate-300">
                            <input type="checkbox" name="ids[]" value="${emp.id}" class="chkRow rounded border-slate-300 text-brand-600 focus:ring-brand-500" ${emp.nametag_status === 'processing' ? 'disabled' : ''}>
                            <div class="mt-1 text-[10px] text-slate-400 dark:text-slate-400">${rowNo}</div>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <div class="font-medium text-slate-800 dark:text-slate-100">${emp.nama || '—'}</div>
                            <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">${emp.nip || '—'}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">${emp.jabatan || '—'}</div>
                        </td>
                        <td class="px-4 py-3 align-top">
                            <div class="text-sm text-slate-800 dark:text-slate-100">${emp.opd || '—'}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">${emp.opd_unit || '—'}</div>
                            <div class="mt-1"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ${stClass}">${st || '—'}</span></div>
                        </td>
                        <td class="px-4 py-3 align-top hidden sm:table-cell">
                            ${emp.latest_qr_token ? `
                                <div class="flex flex-col gap-1">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ${qrClass}">QR ${qrLabel || 'TERAKHIR'}</span>
                                    ${emp.latest_qr_created_at ? `<span class="text-[11px] text-slate-500">${emp.latest_qr_created_at} WIB</span>` : ''}
                                    <div class="flex flex-wrap gap-1">
                                        <a href="/t/${emp.latest_qr_token}" target="_blank" rel="noreferrer" class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-[11px] text-slate-700 hover:bg-slate-200">Halaman Scan</a>
                                        <a href="/scan-logs?q=${emp.latest_qr_token}" class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-[11px] text-slate-700 hover:bg-slate-200">Log Scan</a>
                                    </div>
                                </div>
                            ` : `<span class="text-xs text-slate-400">Belum pernah generate QR</span>`}
                        </td>
                        <td class="px-3 py-2 align-top hidden sm:table-cell">
                            <div class="flex flex-col gap-1 items-start">
                                ${renderNametagActions(emp)}
                            </div>
                        </td>
                        <td class="px-3 py-2 align-top sm:hidden">
                            <a href="/employees/${emp.id}" class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-300 dark:border-slate-700 text-xs text-slate-700 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-white/5">Detail</a>
                        </td>
                        <td class="px-3 py-2 align-top text-right">
                            <div class="inline-flex flex-wrap gap-2 justify-end">
                                <a href="/employees/${emp.id}" title="Detail" class="inline-flex items-center h-8 w-8 justify-center rounded-lg border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-white/5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                </a>
                                ${emp.can_update ? `<a href="/employees/${emp.id}/edit" title="Ubah" class="inline-flex items-center h-8 w-8 justify-center rounded-lg bg-brand-50 text-brand-700 border border-brand-200 hover:bg-brand-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536M4 20h4.586a1 1 0 00.707-.293l9.414-9.414a1 1 0 000-1.414L15.414 4.586a1 1 0 00-1.414 0L4 14.586V19a1 1 0 001 1z"/></svg>
                                </a>` : ''}
                                ${emp.can_force_delete ? `<button type="button" title="Hapus Permanen" onclick="window.deleteEmployee(${emp.id})" class="js-delete-employee inline-flex items-center h-8 w-8 justify-center rounded-lg bg-rose-600 text-white border border-rose-700 hover:bg-rose-700" data-emp-id="${emp.id}">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M8 7V4a1 1 0 011-1h6a1 1 0 011 1v3"/></svg>
                                </button>` : ''}
                            </div>
                        </td>
                    </tr>
                    `;
                }

                function fetchEmployees(url) {
            const tbody = document.getElementById('employeesTbody');
            const pag = document.getElementById('employeesPagination');
            const loading = document.getElementById('employeesLoading');
            if (!tbody || !pag) return;
            if (loading) loading.classList.remove('hidden');
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(data => {
                    const params = new URL(url, window.location.origin).searchParams;
                    const page = parseInt(params.get('page') || '1', 10);
                    const perPage = data.meta.per_page || 20;
                    const startNo = ((page - 1) * perPage) + 1;
                    const rows = data.employees.map((emp, i) => buildRow(emp, startNo + i)).join('');
                    tbody.innerHTML = rows;
                    const current = data.meta.current_page;
                    const last = data.meta.last_page;
                    const total = data.meta.total;
                    const from = (current - 1) * perPage + 1;
                    const to = Math.min(current * perPage, total);
                    
                    const u = new URL(url, window.location.origin);
                    u.searchParams.delete('page');
                    const base = u.pathname + (u.search ? '?' + u.searchParams.toString() : '');
                    
                    let pagHtml = '<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">';
                    
                    // Info section
                    pagHtml += `<div class="text-sm text-slate-600 dark:text-slate-400">
                        <span class="font-semibold text-slate-700 dark:text-slate-300">${from}-${to}</span>
                        dari
                        <span class="font-semibold text-slate-700 dark:text-slate-300">${total}</span>
                        data
                    </div>`;
                    
                    // Pagination links
                    pagHtml += '<div class="flex flex-wrap items-center gap-1">';
                    
                    // Previous button
                    if (current > 1) {
                        pagHtml += `<a href="${base + (base.includes('?') ? '&' : '?')}page=${current-1}" class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg></a>`;
                    } else {
                        pagHtml += '<button disabled class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed dark:border-slate-700 dark:bg-slate-900"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg></button>';
                    }
                    
                    // Page numbers
                    const range = 2;
                    const start = Math.max(1, current - range);
                    const end = Math.min(last, current + range);
                    const showFirstDots = (start > 2);
                    const showLastDots = (end < last - 1);
                    
                    if (start > 1) {
                        pagHtml += `<a href="${base + (base.includes('?') ? '&' : '?')}page=1" class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">1</a>`;
                        if (showFirstDots) {
                            pagHtml += '<span class="h-9 flex items-center px-1 text-slate-400">…</span>';
                        }
                    }
                    
                    for (let i = start; i <= end; i++) {
                        if (i === current) {
                            pagHtml += `<button disabled class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-brand-600 bg-brand-600 text-sm font-semibold text-white dark:border-brand-500 dark:bg-brand-500">${i}</button>`;
                        } else {
                            pagHtml += `<a href="${base + (base.includes('?') ? '&' : '?')}page=${i}" class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">${i}</a>`;
                        }
                    }
                    
                    if (end < last) {
                        if (showLastDots) {
                            pagHtml += '<span class="h-9 flex items-center px-1 text-slate-400">…</span>';
                        }
                        pagHtml += `<a href="${base + (base.includes('?') ? '&' : '?')}page=${last}" class="inline-flex items-center justify-center h-9 min-w-9 px-2 rounded border border-slate-200 bg-white text-sm text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">${last}</a>`;
                    }
                    
                    // Next button
                    if (current < last) {
                        pagHtml += `<a href="${base + (base.includes('?') ? '&' : '?')}page=${current+1}" class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 transition dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg></a>`;
                    } else {
                        pagHtml += '<button disabled class="inline-flex items-center justify-center h-9 w-9 rounded border border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed dark:border-slate-700 dark:bg-slate-900"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5l7 7-7 7"/></svg></button>';
                    }
                    
                    pagHtml += '</div></div>';
                    pag.innerHTML = pagHtml;
                    try { window.history.replaceState({}, '', url); } catch (e) {}
                    document.querySelectorAll('.chkRow').forEach(c => c.addEventListener('change', refreshBatchInfo));
                    attachDeleteHandlers();
                    refreshBatchInfo();
                })
                .catch(() => { window.location = url; })
                .finally(() => { if (loading) loading.classList.add('hidden'); });
        }

            // expose a refresh helper for external scripts (batch poller) to
            // request a fresh table load when batch status changes
            try { window.nametag_fetchEmployees = function(){ try { fetchEmployees(window.location.pathname + window.location.search); } catch(e){} }; } catch(e){}

        // reset button clears storage and reloads results via AJAX restoring server defaults
        const resetBtn = document.getElementById('resetFilters');
        if (resetBtn) {
            resetBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                console.log('[Reset] Button clicked');
                
                try { localStorage.removeItem(STORAGE_KEY); } catch (ex) {}

                // Clear inputs to show defaults
                try {
                    if (inputQ) { inputQ.value = ''; console.log('[Reset] Cleared inputQ'); }
                    if (selectStatus) { selectStatus.value = ''; console.log('[Reset] Cleared selectStatus'); }
                    if (selectOpd) { selectOpd.value = ''; console.log('[Reset] Cleared selectOpd'); }
                    
                    // Clear opd_parent_only via hidden input
                    const hiddenOpdParentOnly = document.getElementById('hiddenOpdParentOnly');
                    if (hiddenOpdParentOnly) { hiddenOpdParentOnly.value = '0'; console.log('[Reset] Cleared hiddenOpdParentOnly'); }

                    // Repopulate dependent selects with empty OPD selection
                    try { populateUnits('', null); console.log('[Reset] Populated units'); } catch (ee) { console.warn('[Reset] populateUnits failed:', ee); }
                    try { populateUnitKerjas('', null); console.log('[Reset] Populated unit kerjas'); } catch (ee) { console.warn('[Reset] populateUnitKerjas failed:', ee); }

                    if (selectUnit) { selectUnit.value = ''; console.log('[Reset] Cleared selectUnit'); }
                    if (selectUnitKerja) { selectUnitKerja.value = ''; console.log('[Reset] Cleared selectUnitKerja'); }

                    // First clear server-side saved filters, then fetch unfiltered list
                    const resetUrl = (window.EMP_INDEX_CONFIG && window.EMP_INDEX_CONFIG.urls && window.EMP_INDEX_CONFIG.urls.resetFilters)
                        ? window.EMP_INDEX_CONFIG.urls.resetFilters
                        : (window.location.pathname.replace(/\/$/, '') + '/filters/reset');

                    console.log('[Reset] Calling reset URL:', resetUrl);
                    
                    try {
                        fetch(resetUrl, { 
                            method: 'GET',
                            credentials: 'same-origin', 
                            headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                        })
                            .then(response => {
                                console.log('[Reset] Reset endpoint response status:', response.status);
                                const url = window.location.pathname;
                                try { window.history.replaceState({}, '', url); } catch (ee) {}
                                console.log('[Reset] Fetching employees from:', url);
                                fetchEmployees(url);
                            })
                            .catch(err => { 
                                console.warn('[Reset] Fetch failed:', err); 
                                try { window.location = window.location.pathname; } catch (ee) {} 
                            });
                    } catch (e) {
                        console.error('[Reset] Exception during fetch:', e);
                        try { window.location = window.location.pathname; } catch (ee) {}
                    }
                    return;
                } catch (ex2) {
                    console.warn('[Reset] failed, falling back to reload', ex2);
                }

                // final fallback: full page reload
                console.log('[Reset] Fallback: full page reload');
                try { window.location = window.location.pathname; } catch (ee) {}
            });
        }

        // Batch handling: legacy polling removed. Batch UI is now driven
        // entirely by the `nametag.batch.queued` endpoint (see batch-ui partial).
        // Keep a minimal retry button handler that triggers server retry and
        // relies on the batch-ui polling to pick up changes.

        const batchForm = document.getElementById('batchForm');
        if (batchForm) {
            batchForm.addEventListener('submit', (ev) => {
                const checked = Array.from(document.querySelectorAll('.chkRow:checked'));
                if (checked.length === 0) { alert('Pilih minimal satu pegawai.'); ev.preventDefault(); return; }
                const WARN_LIMIT = 500;
                const HARD_LIMIT = 5000;
                if (checked.length > HARD_LIMIT) { alert(`Pilihan terlalu banyak (${checked.length}). Batalkan dan pilih lebih sedikit.`); ev.preventDefault(); return; }
                if (checked.length > WARN_LIMIT) { if (!confirm(`Anda memilih ${checked.length} pegawai. Proses ini bisa memakan waktu lama. Lanjutkan?`)) { ev.preventDefault(); return; } }
                // allow normal POST so server logs and errors surface
            });
        }

        const retryBtn = document.getElementById('retryFailedBtn');
        if (retryBtn) {
            retryBtn.addEventListener('click', async (ev) => {
                ev.preventDefault();
                const batchId = retryBtn.dataset.batch || '';
                if (!batchId) return alert('Tidak ada batch yang dipilih untuk retry.');
                if (!confirm('Retry hanya pegawai yang gagal? Lanjutkan?')) return;
                try {
                    retryBtn.disabled = true;
                    if (!urls.batchRetryFailed) throw new Error('no retry url');
                    const res = await fetch(urls.batchRetryFailed + '/' + batchId, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const j = await res.json().catch(() => ({}));
                    if (!res.ok || !j.ok) { if (window.showToast) window.showToast('error', j.message || 'Gagal retry failed'); return; }
                    if (window.showToast) window.showToast('success', `Requeued ${j.requeued || 0} failed items`);
                    // Do not start per-batch polling here; batch-ui will reflect changes.
                } catch (e) { console.error(e); if (window.showToast) window.showToast('error', 'Gagal melakukan retry.'); } finally { retryBtn.disabled = false; }
            });
        }

        // initial load
        loadSaved();
        try {
            const cfg = window.EMP_INDEX_CONFIG || {};
            const initialFilters = cfg.initialFilters || {};
            const opdForInit = selectOpd.value || initialFilters.opd_id || '';
            const unitForInit = initialFilters.opd_unit_id || '';
            const unitKerjaForInit = initialFilters.unit_kerja_id || '';
            if (opdForInit) {
                selectOpd.value = opdForInit;
                populateUnits(opdForInit, unitForInit || null);
                populateUnitKerjas(opdForInit, unitKerjaForInit || null);
            }
        } catch (e) {}

        // force delete modal / handler
        (function(){
            const modal = document.getElementById('forceDeleteModal');
            const modalBody = document.getElementById('forceDeleteModalBody');
            const btnCancel = document.getElementById('forceDeleteCancel');
            const btnConfirm = document.getElementById('forceDeleteConfirm');
            const btnClose = document.getElementById('forceDeleteModalClose');
            let activeForm = null;
            
            function showModal(text) {
                if (modalBody) modalBody.textContent = text || modalBody.textContent;
                if (!modal) return;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                try { btnConfirm?.focus(); } catch(e) {}
            }
            
            function hideModal() { 
                if (!modal) return; 
                modal.classList.add('hidden'); 
                modal.classList.remove('flex');
                activeForm = null;
            }
            
            // Cancel button
            btnCancel?.addEventListener('click', hideModal);
            btnClose?.addEventListener('click', hideModal);
            
            // Confirm button - calls pending delete function
            btnConfirm?.addEventListener('click', async function(){
                if (!window._pendingDelete) {
                    hideModal();
                    return;
                }
                try {
                    btnConfirm.disabled = true;
                    btnConfirm.textContent = 'Menghapus...';
                    const result = await window._pendingDelete();
                    // Close modal after delete completes (success or fail)
                    hideModal();
                } catch(e) {
                    console.error('Delete error:', e);
                } finally {
                    btnConfirm.disabled = false;
                    btnConfirm.textContent = 'Hapus';
                    window._pendingDelete = null;
                }
            });
            
            // Close modal when clicking outside (on the backdrop)
            modal?.addEventListener('click', function(e){
                if (e.target === modal) hideModal();
            });
        })();


        // delegated handler for per-row generate buttons (avoids nested forms)
        document.addEventListener('click', async function(ev){
            const btn = ev.target.closest && ev.target.closest('.js-generate');
            if (!btn) return;
            ev.preventDefault();
            const id = btn.dataset.id;
            const force = btn.dataset.force || '1';
            if (!id) return;
            const origText = btn.textContent;
            const csrfLocal = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const confirmText = origText && origText.toLowerCase().includes('retry') ? 'Retry generate nametag untuk pegawai ini?' : (origText && origText.toLowerCase().includes('regenerate') ? 'Regenerate nametag untuk pegawai ini?' : 'Generate nametag untuk pegawai ini?');
            if (!confirm(confirmText)) return;
            try {
                btn.disabled = true;
                btn.textContent = 'Mengirim...';
                const res = await fetch('employees/' + id + '/nametag', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrfLocal, 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ force: force })
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok) {
                    if (window.showToast) window.showToast('error', j.message || `Gagal generate (status ${res.status})`);
                } else {
                    if (window.showToast) window.showToast('success', j.message || 'Permintaan generate dikirim.');
                    // immediate UI update: if server returned front/back URLs, update row actions
                    try {
                        const empId = j.employee_id || id;
                        const front = j.front_url || j.front_out || j.front || j.frontPath || null;
                        const back = j.back_url || j.back_out || j.back || j.backPath || null;
                        if (front || back) {
                            try { updateRowNametagActions(empId, front, back); } catch(e) { console.debug('updateRowNametagActions failed', e); }
                        }
                    } catch(e) { console.debug('immediate row update failed', e); }
                    try { fetchEmployees(window.location.pathname + window.location.search); } catch(e){}
                }
            } catch (e) {
                console.error(e);
                if (window.showToast) window.showToast('error', 'Gagal mengirim permintaan.');
            } finally {
                btn.disabled = false;
                try { btn.textContent = origText; } catch(e){}
            }
        });

        // Update a single employee row's nametag actions cell to show previews
        // and a regenerate button when front/back outputs are available.
        function updateRowNametagActions(empId, frontUrl, backUrl) {
            try {
                const tr = document.querySelector(`tr[data-emp-id="${empId}"]`);
                if (!tr) return;
                // find the TD that contains nametag actions by searching for existing markers
                let targetTd = null;
                const tds = Array.from(tr.querySelectorAll('td'));
                for (const td of tds) {
                    const html = (td.innerHTML || '').toString();
                    if (html.includes('Diproses') || html.includes('Preview Depan') || html.includes('Preview Belakang') || td.querySelector('.js-generate')) { targetTd = td; break; }
                }
                if (!targetTd) return;
                const safeFront = frontUrl ? String(frontUrl).replace(/'/g, "\\'") : '';
                const safeBack = backUrl ? String(backUrl).replace(/'/g, "\\'") : '';
                const newHtml = `\n<div class="flex flex-wrap gap-2">\n${safeFront ? `<button type="button" onclick="openImgPreview('${safeFront}')" class="h-8 px-2 rounded border border-slate-300 text-[11px] hover:bg-slate-50">Preview Depan</button>` : ''}\n                                ${safeBack ? `<button type="button" onclick="openImgPreview('${safeBack}')" class="h-8 px-2 rounded border border-slate-300 text-[11px] hover:bg-slate-50">Preview Belakang</button>` : ''}\n                                <button type="button" data-id="${empId}" data-force="1" class="js-generate h-8 px-3 rounded bg-brand-600 text-[11px] text-white hover:bg-brand-700">Regenerate</button>\n                            </div>`;
                targetTd.innerHTML = newHtml;
                // rebind checkbox change handlers if row was replaced
                try { document.querySelectorAll('.chkRow').forEach(c => c.addEventListener('change', refreshBatchInfo)); } catch (e) {}
            } catch (e) { console.debug('updateRowNametagActions error', e); }
        }


    });
})();

    </script>
    <!-- Delete button handler - DEPRECATED (use event delegation instead) -->
    <!-- Kept for backward compatibility if old onclick handlers are called -->
    <script>
        window.handleDeleteClick = async function(event, empId) {
            console.warn('[Delete] Legacy onclick handler called - should use event delegation instead');
            event?.preventDefault?.();
            event?.stopPropagation?.();
            
            if (!empId) {
                console.error('[Delete] No employee ID provided');
                return;
            }
            
            if (!confirm('Hapus pegawai ini secara permanen?')) {
                return;
            }
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const currentUrl = new URL(window.location.href);
            const pathnameParts = currentUrl.pathname.split('/').filter(p => p);
            if (pathnameParts[pathnameParts.length - 1] === 'employees') {
                pathnameParts.pop();
            }
            const url = '/' + pathnameParts.join('/') + '/employees/' + empId + '/force-delete';
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    window.showToast && window.showToast('success', 'Data berhasil dihapus.');
                    try { 
                        window.nametag_fetchEmployees ? window.nametag_fetchEmployees() : location.reload();
                    } catch(e) { 
                        location.reload();
                    }
                } else {
                    const data = await response.json().catch(() => ({}));
                    window.showToast && window.showToast('error', data.message || 'Gagal menghapus data.');
                }
            } catch(error) {
                console.error('[Delete] Fetch error:', error);
                window.showToast && window.showToast('error', 'Terjadi kesalahan saat menghapus data.');
            }
        };
        console.log('[Init] window.handleDeleteClick defined (legacy):', typeof window.handleDeleteClick);
    </script>
    <script>
        // header buttons: generate and download
        (function(){
            const gen = document.getElementById('generateBatchBtn');
            const dl = document.getElementById('downloadSelected');
            const batchForm = document.getElementById('batchForm');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            
            // Debug: log if buttons found
            console.log('[Batch Button] generateBatchBtn found:', !!gen, 'downloadSelected found:', !!dl, 'batchForm found:', !!batchForm);

            // Helper: toggle button alive/loading state (non-destructive)
            function setBatchButtonLoading(isLoading) {
                const btn = document.getElementById('generateBatchBtn');
                if (!btn) return;

                const icon = btn.querySelector('svg');
                const text = btn.querySelector('span.font-medium');

                if (isLoading) {
                    btn.disabled = true;
                    btn.classList.add('opacity-70', 'cursor-not-allowed');
                    if (icon) icon.classList.add('animate-spin');
                    if (text) text.textContent = 'Mengirim...';
                } else {
                    btn.disabled = false;
                    btn.classList.remove('opacity-70', 'cursor-not-allowed');
                    if (icon) icon.classList.remove('animate-spin');
                    if (text) text.textContent = 'Proses Massal';
                }
            }

            // New robust batch starter: perform AJAX dispatch so we can show immediate feedback
            async function startEmployeesBatch() {
                console.log('[startEmployeesBatch] Function called');
                // immediate feedback
                setBatchButtonLoading(true);

                try {
                    const form = document.getElementById('batchForm');
                    if (!form) return alert('Form batch tidak ditemukan.');
                    
                    // Determine IDs source: either select-all-filtered or manual checkboxes
                    let ids = [];
                    let selectionMode = 'manual';
                    
                    if (window.selectAllFilteredState && window.selectAllFilteredState.total > 0 && window.selectAllFilteredState.ids) {
                        // Use select-all-filtered IDs from session
                        ids = window.selectAllFilteredState.ids;
                        selectionMode = 'filtered';
                    } else {
                        // Use manually checked rows
                        ids = Array.from(document.querySelectorAll('.chkRow:checked'))
                            .map(i => i.value);
                    }
                    
                    if (!ids.length) return alert('Pilih minimal satu pegawai.');

                    try {
                        if (window.showToast) window.showToast('info', `Mengirim ${ids.length} pegawai ke antrian (${selectionMode})...`);
                    } catch (e) { console.warn('pre-submit UI update failed', e); }

                    const fd = new FormData();
                    ids.forEach(id => fd.append('ids[]', id));
                    
                    // Add flag for backend to know this is from filtered session
                    if (selectionMode === 'filtered') {
                        fd.append('use_filtered_session', '1');
                    }
                    
                    const csrfLocal = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const dispatchUrl = (window.EMP_INDEX_CONFIG && window.EMP_INDEX_CONFIG.urls && window.EMP_INDEX_CONFIG.urls.nametagBatchDispatch) || 'nametag/batch/dispatch';
                    const res = await fetch(dispatchUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': csrfLocal, 'X-Requested-With': 'XMLHttpRequest' },
                        body: fd,
                    });

                    // Try to parse JSON if present; but accept non-JSON 2xx responses too
                    let json = {};
                    try { json = await res.json().catch(() => ({})); } catch (e) { json = {}; }

                    if (!res.ok) {
                        const msg = (json && json.message) ? json.message : `Gagal memulai batch (status ${res.status}).`;
                        if (window.showToast) window.showToast('error', msg);
                        return;
                    }

                    // Best-effort extract batch id from JSON or Location header
                    let batchId = (json && (json.batch_id || json.id || json.batch)) ? (json.batch_id || json.id || json.batch) : null;
                    if (!batchId) {
                        const loc = res.headers.get('Location') || res.headers.get('location') || '';
                        if (loc) {
                            try { batchId = loc.split('/').pop(); } catch (e) { /* ignore */ }
                        }
                    }

                    // Success feedback
                    if (window.showToast) window.showToast('success', `Batch dikirim (${ids.length} item).`);

                    // start targeted polling if we have a batch id
                    try {
                        if (batchId) {
                            window.CURRENT_BATCH_ID = batchId;
                            try { if (window.nametag_pollForBatch) window.nametag_pollForBatch(batchId); } catch(e){}
                        }
                        // always refresh overall queued list
                        try { if (window.nametag_fetchQueued) window.nametag_fetchQueued(); } catch(e){}
                    } catch (e) { console.debug('start targeted poll failed', e); }

                    // refresh table (non-blocking)
                    try { fetchEmployees(window.location.pathname + window.location.search); } catch (e) {}

                    // scroll to batch UI and briefly highlight newest batch element
                    try {
                        const batchSection = document.getElementById('batch-ui-root') || document.getElementById('nametagUiContainer');
                        if (batchSection) {
                            batchSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            const firstBatch = batchSection.querySelector('#nametagBatches [data-batch-id]');
                            if (firstBatch) {
                                firstBatch.classList.add('ring-2', 'ring-indigo-400');
                                setTimeout(() => firstBatch.classList.remove('ring-2', 'ring-indigo-400'), 1500);
                            }
                        }
                    } catch (e) { console.debug('scroll/highlight failed', e); }

                } catch (e) {
                    console.error(e);
                    if (window.showToast) window.showToast('error', 'Terjadi kesalahan saat mengirim batch.');
                } finally {
                    setBatchButtonLoading(false);
                }
            }

            if (gen) {
                gen.addEventListener('click', startEmployeesBatch);
                console.log('[Batch Button] Click handler attached to generateBatchBtn');
            } else {
                console.warn('[Batch Button] generateBatchBtn not found, click handler NOT attached!');
            }

            if (dl) {
                dl.addEventListener('click', function(){
                    const ids = Array.from(document.querySelectorAll('.chkRow:checked')).map(i => i.value);
                    console.debug('downloadSelected clicked ids=', ids);
                    if (!ids.length) return alert('Pilih minimal satu pegawai untuk diunduh.');

                    // create form and submit to download endpoint so browser handles download
                    const action = (window.EMP_INDEX_CONFIG && window.EMP_INDEX_CONFIG.urls && window.EMP_INDEX_CONFIG.urls.nametagBatchDownload) || 'nametag/batch/download';
                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.action = action;
                    f.style.display = 'none';
                    const token = document.createElement('input'); token.type = 'hidden'; token.name = '_token'; token.value = csrf; f.appendChild(token);
                    ids.forEach(id => {
                        const i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; f.appendChild(i);
                    });
                    document.body.appendChild(f);
                    f.submit();
                });
            }
        })();
    </script>
</x-layouts.admin>
