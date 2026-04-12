<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use App\Services\Nametag\NametagPathHelpers;
use App\Services\Nametag\NametagTextLayout;
use App\Services\Nametag\NametagQrHelpers;

class NametagRenderService
{
    use NametagPathHelpers;
    use NametagTextLayout;
    use NametagQrHelpers;

    private const PHOTO_BIAS_TOP    = 0.01; // 0..1
    private const PHOTO_HEADROOM_FR = 0.03; // 3%

    /* =========================
     *  FRONT
     * ========================= */

    public function renderFront(Employee $e, ?string $templatePath): bool
    {
        // Clear PHP stat cache to ensure we detect the latest file modifications
        clearstatcache(true);
        $this->ensureDirs();

        // Normalisasi template ke public/templates/...
        $templatePath = $this->resolveTemplatePath($templatePath, 'front');
        if (!$templatePath) {
            Log::error('nametag: front template missing', [
                'employee_id' => $e->id,
            ]);
            return false;
        }

        $photoPath = $this->resolvePhoto($e);
        if (!$photoPath) return false;

        $tpl      = $this->loadImage($templatePath);
        $cfgFront = config('nametag.templates.front');
        $ppm      = $this->ppm($tpl, $cfgFront);

        // default line-height sisi depan
        $lhDefaultFront = (float)(
            config('nametag.line_height.front')
            ?? config('nametag.line_height.default', 1.25)
        );

        $foto = $this->loadImage($photoPath);
        if (!$tpl || !$foto) {
            if ($tpl)  imagedestroy($tpl);
            if ($foto) imagedestroy($foto);
            Log::error('nametag: front image load fail', [
                'employee_id' => $e->id,
                'tpl_ok'      => (bool)$tpl,
                'foto_ok'     => (bool)$foto,
            ]);
            return false;
        }

        // Foto: COVER + top-bias + headroom
        $slot = $cfgFront['photo'] ?? null;
        if (!$slot) {
            imagedestroy($tpl);
            imagedestroy($foto);
            Log::warning('nametag: front slot missing');
            return false;
        }

        $x = (int)round($slot['x'] * $ppm);
        $y = (int)round($slot['y'] * $ppm);
        $w = (int)round($slot['w'] * $ppm);
        $h = (int)round($slot['h'] * $ppm);

        $fw = imagesx($foto);
        $fh = imagesy($foto);

        $scale = max($w / max(1, $fw), $h / max(1, $fh));
        $srcW  = (int)round($w / $scale);
        $srcH  = (int)round($h / $scale);
        $srcX  = (int)max(0, round(($fw - $srcW) / 2));

        $excessY  = max(0, $fh - $srcH);
        $headroom = (int)round(($h * self::PHOTO_HEADROOM_FR) / max(1, $scale));
        $srcY     = (int)round(min(max(0, $excessY * self::PHOTO_BIAS_TOP), max(0, $excessY - $headroom)));

        $tmp = imagecreatetruecolor($w, $h);
        imagealphablending($tmp, false);
        imagesavealpha($tmp, true);
        $fill = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
        imagefill($tmp, 0, 0, $fill);
        imagecopyresampled($tmp, $foto, 0, 0, $srcX, $srcY, $w, $h, $srcW, $srcH);
        imagedestroy($foto);
        imagecopy($tpl, $tmp, $x, $y, 0, 0, $w, $h);
        imagedestroy($tmp);

        // === TEXTS
        $textMap = \App\Support\NametagData::buildFront($e);
        // FIX: Use array_merge to create a COPY, not reference to config
        // If we modify $items in-place (e.g., adding __scaled_px), 
        // it must NOT affect the cached config for subsequent renders
        $items   = array_merge([], $cfgFront['texts'] ?? []);

        $mapIdx = [];
        foreach ($items as $i => $it) {
            $k = $it['key'] ?? null;
            if (in_array($k, ['nama', 'nip', 'jabatan'], true)) $mapIdx[$k] = $i;
        }

        // Cari label NIP
        $nipLabelIdx = null;
        foreach ($items as $i => $it) {
            if (($it['key'] ?? null) === 'nip_label') {
                $nipLabelIdx = $i;
                break;
            }
        }
        if ($nipLabelIdx === null) {
            foreach ($items as $i => $it) {
                $t = isset($it['text']) ? trim(mb_strtoupper($it['text'])) : '';
                if ($t === 'NIP.' || $t === 'NIP. ') {
                    $nipLabelIdx = $i;
                    break;
                }
            }
        }

        // === Gabung label NIP + nilai NIP jadi satu baris ===
        if (isset($mapIdx['nip'])) {
            $nipIdx   = $mapIdx['nip'];
            $nipValue = (string)($textMap['nip'] ?? '');

            if ($nipValue !== '') {
                // Prefer employee-driven label (PPPK -> 'NIPPPK.'). If template contains
                // a label item it's ignored in favor of the rule so templates don't
                // accidentally hardcode the old string.
                $labelText = (string) ($e->nip_label ?? 'NIP.');
                if ($nipLabelIdx !== null) {
                    // clear template label item to avoid double-draw
                    $items[$nipLabelIdx]['text'] = null;
                    $items[$nipLabelIdx]['flow'] = false;
                }

                // simpan gabungan ke textMap['nip'] → otomatis dipakai saat render
                $textMap['nip'] = trim($labelText . ' ' . $nipValue);

                // pastikan align nip center (kalau belum di-set di config)
                if (empty($items[$nipIdx]['align'])) {
                    $items[$nipIdx]['align'] = 'center';
                }
            }
        }

        // Pre-scaling trio Nama–NIP–Jabatan
        if (isset($mapIdx['nama']) && isset($mapIdx['jabatan'])) {
            $itNama = $items[$mapIdx['nama']];
            $itJab  = $items[$mapIdx['jabatan']];
            $itNip  = isset($mapIdx['nip']) ? $items[$mapIdx['nip']] : null;

            $getBasePx = function (array $it) use ($ppm) {
                $mm = (float)($it['font']['size'] ?? 5.5);
                return $mm * $ppm * 0.92;
            };

                $fontNama = $this->resolveFont((bool)($itNama['font']['bold'] ?? false), $itNama['font']['key'] ?? null)
                    ?: $this->resolveFont(false, $itNama['font']['key'] ?? null);
                $fontJab  = $this->resolveFont((bool)($itJab['font']['bold'] ?? false), $itJab['font']['key'] ?? null)
                    ?: $this->resolveFont(false, $itJab['font']['key'] ?? null);

            $baseNamaPx = $getBasePx($itNama);
            $baseJabPx  = $getBasePx($itJab);
            $baseNipPx  = $itNip ? $getBasePx($itNip) : $baseNamaPx;

            $namaVal = (string)($textMap['nama'] ?? '');
            $jabVal  = (string)($textMap['jabatan'] ?? '');

            $wNama = (int)round(($itNama['w'] ?? 9999) * $ppm);
            $wJab  = (int)round(($itJab['w'] ?? 9999) * $ppm);

            $fitNamaPx = $this->fitSingleLinePx($namaVal, $fontNama, $baseNamaPx, $wNama);
            $fitJabPx  = $this->fitWrappedLinesPx($jabVal, $fontJab, $baseJabPx, $wJab, 2);

            $scaleNama = $fitNamaPx / max(1e-6, $baseNamaPx);
            $scaleJab  = $fitJabPx / max(1e-6, $baseJabPx);
            $scale     = min($scaleNama, $scaleJab, 1.0);

            $items[$mapIdx['nama']]['__scaled_px'] = $baseNamaPx * $scale;
            if ($itNip) {
                $items[$mapIdx['nip']]['__scaled_px'] = $baseNipPx * $scale;
            }
            $items[$mapIdx['jabatan']]['__scaled_px'] = $baseJabPx * $scale;

            if ($nipLabelIdx !== null) {
                $baseLblPx                          = $getBasePx($items[$nipLabelIdx]);
                $items[$nipLabelIdx]['__scaled_px'] = $baseLblPx * $scale;
                $items[$nipLabelIdx]['wrap']        = 1;
            }
            $items[$mapIdx['nama']]['wrap']    = 1;
            $items[$mapIdx['jabatan']]['wrap'] = 2;

            // Special layout rule: if Jabatan fits in a single line with base font
            // size, then nudge NIP and Jabatan vertically (+1mm for NIP, +2mm for Jabatan).
            // Note: per request, we assume Nama and NIP are already single-line and
            // only check `jabatan` for the tweak.
            try {
                $fontJabPath = $fontJab;
                $wJabPx = $wJab;
                $linesJab = $this->wrapLines($jabVal, $wJabPx, $fontJabPath, $baseJabPx);
                $fitsJabOneLine = (count($linesJab) === 1);

                if ($fitsJabOneLine) {
                    if ($itNip && isset($items[$mapIdx['nip']])) {
                        $items[$mapIdx['nip']]['y'] = (float)($items[$mapIdx['nip']]['y'] ?? 0) + 1;
                    }
                    if (isset($items[$mapIdx['jabatan']])) {
                        $items[$mapIdx['jabatan']]['y'] = (float)($items[$mapIdx['jabatan']]['y'] ?? 0) + 2;
                    }
                }
            } catch (\Throwable $_) {
                // non-fatal: ignore layout tweak on error
            }
        }

        // Gambar teks
        $deltaY = 0;
        foreach ($items as $it) {
            $key = $it['key'] ?? null;
            $val = $it['text'] ?? ($key ? ($textMap[$key] ?? null) : null);
            if ($val === null || $val === '') continue;

            $caseMode = $it['case'] ?? (!empty($it['uppercase']) ? 'upper' : 'none');
            // NOTE: Gelar is already normalized at SAVE time by EmployeeService,
            // so no need to normalize again here. Direct apply case transformation.
            $val      = $this->applyCase($val, $caseMode);

            $tx = (int)round(($it['x'] ?? 0) * $ppm);
            $ty = (int)round(($it['y'] ?? 0) * $ppm) + $deltaY;
            $tw = (int)round(($it['w'] ?? 9999) * $ppm);
            $al = strtolower($it['align'] ?? 'left');

            $sizeMm = (float)($it['font']['size'] ?? 8);
            $pxBase = $sizeMm * $ppm * 0.92;
            $pxSize = isset($it['__scaled_px']) ? (float)$it['__scaled_px'] : $pxBase;

            $bold = (bool)($it['font']['bold'] ?? false);
            $hex  = $it['font']['color'] ?? '#111827';
            $lh   = (float)($it['line_height'] ?? $lhDefaultFront);
            $wrap = array_key_exists('wrap', $it) ? (int)$it['wrap'] : null;
            $flow = array_key_exists('flow', $it) ? (bool)$it['flow'] : true;

            $font = $this->resolveFont($bold, $it['font']['key'] ?? null);
            if (!$font) {
                Log::warning('nametag: front font missing, skip text', [
                    'employee_id' => $e->id,
                    'key'         => $key,
                ]);
                continue;
            }
            $rgb = \App\Support\NametagPainter::hexToRgb($hex);

            // Check if this is jabatan with FUNGSIONAL type for Ahli post-processing
            $applyAhliPostProcess = false;
            $originalTextForAhli = null;
            if ($key === 'jabatan' && $e->jabatan_type === 'FUNGSIONAL') {
                $applyAhliPostProcess = true;
                $originalTextForAhli = (string)$val;
            }

            $usedH = $this->drawWrappedTextAndGetHeight(
                $tpl,
                (string)$val,
                $tx,
                $ty,
                $tw,
                $al,
                $font,
                $pxSize,
                $rgb,
                $lh,
                $wrap,
                $applyAhliPostProcess,
                $originalTextForAhli
            );
            if ($flow && $usedH > 0) {
                $oneLine = (int)round($pxSize * $lh);
                $extra   = $usedH - $oneLine;
                if ($extra > 0) $deltaY += $extra;
            }
        }

        // QR
        $qrSlot = $cfgFront['qr'] ?? null;
        if ($qrSlot) {
            $qx = (int)round($qrSlot['x'] * $ppm);
            $qy = (int)round($qrSlot['y'] * $ppm);
            $qs = (int)round(($qrSlot['size'] ?? $qrSlot['w'] ?? 40) * $ppm);
            $this->drawQrFromDiskOrMake($tpl, $e, $qx, $qy, $qs);
        }

        // Resample final canvas to match original template pixel size (if different)
        $origTpl = $this->loadImage($templatePath);
        if ($origTpl) {
            $origW = imagesx($origTpl);
            $origH = imagesy($origTpl);
            if ($origW !== imagesx($tpl) || $origH !== imagesy($tpl)) {
                $dst = imagecreatetruecolor($origW, $origH);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $fill = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefill($dst, 0, 0, $fill);
                imagecopyresampled($dst, $tpl, 0, 0, 0, 0, $origW, $origH, imagesx($tpl), imagesy($tpl));
                imagedestroy($tpl);
                $tpl = $dst;
            }
            imagedestroy($origTpl);
        }

        $out = $this->outputDir('front') . "/{$e->id}.png";
        $ok  = imagepng($tpl, $out, 6);
        if ($ok) {
            $dpi = (int)config('nametag.dpi', 300);
            $this->insertPngPhys($out, $dpi);
        }
        imagedestroy($tpl);

        Log::info('nametag: store result', [
            'employee_id' => $e->id,
            'front_out'   => $out,
            'ok'          => $ok,
        ]);
        return (bool)$ok;
    }

    /**
     * Insert pHYs chunk into a PNG file to indicate DPI (pixels per unit metre).
     * This inserts the chunk right after the IHDR chunk.
     */
    private function insertPngPhys(string $filePath, int $dpi): void
    {
        if (!is_file($filePath)) return;
        $data = file_get_contents($filePath);
        if ($data === false || strlen($data) < 33) return;

        // PNG signature
        $sig = substr($data, 0, 8);
        if ($sig !== "\x89PNG\r\n\x1a\n") return;

        // read first chunk length and type to locate IHDR
        $lenBytes = substr($data, 8, 4);
        $len = unpack('N', $lenBytes)[1];
        $type = substr($data, 12, 4);
        if ($type !== 'IHDR') return;

        // compute insertion offset = 8 (sig) + 4 + 4 + len + 4
        $offset = 8 + 4 + 4 + $len + 4;

        // compute pixels per metre from dpi
        $ppum = (int)round($dpi / 0.0254);

        $chunkType = 'pHYs';
        $chunkData = pack('NNC', $ppum, $ppum, 1);
        // pack length (9) + type + data + crc
        $lenPack = pack('N', 9);
        $crcVal = crc32($chunkType . $chunkData);
        $crc = pack('N', $crcVal);

        $physChunk = $lenPack . $chunkType . $chunkData . $crc;

        // insert
        $new = substr($data, 0, $offset) . $physChunk . substr($data, $offset);
        @file_put_contents($filePath, $new);
    }

    /* =========================
     *  BACK
     * ========================= */

    public function renderBack(Employee $e, ?string $templatePath): bool
    {
        try {
            // Clear PHP stat cache to ensure we detect the latest file modifications
            clearstatcache(true);
            $this->ensureDirs();

            // Normalisasi template back
            $templatePath = $this->resolveTemplatePath($templatePath, 'back');
            if (!$templatePath) {
                Log::error('nametag: back template missing', [
                    'employee_id' => $e->id,
                ]);
                return false;
            }

            $tpl = $this->loadImage($templatePath);
            if (!$tpl) {
                Log::error('nametag: back image load fail', [
                    'employee_id' => $e->id,
                    'template'    => $templatePath,
                ]);
                return false;
            }
            $cfgBack = config('nametag.templates.back');
            $ppm     = $this->ppm($tpl, $cfgBack);

            // default line-height sisi belakang
            $lhDefaultBack = (float)(
                config('nametag.line_height.back')
                ?? config('nametag.line_height.default', 1.25)
            );

            $data  = \App\Support\NametagData::buildBack($e);
            // FIX: Use array_merge to create a COPY, not reference to config
            // If we modify $items in-place (e.g., modifying text fields), 
            // it must NOT affect the cached config for subsequent renders
            $items = array_merge([], $cfgBack['texts'] ?? []);

            // ---- Ensure back-side NIP label uses employee accessor but keep
            // label as its own column (don't merge with the value as we do on front)
            $labelNipIdx = null;
            foreach ($items as $i => $it) {
                if (($it['key'] ?? null) === 'label_nip') {
                    $labelNipIdx = $i;
                    break;
                }
            }
            $labelText = mb_strtoupper((string) ($e->nip_label ?? 'NIP.'), 'UTF-8');
            if ($labelNipIdx !== null) {
                $items[$labelNipIdx]['text'] = $labelText;
                $items[$labelNipIdx]['uppercase_force'] = true;
            } else {
                // fallback: replace any literal 'NIP.' text entries with the resolved label
                foreach ($items as $ii => $it) {
                    $t = isset($it['text']) ? trim(mb_strtoupper($it['text'])) : '';
                    if ($t === 'NIP.' || $t === 'NIP. ') {
                        $items[$ii]['text'] = mb_strtoupper($labelText, 'UTF-8');
                        $items[$ii]['uppercase_force'] = true;
                    }
                }
            }

                // pisah pre-TTD & blok TTD
                $preTtd = [];
                $ttdBlk = [];
                foreach ($items as $it) {
                $k = $it['key'] ?? '';
                if (str_starts_with((string)$k, 'ttd_')) {
                    $ttdBlk[] = $it;
                } else {
                    $preTtd[] = $it;
                }
            }

            // data pegawai
            $deltaY = 0;
            // track the lowest pixel reached by pre-TTD block (to decide auto-shift)
            $maxPreTtdBottom = 0;
            foreach ($preTtd as $it) {
                $key = $it['key'] ?? null;
                $val = $it['text'] ?? ($key ? ($data[$key] ?? null) : null);

                $caseMode = $it['case'] ?? (!empty($it['uppercase']) ? 'upper' : 'none');
                    // NOTE: Gelar is already normalized at SAVE time by EmployeeService,
                    // so no need to normalize again here. Direct apply case transformation.
                    $val      = $this->applyCase($val, $caseMode);
                    if (!empty($it['uppercase_force'])) {
                        $val = mb_strtoupper((string)$val, 'UTF-8');
                    }

                // Check if this is jabatan with FUNGSIONAL type for Ahli post-processing (back template)
                $applyAhliPostProcess = false;
                $originalTextForAhli = null;
                if ($key === 'val_jab' && $e->jabatan_type === 'FUNGSIONAL') {
                    $applyAhliPostProcess = true;
                    $originalTextForAhli = (string)$val;
                }

                $tx = (int)round(($it['x'] ?? 0) * $ppm);
                $ty = (int)round(($it['y'] ?? 0) * $ppm) + $deltaY;
                $tw = (int)round(($it['w'] ?? 9999) * $ppm);
                $al = strtolower($it['align'] ?? 'left');

                $size   = (float)($it['font']['size'] ?? 6.5);
                $pxSize = $size * $ppm * 0.92;
                $bold   = (bool)($it['font']['bold'] ?? false);
                $hex    = $it['font']['color'] ?? '#111827';
                $lh     = (float)($it['line_height'] ?? $lhDefaultBack);
                $wrap   = isset($it['wrap']) ? (int)$it['wrap'] : null;
                $flow   = array_key_exists('flow', $it) ? (bool)$it['flow'] : true;

                $font = $this->resolveFont($bold, $it['font']['key'] ?? null);
                if (!$font) {
                    Log::warning('nametag: back font missing', ['key' => $key]);
                    continue;
                }
                $rgb = \App\Support\NametagPainter::hexToRgb($hex);

                $usedH = $this->drawWrappedTextAndGetHeight(
                    $tpl,
                    (string)$val,
                    $tx,
                    $ty,
                    $tw,
                    $al,
                    $font,
                    $pxSize,
                    $rgb,
                    $lh,
                    $wrap,
                    $applyAhliPostProcess,
                    $originalTextForAhli
                );
                // record bottom of this drawn block
                $bottom = $ty + max(0, $usedH);
                // ignore placeholder '-' (fallback values) so they don't trigger auto-shift
                $isPlaceholder = is_string($val) && trim($val) === '-';
                if (!$isPlaceholder && $bottom > $maxPreTtdBottom) $maxPreTtdBottom = $bottom;
                if ($flow && $usedH > 0) {
                    $oneLine = (int)round($pxSize * $lh);
                    $extra   = $usedH - $oneLine;
                    if ($extra > 0) $deltaY += $extra;
                }
            }

            // If pre-TTD content reaches threshold from top, shift TTD area down
            $autoShiftPx = 0;
            $thresholdPx = (int)round(50 * $ppm);
            if ($maxPreTtdBottom >= $thresholdPx) {
                $autoShiftPx = (int)round(10 * $ppm);
            }

            // Debug: log auto-shift decision and pre-TTD bottom
            Log::info('nametag: back layout debug', [
                'employee_id' => $e->id,
                'maxPreTtdBottom' => $maxPreTtdBottom,
                'thresholdPx' => $thresholdPx,
                'autoShiftPx' => $autoShiftPx,
                'ppm' => $ppm,
            ]);

            // STAMP + TTD images
            $slot      = $cfgBack['signature'] ?? null;
            $stampSlot = $cfgBack['stamp'] ?? null;
            $sigPath = $this->resolveSignaturePath();
            $sigWpx  = 0;
            $sigHpx  = 0;
            if ($slot && $sigPath) {
                $sx   = (int)round(($slot['x'] ?? 0) * $ppm);
                $sy   = (int)round(($slot['y'] ?? 0) * $ppm) + $autoShiftPx; // dipakai untuk posisi teks
                $boxS = (int)round(($slot['size'] ?? 40) * $ppm);

                // offset gambar saja (mm) → dikonversi ke px, default 0
                $imgYOffMm = (float)($slot['img_y_offset'] ?? 0);
                $syImage = $sy + (int)round($imgYOffMm * $ppm); // hanya untuk menempatkan gambar

                // Debug: log signature placement inputs
                Log::info('nametag: signature placement debug', [
                    'employee_id' => $e->id,
                    'sigPath' => $sigPath,
                    'sx' => $sx,
                    'sy' => $sy,
                    'imgYOffMm' => $imgYOffMm,
                    'syImage' => $syImage,
                    'boxS' => $boxS,
                ]);

                $rem = app(\App\Services\BackgroundRemovalService::class);
                $tmpSig = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sig_' . uniqid() . '.png';
                $res = $rem->clean($sigPath, $tmpSig);
                if ($res && is_file($tmpSig)) {
                    $sig = $this->loadImage($tmpSig);
                    if ($sig) {
                        $sw0   = imagesx($sig);
                        $sh0   = imagesy($sig);
                        $scale = min($boxS / max(1, $sw0), $boxS / max(1, $sh0));
                        $sigWpx = (int)round($sw0 * $scale);
                        $sigHpx = (int)round($sh0 * $scale);

                        $can = imagecreatetruecolor($boxS, $boxS);
                        imagealphablending($can, false);
                        imagesavealpha($can, true);
                        $tr = imagecolorallocatealpha($can, 0, 0, 0, 127);
                        imagefill($can, 0, 0, $tr);

                        $tmp = imagecreatetruecolor($sigWpx, $sigHpx);
                        imagealphablending($tmp, false);
                        imagesavealpha($tmp, true);
                        $tr2 = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                        imagefill($tmp, 0, 0, $tr2);
                        imagecopyresampled($tmp, $sig, 0, 0, 0, 0, $sigWpx, $sigHpx, $sw0, $sh0);

                        $ox = (int)round(($boxS - $sigWpx) / 2);
                        $oy = (int)round(($boxS - $sigHpx) / 2);
                        imagecopy($can, $tmp, $ox, $oy, 0, 0, $sigWpx, $sigHpx);
                        imagedestroy($tmp);
                        imagedestroy($sig);

                        imagealphablending($tpl, true);
                        imagesavealpha($tpl, true);
                        imagecopy($tpl, $can, $sx, $syImage, 0, 0, $boxS, $boxS);
                        Log::info('nametag: signature drawn', [
                            'employee_id' => $e->id,
                            'sigWpx' => $sigWpx,
                            'sigHpx' => $sigHpx,
                            'placed_at_px' => ['x' => $sx, 'y' => $syImage],
                            'boxS' => $boxS,
                        ]);
                        imagedestroy($can);
                    }
                } else {
                    // fallback: load raw signature and try trimming using local helpers
                    $sig = $this->loadImage($sigPath);
                    if ($sig) {
                        $this->whitenToAlpha($sig, 245);
                        $this->removeBgToAlpha($sig, 60);

                        $sw0   = imagesx($sig);
                        $sh0   = imagesy($sig);
                        $scale = min($boxS / max(1, $sw0), $boxS / max(1, $sh0));
                        $sigWpx = (int)round($sw0 * $scale);
                        $sigHpx = (int)round($sh0 * $scale);

                        $can = imagecreatetruecolor($boxS, $boxS);
                        imagealphablending($can, false);
                        imagesavealpha($can, true);
                        $tr = imagecolorallocatealpha($can, 0, 0, 0, 127);
                        imagefill($can, 0, 0, $tr);

                        $tmp = imagecreatetruecolor($sigWpx, $sigHpx);
                        imagealphablending($tmp, false);
                        imagesavealpha($tmp, true);
                        $tr2 = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                        imagefill($tmp, 0, 0, $tr2);
                        imagecopyresampled($tmp, $sig, 0, 0, 0, 0, $sigWpx, $sigHpx, $sw0, $sh0);

                        $ox = (int)round(($boxS - $sigWpx) / 2);
                        $oy = (int)round(($boxS - $sigHpx) / 2);
                        imagecopy($can, $tmp, $ox, $oy, 0, 0, $sigWpx, $sigHpx);
                        imagedestroy($tmp);
                        imagedestroy($sig);

                        imagealphablending($tpl, true);
                        imagesavealpha($tpl, true);
                        imagecopy($tpl, $can, $sx, $syImage, 0, 0, $boxS, $boxS);
                        imagedestroy($can);
                    }
                }
            } else {
                Log::warning('nametag: signature slot not found', [
                    'slot_exists' => (bool)$slot,
                    'path'        => $sigPath,
                ]);
            }

            // Stamp (cap) — optional: load and composite stamp image if configured
            $stampPath = $this->resolveStampPath();
            if ($stampSlot && $stampPath) {
                $stx = (int)round(($stampSlot['x'] ?? 0) * $ppm);
                $sty = (int)round(($stampSlot['y'] ?? 0) * $ppm) + $autoShiftPx;
                $stBox = (int)round(($stampSlot['size'] ?? 40) * $ppm);

                // Debug: log stamp placement inputs
                Log::info('nametag: stamp placement debug', [
                    'employee_id' => $e->id,
                    'stampPath' => $stampPath,
                    'stx' => $stx,
                    'sty' => $sty,
                    'stBox' => $stBox,
                ]);

                $stampImg = $this->loadImage($stampPath);
                if ($stampImg) {
                    $sw0 = imagesx($stampImg);
                    $sh0 = imagesy($stampImg);
                    $scale = min($stBox / max(1, $sw0), $stBox / max(1, $sh0));
                    $stw = (int)round($sw0 * $scale);
                    $sth = (int)round($sh0 * $scale);

                    $scan = imagecreatetruecolor($stBox, $stBox);
                    imagealphablending($scan, false);
                    imagesavealpha($scan, true);
                    $tr = imagecolorallocatealpha($scan, 0, 0, 0, 127);
                    imagefill($scan, 0, 0, $tr);

                    $tmp = imagecreatetruecolor($stw, $sth);
                    imagealphablending($tmp, false);
                    imagesavealpha($tmp, true);
                    $tr2 = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
                    imagefill($tmp, 0, 0, $tr2);
                    imagecopyresampled($tmp, $stampImg, 0, 0, 0, 0, $stw, $sth, $sw0, $sh0);

                    $ox = (int)round(($stBox - $stw) / 2);
                    $oy = (int)round(($stBox - $sth) / 2);
                    imagecopy($scan, $tmp, $ox, $oy, 0, 0, $stw, $sth);
                    imagedestroy($tmp);
                    imagedestroy($stampImg);

                    imagealphablending($tpl, true);
                    imagesavealpha($tpl, true);
                    imagecopy($tpl, $scan, $stx, $sty, 0, 0, $stBox, $stBox);
                    Log::info('nametag: stamp drawn', [
                        'employee_id' => $e->id,
                        'stw' => $stw,
                        'sth' => $sth,
                        'placed_at_px' => ['x' => $stx, 'y' => $sty],
                        'stBox' => $stBox,
                    ]);
                    imagedestroy($scan);
                }
            }

            // Blok TTD teks
            if (!empty($ttdBlk) && $slot) {
            
            $fontColor       = '#111827';
            // positive = move title upward from signature box
            $titleOffsetUp   = (int)round(20 * $ppm);
            // small positive gap between signature box and text lines below
            $textGapBelowSig = (int)round(6 * $ppm);
            $lineGap         = (int)round(1 * $ppm);

            $sx   = (int)round(($slot['x'] ?? 0) * $ppm);
            $sy   = (int)round(($slot['y'] ?? 0) * $ppm);
            $boxS = (int)round(($slot['size'] ?? 40) * $ppm);

            // Anchor title/name relative to the stamp if available, otherwise fallback to signature box
            if (!empty($stampSlot)) {
                $anchorSy  = (int)round(($stampSlot['y'] ?? 0) * $ppm);
                $anchorBoxS = (int)round(($stampSlot['size'] ?? 40) * $ppm);
            } else {
                $anchorSy  = $sy;
                $anchorBoxS = $boxS;
            }

            // Force auto-shift to apply to anchor as well so all anchor-based text moves
            if (!empty($autoShiftPx)) {
                $anchorSy += $autoShiftPx;
            }

            $drawOne = function (array $it, int $x, int $y) use ($tpl, $ppm, $fontColor, $lhDefaultBack) {
                $size   = (float)($it['font']['size'] ?? 5.5);
                $pxSize = $size * $ppm * 0.92;
                $bold   = (bool)($it['font']['bold'] ?? false);
                $hex    = $it['font']['color'] ?? $fontColor;
                $al     = strtolower($it['align'] ?? 'left');
                $w      = (int)round(($it['w'] ?? 120) * $ppm);

                $font = $this->resolveFont($bold, $it['font']['key'] ?? null);
                if (!$font) return 0;
                $rgb = \App\Support\NametagPainter::hexToRgb($hex);

                $wrap = isset($it['wrap']) ? (int)$it['wrap'] : null;
                $lh   = (float)($it['line_height'] ?? $lhDefaultBack);

                return $this->drawWrappedTextAndGetHeight(
                    $tpl,
                    (string)$it['__val'],
                    $x,
                    $y,
                    $w,
                    $al,
                    $font,
                    $pxSize,
                    $rgb,
                    $lh,
                    $wrap
                );
            };

            foreach ($ttdBlk as &$it) {
                $k           = $it['key'] ?? null;
                $it['__val'] = $it['text'] ?? ($k ? ($data[$k] ?? null) : null);
                $caseMode    = $it['case'] ?? (!empty($it['uppercase']) ? 'upper' : 'none');
                if ($it['__val'] !== null) {
                    $it['__val'] = $this->applyCase($it['__val'], $caseMode);
                }
            }
            unset($it);

            unset($it);

            $title = collect($ttdBlk)->firstWhere('key', 'ttd_title');
            if ($title && $title['__val']) {
                $xTitle = (int)round(($title['x'] ?? 50) * $ppm);

                // prefer config Y if present, otherwise compute using the anchor (stamp)
                if (isset($title['y']) && $title['y'] !== null) {
                    $yTitle = (int)round(($title['y'] ?? 0) * $ppm) + $autoShiftPx;
                } else {
                    $yTitle = $anchorSy - $titleOffsetUp;
                }

                $titleLh = (float)($title['line_height'] ?? $lhDefaultBack);

                $this->drawWrappedTextAndGetHeight(
                    $tpl,
                    (string)$title['__val'],
                    $xTitle,
                    $yTitle,
                    (int)round(($title['w'] ?? 120) * $ppm),
                    strtolower($title['align'] ?? 'left'),
                    $this->resolveFont((bool)($title['font']['bold'] ?? false), $title['font']['key'] ?? null),
                    ($title['font']['size'] ?? 5.5) * $ppm * 0.92,
                    \App\Support\NametagPainter::hexToRgb($title['font']['color'] ?? '#111827'),
                    $titleLh,
                    $title['wrap'] ?? null
                );
            }

            // base Y for lines below: prefer config y of first ttd line if present
            $firstBelow = collect($ttdBlk)->firstWhere('key', 'ttd_nama') ?: collect($ttdBlk)->firstWhere('key', 'ttd_sekda');
            if ($firstBelow && isset($firstBelow['y']) && $firstBelow['y'] !== null) {
                $yBase = (int)round(($firstBelow['y'] ?? 0) * $ppm) + $autoShiftPx;
            } else {
                $yBase = $anchorSy + $anchorBoxS + $textGapBelowSig;
            }
            foreach (['ttd_nama', 'ttd_pangkat', 'ttd_nip', 'ttd_sekda'] as $k) {
                $it = collect($ttdBlk)->firstWhere('key', $k);
                if (!$it || !$it['__val']) continue;
                $x = (int)round(($it['x'] ?? 50) * $ppm);

                // if config y is provided for this item, use it; otherwise use incremental yBase
                if (isset($it['y']) && $it['y'] !== null) {
                    $useY = (int)round($it['y'] * $ppm) + $autoShiftPx;
                } else {
                    $useY = $yBase;
                }

                $h = $drawOne($it, $x, $useY);

                if (!isset($it['y']) || $it['y'] === null) {
                    // only advance the running yBase when we used it
                    $yBase += ($h > 0 ? $h + $lineGap : $lineGap);
                }
            }
        }

        // QR belakang (opsional)
        $qrSlot = $cfgBack['qr'] ?? null;
        if ($qrSlot) {
            $qx = (int)round($qrSlot['x'] * $ppm);
            $qy = (int)round($qrSlot['y'] * $ppm);
            $qs = (int)round(($qrSlot['size'] ?? ($qrSlot['w'] ?? 40)) * $ppm);
            $this->drawQrFromDiskOrMake($tpl, $e, $qx, $qy, $qs);
        }

        // Resample final canvas to match original template pixel size (if different)
        $origTpl = $this->loadImage($templatePath);
        if ($origTpl) {
            $origW = imagesx($origTpl);
            $origH = imagesy($origTpl);
            if ($origW !== imagesx($tpl) || $origH !== imagesy($tpl)) {
                $dst = imagecreatetruecolor($origW, $origH);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $fill = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefill($dst, 0, 0, $fill);
                imagecopyresampled($dst, $tpl, 0, 0, 0, 0, $origW, $origH, imagesx($tpl), imagesy($tpl));
                imagedestroy($tpl);
                $tpl = $dst;
            }
            imagedestroy($origTpl);
        }

        $out = $this->outputDir('back') . "/{$e->id}.png";
        $ok  = imagepng($tpl, $out, 6);
        if ($ok) {
            $dpi = (int)config('nametag.dpi', 300);
            $this->insertPngPhys($out, $dpi);
        }
        imagedestroy($tpl);

        Log::info('nametag: back store result', [
            'employee_id' => $e->id,
            'back_out'    => $out,
            'ok'          => $ok,
        ]);
        return (bool)$ok;
        } catch (\Throwable $ex) {
            Log::error('nametag: renderBack exception', [
                'employee_id' => $e->id,
                'err' => (string)$ex->getMessage(),
            ]);
            return false;
        }
    }
}
