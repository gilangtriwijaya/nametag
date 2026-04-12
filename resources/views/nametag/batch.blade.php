<x-layouts.admin :title="'Batch Nametag'">
  <div class="space-y-6">

    {{-- Filter & opsi --}}
    <div class="rounded-xl border p-4 bg-white">
      <div class="flex flex-wrap items-end gap-3">
        <div>
          <label class="block text-sm font-medium mb-1">Unit OPD</label>
          <select id="unit" class="border rounded px-3 py-2 min-w-[260px]">
            <option value="">(Semua)</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Limit</label>
          <input
            id="limit"
            type="number"
            value="200"
            min="1"
            max="1000"
            class="border rounded px-3 py-2 w-28"
          >
        </div>

        <div class="flex items-center gap-3">
          <label class="inline-flex items-center gap-2">
            <input id="only_front" type="checkbox" class="h-4 w-4">
            <span class="text-sm">Only Front</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input id="only_back" type="checkbox" class="h-4 w-4">
            <span class="text-sm">Only Back</span>
          </label>
        </div>

        <button
          id="reload"
          type="button"
          class="px-4 py-2 rounded bg-slate-600 text-white"
        >
          Muat Ulang
        </button>
      </div>
    </div>

    {{-- Tabel pegawai --}}
    <div class="rounded-xl border bg-white overflow-hidden">
      <div class="px-4 py-3 border-b flex items-center justify-between">
        <div class="font-semibold">
          Daftar Pegawai
          <span class="text-xs text-slate-400 ml-1" id="emp_count"></span>
        </div>
        <div class="text-sm text-slate-500">
          Estimasi: <span id="eta">-</span> detik
        </div>
      </div>
      <div class="p-4">
        <div class="mb-3 flex items-center gap-3">
          <label class="inline-flex items-center gap-2">
            <input id="check_all" type="checkbox" class="h-4 w-4">
            <span>Centang semua</span>
          </label>
          <button
            id="dispatch"
            type="button"
            class="px-4 py-2 rounded bg-indigo-600 text-white disabled:opacity-50"
            disabled
          >
            Jalankan Batch
          </button>
          <button
            id="createArchive"
            type="button"
            class="px-4 py-2 rounded bg-emerald-600 text-white disabled:opacity-50"
            disabled
          >
            Buat Arsip
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-slate-50 text-left">
                <th class="p-2 w-10"></th>
                <th class="p-2">Nama</th>
                <th class="p-2">NIP</th>
                <th class="p-2">Unit OPD</th>
                <th class="p-2">OPD</th>
              </tr>
            </thead>
            <tbody id="tbody">
              <tr>
                <td colspan="5" class="p-4 text-center text-slate-400">
                  Memuat data...
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Progres batch --}}
    <div class="rounded-xl border bg-white">
      <div class="px-4 py-3 border-b font-semibold">Progres</div>
      <div class="p-4">
        <div class="w-full bg-slate-100 rounded h-8 overflow-hidden">
          <div id="bar" class="h-8 bg-indigo-600 rounded flex items-center justify-center text-white text-sm font-medium" style="width:0%">0%</div>
        </div>
        <div class="mt-2 text-xs text-slate-500" id="statusVis" aria-hidden="true" style="display:none">-</div>
      </div>
    </div>

  </div>

  <script>
    const elUnit       = document.getElementById('unit');
    const elLimit      = document.getElementById('limit');
    const elEta        = document.getElementById('eta');
    const elEmpCount   = document.getElementById('emp_count');
    const elTbody      = document.getElementById('tbody');
    const elCheckAll   = document.getElementById('check_all');
    const elDispatch   = document.getElementById('dispatch');
    const elReload     = document.getElementById('reload');
    const elCreateArchive = document.getElementById('createArchive');
    const elOnlyFront  = document.getElementById('only_front');
    const elOnlyBack   = document.getElementById('only_back');
    const elBar        = document.getElementById('bar');
    const elStatus     = document.getElementById('status');
    const elStatusVis  = document.getElementById('statusVis');

    let dataEmployees   = [];
    let currentBatch    = null;
    let currentBatchToast = null;
    let polling         = null;
    let lastSelectedUnit = ''; // simpan pilihan agar tidak hilang

    function clampLimit() {
      let lim = parseInt(elLimit.value || '200', 10);
      if (isNaN(lim)) lim = 200;
      lim = Math.min(Math.max(lim, 1), 1000);
      elLimit.value = lim;
      return lim;
    }

    function buildQueryString() {
      const u   = new URLSearchParams();
      const lim = clampLimit();
      u.set('limit', lim);
      if (elUnit.value) u.set('unit', elUnit.value);
      return u.toString();
    }

    function fmtInt(n) {
      return (n ?? 0).toString();
    }

    function setLoadingState(isLoading) {
      elReload.disabled   = isLoading;
      elLimit.disabled    = isLoading;
      elUnit.disabled     = isLoading;
      elCheckAll.disabled = isLoading;
      if (isLoading) {
        elDispatch.disabled = true;
        if (elCreateArchive) elCreateArchive.disabled = true;
        elTbody.innerHTML = `
          <tr>
            <td colspan="5" class="p-4 text-center text-slate-400">
              Memuat data...
            </td>
          </tr>
        `;
      }
    }

    async function loadData() {
      clearInterval(polling);
      polling      = null;
      currentBatch = null;
      elBar.style.width = '0%';
      elStatus.innerText = '-';

      setLoadingState(true);
      elEmpCount.textContent = '';

      try {
        const res = await fetch(`{{ route('nametag.batch.data') }}?${buildQueryString()}`, {
          headers: {'X-Requested-With':'XMLHttpRequest'}
        });

        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }

        const json = await res.json();

        if (json.ok === false) {
          throw new Error(json.message || 'Gagal memuat data');
        }

        // units
        if (json.units) {
          const selectedUnit = json.selected_unit || lastSelectedUnit || '';
          elUnit.innerHTML = '<option value="">(Semua)</option>' + json.units
            .map(u => `<option value="${escapeHtml(u)}">${escapeHtml(u)}</option>`)
            .join('');
          elUnit.value = selectedUnit;
          lastSelectedUnit = selectedUnit;
        }

        // employees
        dataEmployees = json.employees || [];
        elEta.innerText = fmtInt(json.eta);
        elEmpCount.textContent = dataEmployees.length
          ? `(${dataEmployees.length} pegawai)`
          : '(0 pegawai)';

        renderTable();
        elDispatch.disabled = dataEmployees.length === 0;
        if (elCreateArchive) elCreateArchive.disabled = dataEmployees.length === 0;
        elCheckAll.checked = false;
      } catch (e) {
        console.error(e);
        elEta.innerText = '-';
        elEmpCount.textContent = '';
        elTbody.innerHTML = `
          <tr>
            <td colspan="5" class="p-4 text-center text-red-500">
              Gagal memuat data. ${escapeHtml(e.message || '')}
            </td>
          </tr>
        `;
        elDispatch.disabled = true;
      } finally {
        setLoadingState(false);
      }
    }

    function renderTable() {
      if (!dataEmployees.length) {
        elTbody.innerHTML = `
          <tr>
            <td colspan="5" class="p-4 text-center text-slate-400">
              Tidak ada data pegawai aktif untuk filter ini.
            </td>
          </tr>
        `;
        return;
      }

      elTbody.innerHTML = dataEmployees.map(e => `
        <tr class="border-b hover:bg-slate-50">
          <td class="p-2">
            <input
              type="checkbox"
              class="rowchk h-4 w-4"
              value="${e.id}"
            >
          </td>
          <td class="p-2">${escapeHtml(e.nama ?? '')}</td>
          <td class="p-2">${escapeHtml(e.nip ?? '')}</td>
          <td class="p-2">${escapeHtml(e.nama_unit_opd ?? '')}</td>
          <td class="p-2">${escapeHtml(e.opd_nama ?? '')}</td>
        </tr>
      `).join('');
    }

    elCheckAll.addEventListener('change', () => {
      document.querySelectorAll('.rowchk').forEach(ch => {
        ch.checked = elCheckAll.checked;
      });
    });

    // Muat ulang manual
    elReload.addEventListener('click', () => {
      lastSelectedUnit = elUnit.value;
      loadData();
    });

    // Ganti unit → reload
    elUnit.addEventListener('change', () => {
      lastSelectedUnit = elUnit.value;
      loadData();
    });

    // Live limit (debounce)
    let limTimer = null;
    elLimit.addEventListener('input', () => {
      clearTimeout(limTimer);
      limTimer = setTimeout(() => {
        loadData();
      }, 400);
    });

    // mutual exclusive only_front/back
    elOnlyFront.addEventListener('change', () => {
      if (elOnlyFront.checked) elOnlyBack.checked = false;
    });
    elOnlyBack.addEventListener('change', () => {
      if (elOnlyBack.checked) elOnlyFront.checked = false;
    });

    async function doDispatch() {
      const ids = Array.from(document.querySelectorAll('.rowchk'))
        .filter(ch => ch.checked)
        .map(ch => parseInt(ch.value, 10))
        .filter(v => !isNaN(v));

      if (ids.length === 0) {
        alert('Pilih minimal satu pegawai.');
        return;
      }

      elDispatch.disabled = true;
      elStatus.innerText = 'Mengirim batch...';

      const fd = new FormData();
      ids.forEach(id => fd.append('ids[]', id));
      if (elOnlyFront.checked) fd.set('only_front', '1');
      if (elOnlyBack.checked)  fd.set('only_back', '1');

      try {
        const res = await fetch(`{{ route('nametag.batch.dispatch') }}`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: fd,
        });

        const json = await res.json().catch(() => ({}));

        if (!res.ok || !json.ok) {
          const msg = json.message || `Gagal memulai batch (HTTP ${res.status})`;
            if (window.showToast) window.showToast('error', msg);
          elStatus.innerText = 'Gagal memulai batch.';
          elDispatch.disabled = false;
          return;
        }

        currentBatch = json.batch_id;
        elStatus.innerText = 'Batch dimulai, memantau progres...';
        if (window.showToast) currentBatchToast = window.showToast('info', 'Batch dikirim ke antrian', 0);
        startPolling();
      } catch (e) {
        console.error(e);
        if (window.showToast) window.showToast('error', 'Terjadi kesalahan saat mengirim batch.');
        elStatus.innerText = 'Gagal memulai batch.';
        elDispatch.disabled = false;
      }
    }

    function startPolling() {
      clearInterval(polling);

      function fmtDuration(sec) {
        sec = Math.max(0, Math.floor(sec || 0));
        const h = Math.floor(sec / 3600);
        const m = Math.floor((sec % 3600) / 60);
        const s = sec % 60;
        if (h > 0) return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        return `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
      }

      const tick = async () => {
        if (!currentBatch) return;

        try {
          const url = `{{ url('/nametag/batch/progress') }}/${currentBatch}`;
          const res = await fetch(url, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
          if (!res.ok) return;

          const j = await res.json();
          if (!j.ok) return;

          const total = j.total ?? 0;
          const done  = j.done  ?? 0;
          const fail  = j.fail  ?? 0;
          const eta   = j.eta   ?? 0;
          const pct   = total ? Math.round(((done + fail) / total) * 100) : 0;

          elBar.style.width = pct + '%';

          let elapsed = 0;
          if (j.started_at) {
            const startMs = Date.parse(j.started_at);
            if (!isNaN(startMs)) elapsed = Math.floor((Date.now() - startMs) / 1000);
          }

          const lastMs = j.last_item_ms ?? null;

          // Build label for inside progress bar
          let label = `${pct}%`;
          if (eta) label += ` · ETA ${fmtDuration(eta)}`;
          else if (j.status) label += ` · ${j.status}`;
          if (lastMs !== null) label += ` · ${lastMs}ms/item`;
          elBar.innerText = label;
          if (elStatusVis) {
            elStatusVis.style.display = 'none';
            elStatusVis.innerText = `Status: ${j.status} | ${done}/${total} selesai, gagal ${fail} | elapsed ${fmtDuration(elapsed)}`;
          }

          const terminalStatuses = [
            'finished',
            'finished_with_errors',
            'empty',
            'invalid_mode',
            'template_missing',
          ];

          if (j.status && terminalStatuses.includes(j.status)) {
            clearInterval(polling);
            polling = null;
            elDispatch.disabled = false;
            // final friendly message shown in bar + toast
            const totalDone = done;
            const totalFail = fail;
            const finalMsg = `Selesai: ${totalDone}/${total} berhasil, gagal ${totalFail}. Waktu: ${fmtDuration(elapsed)}`;
            elBar.style.width = '100%';
            elBar.innerText = finalMsg;
            if (window.updateToast && currentBatchToast) {
              if (totalFail && totalFail > 0) {
                window.updateToast(currentBatchToast, 'warning', `Batch selesai — ${totalDone}/${total} berhasil, ${totalFail} gagal`, 6000);
              } else {
                window.updateToast(currentBatchToast, 'success', `Batch selesai — ${totalDone}/${total} berhasil`, 4000);
              }
              // clear local ref after a short delay
              setTimeout(() => { currentBatchToast = null; }, 7000);
            } else if (window.showToast) {
              if (totalFail && totalFail > 0) {
                window.showToast('warning', `Batch selesai — ${totalDone}/${total} berhasil, ${totalFail} gagal`);
              } else {
                window.showToast('success', `Batch selesai — ${totalDone}/${total} berhasil`);
              }
            }
          }
        } catch (e) {
          console.error(e);
          // jangan matikan polling hanya karena satu error kecil
        }
      };

      tick();
      polling = setInterval(tick, 1500);
    }

    elDispatch.addEventListener('click', doDispatch);

    // Archive creation flow
    const elCreateArchive = document.getElementById('createArchive');
    let archivePoll = null;

    async function doCreateArchive() {
      const ids = Array.from(document.querySelectorAll('.rowchk'))
        .filter(ch => ch.checked)
        .map(ch => parseInt(ch.value, 10))
        .filter(v => !isNaN(v));

      if (ids.length === 0) {
        alert('Pilih minimal satu pegawai.');
        return;
      }

      elCreateArchive.disabled = true;
      const fd = new FormData();
      ids.forEach(id => fd.append('ids[]', id));

      try {
        const res = await fetch(`{{ route('nametag.batch.archive') }}`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
          body: fd,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.ok) {
          alert(json.message || 'Gagal memulai pembuatan arsip.');
          elCreateArchive.disabled = false;
          return;
        }

        const archiveId = json.archive_id;
        elStatus.innerText = 'Arsip dikirim ke antrian...';

        // Poll archive status
        if (archivePoll) clearInterval(archivePoll);
        const tick = async () => {
          try {
            const sres = await fetch(`{{ url('/nametag/batch/archive') }}/${archiveId}/status`, { headers: { 'X-Requested-With':'XMLHttpRequest' } });
            if (!sres.ok) return;
            const sj = await sres.json();
            if (!sj.ok) return;
            elStatus.innerText = `Arsip: ${sj.status} (${sj.count} file)`;
            if (sj.download_url) {
              clearInterval(archivePoll);
              archivePoll = null;
              elStatus.innerHTML = `Arsip siap — <a href="${sj.download_url}" class="text-emerald-700 underline">Unduh arsip (${sj.count})</a>`;
                  elCreateArchive.disabled = false;
                  if (window.showToast) window.showToast('success', 'Arsip batch siap diunduh');
            }
          } catch (e) {
            console.error(e);
          }
        };

        tick();
        archivePoll = setInterval(tick, 2000);

      } catch (e) {
        console.error(e);
        alert('Terjadi kesalahan saat membuat arsip.');
        elCreateArchive.disabled = false;
      }
    }

    elCreateArchive.addEventListener('click', doCreateArchive);

    // helper kecil agar aman dari injection HTML
    function escapeHtml(str) {
      return (str ?? '').toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    // If URL contains batch parameter, use it to start polling immediately
    (function checkUrlBatch() {
      try {
        const params = new URLSearchParams(window.location.search);
        const b = params.get('batch') || params.get('batch_id');
        if (b) currentBatch = b;
      } catch (e) {}
    })();

    // initial load
    loadData();

    // If a batch id was provided in URL, start polling after load
    if (currentBatch) {
      elStatus.innerText = 'Menjalankan batch: ' + currentBatch;
      startPolling();
    }
  </script>
</x-layouts.admin>
