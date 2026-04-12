{{-- resources/views/employees/partials/batch-ui.blade.php --}}
<div id="batch-ui-root">
<div id="nametagUiContainer" class="mb-4">
    <div id="nametagCombinedCard" class="p-3 rounded-lg border border-slate-200 bg-white">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold">Antrian & Progress Nametag</h3>
            <div class="text-xs text-slate-500"><span id="nametagLastRefreshed">--</span></div>
        </div>

        <div id="nametagCombinedBody" class="mt-3 space-y-3">
            <div id="nametagEmpty" class="text-sm text-slate-400">Tidak ada antrian.</div>

            <div id="nametagBatches" class="space-y-3"></div>
        </div>
    </div>
</div>

<script>
    (function(){
        const urls = (window.EMP_INDEX_CONFIG && window.EMP_INDEX_CONFIG.urls) || {};
        // Use server-provided URL or default (relative, no leading slash); ensure it ends with '/queued'
        const rawQueued = (urls.nametagBatchQueued || 'nametag/batch/queued');
        const queuedUrl = (function(u){
            try {
                if (!u) return 'nametag/batch/queued';
                let s = u.toString();
                if (!s.endsWith('/queued')) {
                    s = s.replace(/\/?$/, '') + '/queued';
                }
                return s;
            } catch (e) { return 'nametag/batch/queued'; }
        })(rawQueued);
        // helper: if app is hosted under a subpath (e.g. /anambas-id), some callers
        // may accidentally use a leading-absolute URL without the subpath. Build
        // an alternate candidate by prefixing the first path segment from current
        // location (if present).
        const queuedAltPrefix = (function(){
            try {
                const parts = (window.location && window.location.pathname) ? window.location.pathname.split('/') : [];
                // parts[0] is empty string because pathname starts with '/'
                if (parts.length > 1 && parts[1]) return '/' + parts[1];
            } catch (e) {}
            return '';
        })();
        const queuedAlt = (function(){
            if (!queuedAltPrefix) return null;
            try {
                // ensure exactly one slash between prefix and queuedUrl when queuedUrl is path-like
                if (queuedUrl.startsWith('http://') || queuedUrl.startsWith('https://')) return null;
                const prefix = queuedAltPrefix.replace(/\/$/, '');
                if (queuedUrl.startsWith('/')) return prefix + queuedUrl;
                return prefix + '/' + queuedUrl;
            } catch (e) { return null; }
        })();

        // keep last seen statuses to detect transitions (for toasts)
        const lastStatusById = {};

        function statusClass(status) {
            switch ((status||'').toLowerCase()) {
                case 'queued': return 'bg-slate-50 border-slate-200 text-slate-700';
                case 'processing': return 'bg-blue-50 border-blue-200 text-blue-700';
                case 'done': return 'bg-emerald-50 border-emerald-200 text-emerald-800';
                case 'failed': return 'bg-rose-50 border-rose-200 text-rose-800';
                default: return 'bg-slate-50 border-slate-200 text-slate-700';
            }
        }

        function renderBatchTemplate(b) {
            // support both legacy flat shape and new {meta,progress} shape
            const id = String(b.id || b.batch || (b.meta && b.meta.batch) || '');
            const meta = b.meta || {};
            const prog = b.progress || {};
            const total = (b.total ?? prog.total ?? meta.total ?? 0) || 0;
            const done = (b.processed ?? prog.processed ?? prog.done ?? 0) || 0;
            const fail = (b.failed ?? prog.fail ?? 0) || 0;
            const status = (b.status || prog.status || meta.status || 'queued') || 'queued';
            const pct = total > 0 ? Math.round((done / total) * 100) : (b.percent ?? prog.percent ?? 0);
            const created = (b.created_at || meta.created_at || prog.started_at) ? new Date(b.created_at || meta.created_at || prog.started_at).toLocaleString() : '';
            const user = meta.user || b.user || b.user_id || null;
            const eta = prog.eta ?? null;
            const cls = statusClass(status);

            function fmtEta(sec) {
                if (!sec && sec !== 0) return null;
                if (sec < 60) return `${sec}s`;
                const m = Math.floor(sec / 60);
                const s = sec % 60;
                return `${m}m ${s}s`;
            }

            return `
                <div data-batch-id="${id}" class="p-3 border rounded ${cls}">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium">Batch ${id}${user ? ' · oleh ' + user : ''}</div>
                        <div class="text-xs"><span class="batch-created">${created}</span> · <span class="font-semibold uppercase batch-status">${status}</span></div>
                    </div>
                    <div class="mt-2">
                        <div class="w-full bg-white/60 rounded h-3 overflow-hidden border border-slate-100">
                            <div class="batch-progress-bar h-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white text-[11px] flex items-center justify-center transition-all duration-400 ease-in-out" style="width:${pct}%">${pct}%</div>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-4">
                            <div class="text-sm text-slate-600 progress-label">Progress: <span class="prog-pct font-semibold">${pct}%</span> — <span class="prog-state">${status}</span></div>
                            <div class="text-xs text-slate-500 eta" data-eta-seconds="${eta ?? ''}">ETA: <span class="eta-text">${eta ? fmtEta(eta) : '—'}</span></div>
                        </div>
                        <div class="mt-2 text-xs text-slate-500 batch-stats">${done}/${total} diproses · ${fail} gagal</div>
                    </div>
                </div>`;
        }

        async function fetchQueued() {
            try {
                let res = await fetch(queuedUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                // fallback: if 404 and we have an alternate prefixed URL, try that
                if (res.status === 404 && queuedAlt) {
                    try { res = await fetch(queuedAlt, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }); } catch (ee) { /* ignore */ }
                }
                if (!res.ok) throw new Error('failed');
                const j = await res.json().catch(() => ({}));
                if (!j || !Array.isArray(j)) {
                    // allow both {ok,batches:[]} and plain array responses
                    const batches = j.batches || [];
                    return renderBatches(batches);
                }
                return renderBatches(j || []);
            } catch (e) {
                try {
                    const empty = document.getElementById('nametagEmpty');
                    const container = document.getElementById('nametagBatches');
                    if (empty) { empty.textContent = 'Tidak ada antrian.'; empty.classList.remove('hidden'); }
                    if (container) container.innerHTML = '';
                } catch (err) {}
                console.debug('fetchQueued failed', e);
            }
        }
        function renderBatches(batches) {
            const container = document.getElementById('nametagBatches');
            const empty = document.getElementById('nametagEmpty');
            const last = document.getElementById('nametagLastRefreshed');
            if (!container) return;
            if (!batches || batches.length === 0) {
                if (empty) { empty.textContent = 'Tidak ada antrian.'; empty.classList.remove('hidden'); }
                container.innerHTML = '';
                last && (last.textContent = new Date().toLocaleTimeString());
                return;
            }

            if (empty) { empty.classList.add('hidden'); empty.textContent = ''; }

            // Build map of incoming ids
            const incomingIds = new Set((batches || []).map(b => String(b.id || b.batch)));

            // Remove DOM elements not present anymore
            Array.from(container.children).forEach(ch => {
                const bid = String(ch.getAttribute('data-batch-id') || '');
                if (bid && !incomingIds.has(bid)) {
                    // fade out then remove
                    try { ch.style.transition = 'opacity .3s ease'; ch.style.opacity = '0'; } catch (e) {}
                    setTimeout(() => { ch.remove(); }, 300);
                }
            });

            // Upsert elements
            batches.forEach(b => {
                const id = String(b.id || b.batch || '');
                let el = container.querySelector(`[data-batch-id="${id}"]`);
                const pct = Math.round((b.processed || 0) && (b.total ? ((b.processed||0)/b.total)*100 : 0)) || 0;
                const status = (b.status || 'queued').toLowerCase();

                if (!el) {
                    // create new
                    const html = renderBatchTemplate(b);
                    const wrap = document.createElement('div');
                    wrap.innerHTML = html;
                    const node = wrap.firstElementChild;
                    container.appendChild(node);
                    el = node;

                    // highlight & scroll into view for new batch
                    try {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.classList.add('ring-4', 'ring-indigo-200');
                        setTimeout(() => el.classList.remove('ring-4', 'ring-indigo-200'), 1200);
                    } catch (e) {}
                } else {
                    // update class for status
                    try { el.className = `p-3 border rounded ${statusClass(status)}`; } catch (e) {}
                    // update created/timestamps if present
                    const createdEl = el.querySelector('.batch-created');
                    if (createdEl && b.created_at) createdEl.textContent = new Date(b.created_at).toLocaleString();
                    // update status text
                    const statusEl = el.querySelector('.batch-status');
                    if (statusEl) statusEl.textContent = status;
                    // update stats
                    const statsEl = el.querySelector('.batch-stats');
                    if (statsEl) statsEl.textContent = `${b.processed || 0}/${b.total || 0} diproses · ${b.failed || 0} gagal`;
                    // update progress bar
                    const bar = el.querySelector('.batch-progress-bar');
                    if (bar) {
                        bar.style.width = pct + '%';
                        bar.textContent = pct + '%';
                    }
                    // update small percent label and state
                    const pctLabel = el.querySelector('.prog-pct');
                    if (pctLabel) pctLabel.textContent = pct + '%';
                    const stateLabel = el.querySelector('.prog-state');
                    if (stateLabel) stateLabel.textContent = status;
                    // update ETA attribute if provided
                    try {
                        const etaEl = el.querySelector('.eta');
                        const newEta = (b.progress && (b.progress.eta ?? b.progress.eta_seconds)) || b.eta || null;
                        if (etaEl) {
                            if (newEta !== undefined && newEta !== null) {
                                etaEl.setAttribute('data-eta-seconds', String(newEta));
                                const t = etaEl.querySelector('.eta-text');
                                if (t) t.textContent = (function(s){ if (s === null || s === undefined) return '—'; if (s < 60) return s + 's'; const m=Math.floor(s/60); const rs=s%60; return m + 'm ' + rs + 's'; })(parseInt(newEta,10));
                            } else {
                                etaEl.removeAttribute('data-eta-seconds');
                                const t = etaEl.querySelector('.eta-text'); if (t) t.textContent = '—';
                            }
                        }
                    } catch (e) { /* ignore ETA update errors */ }
                }

                // Toast transitions: skip on firstRun to avoid initial noise
                try {
                    const prev = lastStatusById[id];
                    if (!window.__nametag_first_run) window.__nametag_first_run = true;
                    const firstRun = window.__nametag_first_run === true;
                    if (!firstRun && prev !== status) {
                        if (status === 'processing') {
                            // subtle info toast when processing starts
                            window.showToast && window.showToast('info', `Batch ${id} mulai diproses.`, { duration: 3000 });
                        } else if (status === 'done' || status === 'finished' || status === 'completed') {
                            window.showToast && window.showToast('success', `Batch ${id} selesai (${b.processed || 0}/${b.total || 0})`);
                            try { if (window.nametag_fetchEmployees) window.nametag_fetchEmployees(); } catch(e) {}
                        } else if (status === 'failed' || status === 'error') {
                            window.showToast && window.showToast('error', `Batch ${id} gagal. Cek detail.`);
                        }
                    }
                    lastStatusById[id] = status;
                } catch (e) { console.debug('toast detect failed', e); }
            });

            // clear firstRun after initial render
            if (window.__nametag_first_run === true) window.__nametag_first_run = false;

            if (last) last.textContent = new Date().toLocaleTimeString();
        }

        // initial + polling (single source truth)
        // Use a scheduler that pauses when tab is hidden to reduce load.
        let __pollTimer = null;
        const __pollInterval = 2000;

        async function __pollLoop() {
            try {
                if (document.hidden) {
                    // skip fetching when tab not visible, but keep timer to check later
                    __pollTimer = setTimeout(__pollLoop, __pollInterval);
                    return;
                }
                await fetchQueued();
            } catch (e) {
                console.debug('poll error', e);
            }
            __pollTimer = setTimeout(__pollLoop, __pollInterval);
        }

        // initial run and start loop
        fetchQueued().finally(() => { __pollLoop(); });

        // ETA ticker: update visible ETA countdowns every second
        (function startEtaTicker(){
            try {
                setInterval(() => {
                    try {
                        document.querySelectorAll('.eta[data-eta-seconds]').forEach(el => {
                            const raw = el.getAttribute('data-eta-seconds');
                            if (!raw) return;
                            let s = parseInt(raw, 10);
                            if (isNaN(s)) return;
                            if (s > 0) s = s - 1;
                            // update dataset
                            el.setAttribute('data-eta-seconds', String(s));
                            const t = el.querySelector('.eta-text');
                            if (t) {
                                if (s <= 0) t.textContent = '—';
                                else if (s < 60) t.textContent = `${s}s`;
                                else { const m = Math.floor(s/60); const rs = s%60; t.textContent = `${m}m ${rs}s`; }
                            }
                        });
                    } catch (e) {}
                }, 1000);
            } catch (e) { console.debug('eta ticker failed', e); }
        })();

        // resume immediately when tab becomes visible
        document.addEventListener('visibilitychange', function(){
            if (!document.hidden) {
                try { if (__pollTimer) { clearTimeout(__pollTimer); } } catch(e){}
                __pollLoop();
            }
        });

        // expose helpers for external callers (dispatch flow) to poll a specific batch
        try {
            window.nametag_fetchQueued = fetchQueued;
            window.nametag_pollForBatch = function(batchId) {
                if (!batchId) return null;
                let stopped = false;
                const tid = setInterval(async () => {
                    try {
                            const res = await fetch(queuedUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                        if (!res.ok) return;
                        const j = await res.json().catch(() => ({}));
                        const batches = Array.isArray(j) ? j : (j.batches || []);
                        // render all so DOM stays consistent
                        try { renderBatches(batches); } catch (e) {}
                        const target = batches.find(b => String(b.id || b.batch) === String(batchId));
                        const status = (target && (target.status || '')).toLowerCase();
                        if (!target || status === 'done' || status === 'finished' || status === 'failed' || status === 'error') {
                            clearInterval(tid);
                            stopped = true;
                            try { if (window.nametag_fetchEmployees) window.nametag_fetchEmployees(); } catch(e) {}
                        }
                    } catch (e) { console.debug('pollForBatch error', e); }
                }, __pollInterval);
                return {
                    stop() { if (!stopped) clearInterval(tid); }
                };
            };
        } catch (e) { console.debug('expose helpers failed', e); }
    })();
</script>
</div>
