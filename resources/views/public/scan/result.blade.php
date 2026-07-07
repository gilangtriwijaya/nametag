<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Verifikasi Pegawai – Anambas-ID</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-50 text-slate-800">

<div class="max-w-3xl mx-auto p-4 sm:p-6">

  <header class="flex items-center gap-3 mb-6">
    <img src="{{ asset('images/logo-pemda.png') }}" alt="Logo" class="h-10 w-10">
    <div>
      <h1 class="text-xl font-semibold">Verifikasi Pegawai</h1>
      <p class="text-sm text-slate-500">
        Hasil scan token:
        <span class="font-mono">{{ \Illuminate\Support\Str::limit($token, 16, '…') }}</span>
      </p>
    </div>
  </header>

  @php
    /* ---------------------------------------------------------
       HELPERS
    --------------------------------------------------------- */

    // Format NIP lokal (18 digit → 8-6-1-3)
    $formatNip = function (?string $nip): string {
        $nip = preg_replace('/\D+/', '', (string) $nip);
        if ($nip === '') return '—';
        if (strlen($nip) === 18) {
            return trim(sprintf(
                '%s %s %s %s',
                substr($nip,0,8),
                substr($nip,8,6),
                substr($nip,14,1),
                substr($nip,15,3)
            ));
        }
        return trim(implode(' ', str_split($nip, 3)));
    };

    // Asset + cache-buster
    $verAsset = function (?string $p) {
        if (!$p) return null;
        if (preg_match('#^https?://#i', $p)) return $p;
        $rel = ltrim($p,'/');
        $abs = public_path($rel);
        if (is_file($abs)) {
            return asset($rel).'?v='.(filemtime($abs) ?: time());
        }
        return asset($rel);
    };

    // Status badge
    $badge = [
      'ok'                => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
      'revoked'           => 'bg-rose-100 text-rose-800 ring-rose-200',
      'expired'           => 'bg-amber-100 text-amber-800 ring-amber-200',
      'inactive_employee' => 'bg-slate-200 text-slate-800 ring-slate-300',
      'not_found'         => 'bg-gray-100 text-gray-700 ring-gray-200',
    ][$result] ?? 'bg-gray-100 text-gray-700 ring-gray-200';

    $label = [
      'ok'                => 'VALID',
      'revoked'           => 'DICABUT',
      'expired'           => 'KADALUARSA',
      'inactive_employee' => 'PEGAWAI NONAKTIF',
      'not_found'         => 'TOKEN TIDAK DITEMUKAN',
    ][$result] ?? 'TIDAK VALID';

    /* ---------------------------------------------------------
       NAMETAG FRONT/BACK
    --------------------------------------------------------- */
    $empId = $data->employee_id ?? null;
    $ntFrontUrl = $ntBackUrl = null;

    if ($empId) {
        foreach (["nametag/front/$empId.png", "anambas-id/nametag/front/$empId.png"] as $seg) {
            if (is_file(public_path($seg))) {
                $ntFrontUrl = asset($seg).'?v='.(filemtime(public_path($seg)) ?: time());
                break;
            }
        }
        foreach (["nametag/back/$empId.png", "anambas-id/nametag/back/$empId.png"] as $seg) {
            if (is_file(public_path($seg))) {
                $ntBackUrl = asset($seg).'?v='.(filemtime(public_path($seg)) ?: time());
                break;
            }
        }
    }

    /* ---------------------------------------------------------
       FOTO PEGAWAI (gunakan accessor foto_path)
    --------------------------------------------------------- */
    $fotoUrl = null;
    if ($result === 'ok' && $data) {
        if (!empty($data->foto_path)) {
            $fotoUrl = preg_match('#^https?://#i', $data->foto_path)
                ? $data->foto_path
                : $verAsset($data->foto_path);
        }
    }

    /* ---------------------------------------------------------
       NAMA FINAL (gunakan kolom employee_name dari ScanController)
    --------------------------------------------------------- */
    $displayName = $data->employee_name ?? '—';

  @endphp


  <div class="rounded-2xl bg-white ring-1 ring-slate-200 shadow-sm">

    <div class="flex flex-col md:flex-row gap-6 p-6">

      {{-- -----------------------------------------------------
           KOLOM KIRI (Foto + Nametag)
      ------------------------------------------------------ --}}
      <div class="md:w-64 md:flex-shrink-0 space-y-3">

        {{-- FOTO --}}
        <button type="button"
                class="aspect-square w-full overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"
                @if($fotoUrl) onclick="openPreview('{{ $fotoUrl }}')" @endif>
          @if($fotoUrl)
            <img src="{{ $fotoUrl }}" alt="Foto Pegawai" class="w-full h-full object-cover">
          @else
            <div class="w-full h-full grid place-content-center text-slate-400">No Photo</div>
          @endif
        </button>

        {{-- NAMETAG --}}
        <div class="rounded-xl bg-white ring-1 ring-slate-200 p-2">
          <div class="grid grid-cols-2 gap-2">

            {{-- Depan --}}
            <button type="button"
                    class="aspect-[2/3] w-full overflow-hidden rounded-lg ring-1 ring-slate-200 bg-slate-50 grid place-content-center"
                    @if($ntFrontUrl) onclick="openPreview('{{ $ntFrontUrl }}')" @endif>
              @if($ntFrontUrl)
                <img src="{{ $ntFrontUrl }}" class="w-full h-full object-cover">
              @else
                <span class="text-[10px] text-slate-400 px-2 text-center">Nametag depan tidak tersedia</span>
              @endif
            </button>

            {{-- Belakang --}}
            <button type="button"
                    class="aspect-[2/3] w-full overflow-hidden rounded-lg ring-1 ring-slate-200 bg-slate-50 grid place-content-center"
                    @if($ntBackUrl) onclick="openPreview('{{ $ntBackUrl }}')" @endif>
              @if($ntBackUrl)
                <img src="{{ $ntBackUrl }}" class="w-full h-full object-cover">
              @else
                <span class="text-[10px] text-slate-400 px-2 text-center">Nametag belakang tidak tersedia</span>
              @endif
            </button>

          </div>
        </div>

      </div><!-- kiri -->


      {{-- -----------------------------------------------------
           KOLOM KANAN (Detail)
      ------------------------------------------------------ --}}
      <div class="flex-1 min-w-0">

        <div class="flex flex-wrap items-center gap-2 mb-3">
          <span class="px-2.5 py-1 rounded-full text-xs font-semibold ring-1 {{ $badge }}">{{ $label }}</span>
          <span class="text-xs text-slate-500">
            Diverifikasi: @datetime($scanned_at) WIB
          </span>
        </div>

        {{-- DETAIL JIKA VALID --}}
        @if($result === 'ok' && $data)
          <h2 class="text-2xl font-bold leading-tight">{{ $displayName }}</h2>

          <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">

            <div>
              <dt class="text-slate-500">
                {{ (($data->employee_status_kepegawaian ?? '') === 'PPPK') ? 'NIPPPK' : 'NIP' }}
              </dt>
              <dd class="font-mono">
                {{ $formatNip($data->employee_nip ?? null) }}
              </dd>
            </div>

            <div>
              <dt class="text-slate-500">Organisasi Perangkat Daerah</dt>
              <dd>{{ $data->opd_name ?? '—' }}</dd>
            </div>

            <div>
              <dt class="text-slate-500">Jabatan</dt>
              <dd>{{ $data->employee_position ?? '—' }}</dd>
            </div>

            <div>
              <dt class="text-slate-500">Jenis Jabatan</dt>
              <dd>{{ $data->position_type ?? '—' }}</dd>
            </div>

            <div class="sm:col-span-2">
              <dt class="text-slate-500">Unit OPD</dt>
              <dd>{{ $data->nama_unit_opd ?? '—' }}</dd>
            </div>

          </dl>

        @else
          {{-- jika token invalid --}}
          <div class="prose prose-sm max-w-none mt-4">
            @switch($result)
              @case('revoked') <p>Token ini sudah <strong>dicabut</strong>.</p>@break
              @case('expired') <p>Token ini sudah <strong>kadaluwarsa</strong>.</p>@break
              @case('inactive_employee') <p>Status pegawai <strong>NONAKTIF</strong>.</p>@break
              @default <p>Token tidak valid atau tidak ditemukan.</p>
            @endswitch
          </div>
        @endif

      </div><!-- kanan -->

    </div>
  </div>

  <footer class="mt-6 text-xs text-slate-500 leading-relaxed">
    <div>Halaman publik untuk verifikasi keaslian identitas pegawai.</div>
    <div class="mt-1">
      Dikembangkan oleh
      <span class="font-medium text-slate-600">
        Bagian Organisasi – Sekretariat Daerah Kabupaten Kepulauan Anambas
      </span>.
    </div>
  </footer>

</div><!-- container -->


{{-- -----------------------------------------------------------
     MODAL PREVIEW
------------------------------------------------------------ --}}
<div id="imgPreviewModal"
     class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/70 p-4"
     onclick="if(event.target.id==='imgPreviewModal'){closePreview();}">

  <img id="imgPreviewTarget" src="" alt="Preview"
       class="max-h-[90vh] max-w-[90vw] rounded shadow-2xl bg-white">
</div>

<script>
  function openPreview(src){
    const m=document.getElementById('imgPreviewModal');
    const i=document.getElementById('imgPreviewTarget');
    i.src=src;
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.addEventListener('keydown',escClose);
  }
  function closePreview(){
    const m=document.getElementById('imgPreviewModal');
    const i=document.getElementById('imgPreviewTarget');
    i.src='';
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.removeEventListener('keydown',escClose);
  }
  function escClose(e){ if(e.key==='Escape') closePreview(); }
</script>

</body>
</html>
