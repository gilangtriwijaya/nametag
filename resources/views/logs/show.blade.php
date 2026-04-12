{{-- resources/views/logs/show.blade.php --}}
<x-layouts.admin :title="'Detail Log Aktivitas'">

    @php
        $createdAt   = $payload['created_at'] ?? ($log->created_at?->toDateTimeString());
        $causer      = $payload['causer'] ?? null;
        $subject     = $payload['subject'] ?? null;
        $shortProps  = $payload['short_properties'] ?? null;
        $props       = $payload['properties'] ?? null;

        // Badge warna per event
        $event       = $payload['event'] ?? $log->event;
        $eventLabels = [
            'created'      => 'DIBUAT',
            'updated'      => 'DIUBAH',
            'deleted'      => 'DIHAPUS',
            'restored'     => 'DIPULIHKAN',
            'activated'    => 'AKTIFKAN',
            'deactivated'  => 'NONAKTIFKAN',
        ];
        $eventClasses = [
            'created'      => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'updated'      => 'bg-sky-100 text-sky-800 ring-sky-200',
            'deleted'      => 'bg-rose-100 text-rose-800 ring-rose-200',
            'restored'     => 'bg-amber-100 text-amber-800 ring-amber-200',
            'activated'    => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
            'deactivated'  => 'bg-slate-200 text-slate-800 ring-slate-300',
        ];

        $eventLabel = $eventLabels[$event] ?? strtoupper((string) $event ?: '-');
        $eventClass = $eventClasses[$event] ?? 'bg-gray-100 text-gray-800 ring-gray-200';

        // Pretty JSON untuk properties
        $propsPretty = '';
        if (is_array($props) || is_object($props)) {
            $propsPretty = json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif (is_string($props)) {
            $propsPretty = $props;
        } else {
            $propsPretty = json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $subjectTypeShort = $subject['type'] ?? $log->subject_type;
        $subjectId        = $subject['id']   ?? $log->subject_id;
        $subjectLabel     = $subject['label'] ?? null;

        $subjectBaseName = $subjectTypeShort ? class_basename($subjectTypeShort) : null;

        // Deteksi apakah subject adalah Employee (biar bisa disambungkan ke route employees.show)
        $canOpenEmployee = $subjectBaseName === 'Employee' && $subjectId && \Illuminate\Support\Facades\Route::has('employees.show');
    @endphp

    <div class="container mx-auto p-4 space-y-4">
        {{-- Header --}}
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Detail Log Aktivitas</h1>
                <p class="text-xs text-slate-500">
                    ID Log: {{ $log->id }} •
                    Log Name: <span class="font-mono">{{ $payload['log_name'] ?? $log->log_name ?? '—' }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('logs.index') }}"
                   class="px-3 py-2 rounded bg-slate-200 text-xs hover:bg-slate-300">
                    Kembali ke daftar
                </a>
                @if($canOpenEmployee)
                    <a href="{{ route('employees.show', $subjectId) }}"
                       class="px-3 py-2 rounded bg-blue-600 text-xs text-white hover:bg-blue-700">
                        Buka Pegawai
                    </a>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Panel kiri: Ringkasan --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- Ringkasan event --}}
                <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 sm:p-5">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-semibold ring-1 {{ $eventClass }}">
                            {{ $eventLabel }}
                        </span>

                        @if($createdAt)
                            <span class="text-xs text-slate-500">
                                Waktu aktivitas: {{ \Carbon\Carbon::parse($createdAt)->format('d M Y H:i:s') }} WIB
                            </span>
                        @endif
                    </div>

                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500 text-xs">Log Name</dt>
                            <dd class="font-mono text-sm">
                                {{ $payload['log_name'] ?? $log->log_name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 text-xs">Event</dt>
                            <dd class="text-sm">
                                {{ $event ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-slate-500 text-xs">Deskripsi</dt>
                            <dd class="text-sm">
                                {{ $payload['description'] ?? $log->description ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                {{-- Causer & Subject --}}
                <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 sm:p-5 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                        <div>
                            <h2 class="text-xs font-semibold text-slate-500 mb-1">Causer (Pelaku)</h2>
                            <dl class="space-y-1">
                                <div>
                                    <dt class="text-slate-400 text-[11px]">Nama</dt>
                                    <dd class="text-sm">
                                        {{ $causer['name'] ?? $log->causer->name ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400 text-[11px]">User ID</dt>
                                    <dd class="font-mono text-xs">
                                        {{ $causer['id'] ?? $log->causer_id ?? '—' }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div>
                            <h2 class="text-xs font-semibold text-slate-500 mb-1">Subject (Objek)</h2>
                            <dl class="space-y-1">
                                <div>
                                    <dt class="text-slate-400 text-[11px]">Tipe</dt>
                                    <dd class="text-sm">
                                        {{ $subjectBaseName ?? '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400 text-[11px]">ID</dt>
                                    <dd class="font-mono text-xs">
                                        {{ $subjectId ?? '—' }}
                                    </dd>
                                </div>
                                @if($subjectLabel)
                                    <div>
                                        <dt class="text-slate-400 text-[11px]">Label</dt>
                                        <dd class="text-sm">
                                            {{ $subjectLabel }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>

                    @if($shortProps)
                        <div class="border-t border-slate-100 pt-3">
                            <h3 class="text-xs font-semibold text-slate-500 mb-1">Ringkasan Perubahan</h3>
                            <p class="text-sm text-slate-700 leading-relaxed">
                                {{ $shortProps }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Panel kanan: Properties JSON --}}
            <div class="space-y-4">
                <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-4 sm:p-5 h-full flex flex-col">
                    <div class="flex items-center justify-between mb-2">
                        <h2 class="text-sm font-semibold text-slate-700">Properties (Detail Mentah)</h2>
                        <button type="button"
                                class="px-2 py-1 rounded bg-slate-100 text-[11px] text-slate-700 hover:bg-slate-200"
                                onclick="navigator.clipboard.writeText(document.getElementById('logPropsJson').textContent).then(()=>{this.innerText='Disalin'; setTimeout(()=>this.innerText='Salin JSON',1500)})">
                            Salin JSON
                        </button>
                    </div>

                    <pre id="logPropsJson"
                         class="flex-1 text-[11px] leading-relaxed bg-slate-50 rounded-lg p-3 overflow-auto max-h-[70vh]">
{{ $propsPretty }}
                    </pre>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
