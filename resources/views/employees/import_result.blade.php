<x-layouts.admin :title="'Hasil Import Pegawai'">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Hasil Import Pegawai</h1>
            <p class="text-sm text-slate-500">Status pemrosesan import</p>
        </div>
    </x-slot:header>

    <div class="container mx-auto">
        <div id="jobArea">
            <p>Job ID: <strong id="jobId">{{ $job_id ?? '—' }}</strong></p>
            <div id="jobStatus" class="mb-2">Menunggu...</div>
            <div class="mt-2">Progress: <span id="jobProgress">0</span>%</div>
            <div id="jobResult" style="display:none">
                <h4 class="mt-3">Hasil</h4>
                <ul>
                    <li>Dibuat: <span id="created">0</span></li>
                    <li>Diperbarui: <span id="updated">0</span></li>
                    <li>Dilewati: <span id="skipped">0</span></li>
                </ul>
                <div id="errorsList" class="mt-2"></div>
                <div id="errorsDownload" class="mt-2"></div>
                <a href="{{ route('employees.index') }}" class="inline-flex items-center px-3 py-2 rounded bg-sky-600 text-white">Kembali ke Daftar Pegawai</a>
            </div>
        </div>

        <script>
        (function(){
            const jobId = '{{ $job_id ?? '' }}';
            if (!jobId) return;
            const statusEl = document.getElementById('jobStatus');
            const poll = function(){
                fetch('{{ route('employees.import.job.status', ['jobId' => 'JOBID']) }}'.replace('JOBID', jobId))
                    .then(r => r.json())
                    .then(data => {
                        statusEl.textContent = data.status || 'unknown';
                        if (data.status === 'done') {
                            document.getElementById('jobResult').style.display = '';
                            document.getElementById('jobProgress').textContent = data.progress || 100;
                            if (data.result) {
                                document.getElementById('created').textContent = data.result.created || 0;
                                document.getElementById('updated').textContent = data.result.updated || 0;
                                document.getElementById('skipped').textContent = data.result.skipped || 0;
                                if (data.result.errors && data.result.errors.length) {
                                    const el = document.getElementById('errorsList');
                                    el.innerHTML = '<h5>Errors</h5><ul>' + data.result.errors.map(e=>'<li>Baris '+e.row+': '+e.errors.join('; ')+'</li>').join('') + '</ul>';
                                }
                            }
                            if (data.errors_file) {
                                document.getElementById('errorsDownload').innerHTML = '<a class="inline-flex items-center px-3 py-2 rounded border" href="{{ route('employees.import.errors', ['file' => 'FILE']) }}'.replace('FILE', data.errors_file) + '">Download Error CSV</a>';
                            }
                        } else if (data.status === 'failed') {
                            statusEl.textContent = 'Gagal: ' + (data.message || 'unknown');
                            document.getElementById('jobResult').style.display = '';
                        } else if (data.status === 'running') {
                            document.getElementById('jobProgress').textContent = data.progress || 0;
                        } else {
                            setTimeout(poll, 1500);
                        }
                    }).catch(err=>{ statusEl.textContent = 'Error'; console.error(err); setTimeout(poll, 3000); });
            };
            poll();
        })();
        </script>
    </div>
</x-layouts.admin>
