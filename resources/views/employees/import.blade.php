<x-layouts.admin :title="'Import Pegawai'">
    <x-slot:header>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Import Pegawai (Preview → Confirm)</h1>
            <p class="text-sm text-slate-500">Unggah file Excel/CSV dan lihat preview sebelum menyimpan.</p>
        </div>
    </x-slot:header>

    <div class="container mx-auto">
        @if(session('error'))
            <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <form action="{{ route('employees.import.preview') }}" method="post" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="file" class="block mb-1 text-sm font-medium">File Excel/CSV</label>
                <input type="file" name="file" id="file" class="w-full" required>
                <div class="text-xs text-slate-500 mt-1">Format: XLSX, XLS, CSV. Kolom minimal: nip, nama_lengkap</div>
            </div>
            <div class="flex gap-2 items-center">
                <button id="btnPreview" class="inline-flex items-center px-4 py-2 rounded bg-sky-600 text-white">Preview</button>
                <a href="{{ route('employees.import.template') }}" class="inline-flex items-center px-3 py-2 rounded border border-slate-300 text-sm text-slate-700 hover:bg-slate-50">Download Template CSV</a>
            </div>
        </form>

        <hr class="my-4">
        <h5 class="font-semibold">Contoh header CSV yang direkomendasikan</h5>
        <pre class="bg-slate-50 p-2 rounded">opd_id,opd_unit_id,nip,nama_lengkap,jabatan,jabatan_type,email,tgl_lahir,no_hp,alamat,pangkat,golongan</pre>

            <div class="mt-3 rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">
            <strong>Kolom penting:</strong>
            <ul class="mt-1 list-disc pl-5">
                <li><strong>opd_id</strong> — ID OPD lokal (harus sudah tersedia di database)</li>
                <li><strong>opd_unit_id</strong> — ID unit OPD lokal (opsional; jika tidak ada, biarkan kosong)</li>
                <li><strong>nip</strong> — NIP pegawai (format: 18 digit)</li>
                <li><strong>nama_lengkap</strong> — Nama lengkap</li>
                <li><strong>jabatan_type</strong> — Tipe jabatan (PELAKSANA, FUNGSIONAL, PENGAWAS, ADMINISTRATOR, PIMPINAN TINGGI PRATAMA)</li>
            </ul>
            <div class="mt-2 text-xs text-slate-600">Catatan: Jika pegawai tidak memiliki unit OPD, biarkan <strong>opd_unit_id</strong> kosong (jangan isi 0). Jika Anda ingin mengelompokkan pegawai tanpa unit, pertimbangkan menambahkan unit khusus di menu OPD Unit terlebih dahulu.</div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const form = document.querySelector('form');
        const fileInput = document.getElementById('file');
        const btn = document.getElementById('btnPreview');

        const progressBar = document.createElement('div');
        progressBar.style.display = 'none';
        progressBar.innerHTML = '<div class="w-full bg-slate-200 rounded mt-2"><div id="uploadProgress" class="bg-sky-600 text-white text-xs px-2 py-1 rounded" style="width:0%">0%</div></div>';
        form.appendChild(progressBar);

        const spinner = document.createElement('div');
        spinner.style.display = 'none';
        spinner.innerHTML = '<div class="mt-2 text-sm">\\u{1F4E4} Mengunggah…</div>';
        form.appendChild(spinner);

        form.addEventListener('submit', function(e){
            e.preventDefault();
            if (!fileInput.files.length) return alert('Pilih file terlebih dahulu');

            const fd = new FormData();
            fd.append('file', fileInput.files[0]);
            fd.append('_token', '{{ csrf_token() }}');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '{{ route('employees.import.upload') }}', true);

            xhr.upload.addEventListener('progress', function(evt){
                if (evt.lengthComputable) {
                    const percent = Math.round((evt.loaded / evt.total) * 100);
                    progressBar.style.display = 'block';
                    const p = document.getElementById('uploadProgress');
                    p.style.width = percent + '%';
                    p.textContent = percent + '%';
                }
            });

            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    spinner.style.display = 'none';
                    btn.disabled = false;
                    if (xhr.status === 200) {
                        const res = JSON.parse(xhr.responseText);
                        if (res.success) {
                            window.location = res.preview_url;
                        } else {
                            alert('Gagal: ' + (res.message || 'Unknown'));
                        }
                    } else {
                        let msg = 'Gagal mengunggah file';
                        try { msg = JSON.parse(xhr.responseText).message || msg } catch(e){}
                        alert(msg);
                    }
                }
            };

            spinner.style.display = 'block';
            btn.disabled = true;
            xhr.send(fd);
        });
    });
    </script>
</x-layouts.admin>
