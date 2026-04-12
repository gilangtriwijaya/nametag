<?php

namespace App\Services\Nametag;

use Illuminate\Support\Facades\Log;

trait NametagTextLayout
{
    /* ========= TEXT HELPERS ========= */

    private function applyCase(?string $text, string $mode = 'none'): string
    {
        $s    = (string)($text ?? '');
        $mode = strtolower($mode);

        if ($s === '' || $mode === 'none') {
            return $s;
        }

        switch ($mode) {
            case 'upper':
                // Extract and preserve content inside double quotes before uppercasing
                $preservedMap = [];
                $markerStart = chr(0);
                $markerEnd = chr(1);
                $text = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $markerStart, $markerEnd) {
                    $idx = count($preservedMap);
                    $key = $markerStart . 'Q' . $idx . $markerEnd;
                    $preservedMap[$key] = $matches[1];
                    return $key;
                }, $s);
                $result = mb_strtoupper($text, 'UTF-8');
                foreach ($preservedMap as $key => $value) {
                    $result = str_replace($key, $value, $result);
                }
                return $result;

            case 'lower':
                // Extract and preserve content inside double quotes before lowercasing
                $preservedMap = [];
                $markerStart = chr(0);
                $markerEnd = chr(1);
                $text = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $markerStart, $markerEnd) {
                    $idx = count($preservedMap);
                    $key = $markerStart . 'Q' . $idx . $markerEnd;
                    $preservedMap[$key] = $matches[1];
                    return $key;
                }, $s);
                $result = mb_strtolower($text, 'UTF-8');
                foreach ($preservedMap as $key => $value) {
                    $result = str_replace($key, $value, $result);
                }
                return $result;

            case 'title':
                // FIX: Preserve gelar_depan (at start) and gelar_belakang (after comma)
                // This is important because gelar is already normalized at save-time with quote-escape
                // Also preserve content inside double quotes (no case transform applied)
                // Use special UTF-8 markers for placeholders that won't be affected by case transforms
                // NOTE: Quotes are REMOVED from the output, only the content is preserved
                $preservedMap = [];
                $markerStart = chr(0);  // null byte as boundary
                $markerEnd = chr(1);    // SOH as boundary
                
                $s = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $markerStart, $markerEnd) {
                    $idx = count($preservedMap);
                    $key = $markerStart . 'Q' . $idx . $markerEnd;  // Safe placeholder
                    $preservedMap[$key] = $matches[1];  // Store content WITHOUT the quote marks
                    return $key;  // Replace with placeholder temporarily
                }, $s);
                
                $gelarDepan = '';
                $namePart   = $s;
                $gelarBelakang = '';
                
                // Step 1: Extract gelar_belakang (after comma)
                if (strpos($s, ',') !== false) {
                    [$namePart, $gelarBelakang] = explode(',', $s, 2);
                    $gelarBelakang = ',' . $gelarBelakang;  // Restore the comma+space
                }
                
                // Step 2: Extract gelar_depan (leading abbreviations)
                // Match abbreviations: either 1-3 letters + REQUIRED dot (Dr., Apt., Dra., etc.)
                // or any word ending with a dot (guarantees abbreviation: Prof., Dr., etc.)
                // IMPORTANT: Dot is REQUIRED to avoid false positives like "DWI" (3 letters without dot)
                // Pattern requires dot: (word.\s+)* where word is <=3 letters OR word ends with dot
                if (preg_match('/^((?:[A-Za-z]{1,3}\.\s+|[A-Z][a-z]+\.\s+)+)(.*)$/u', $namePart, $m)) {
                    $gelarDepan = $m[1];  // e.g., "Dr. " or "Prof. Dr. "
                    $namePart   = $m[2];  // e.g., "John Smith"
                }

                // Step 3: Title Case only on the remaining name part
                $namePart = mb_convert_case($namePart, MB_CASE_TITLE, 'UTF-8');

                // 2) Kata sambung kecil dibuat lower (kecuali di awal baris)
                $small = ['dan', 'yang', 'di', 'ke', 'dari', 'of', 'and', 'the'];
                $parts = preg_split('/(\s+)/u', $namePart, -1, PREG_SPLIT_DELIM_CAPTURE);
                foreach ($parts as $i => $p) {
                    if ($i % 2 === 0) {
                        $lp = mb_strtolower($p, 'UTF-8');
                        if (in_array($lp, $small, true)) {
                            $parts[$i] = $lp;
                        }
                    }
                }
                $namePart = implode('', $parts);

                // 3) Perbaikan gelar / singkatan bertitik: setiap segmen setelah titik
                // dibuat Title-cased (huruf pertama kapital, sisanya kecil).
                // Contoh: S.I.KOM. -> S.I.Kom.
                // NOTE: Only apply this to name part, NOT to gelar part
                $namePart = preg_replace_callback(
                    '/\b([A-Za-z](?:\.[A-Za-z.]*[A-Za-z]\.?)+)\b/iu',
                    function ($m) {
                        $seg = $m[1];
                        // split preserving dots
                        $parts = preg_split('/(\.)/u', $seg, -1, PREG_SPLIT_DELIM_CAPTURE);
                        for ($i = 0; $i < count($parts); $i++) {
                            if ($parts[$i] === '.') continue;
                            $p = $parts[$i];
                            if ($p === '') continue;
                            $first = mb_substr($p, 0, 1, 'UTF-8');
                            $rest  = mb_substr($p, 1, null, 'UTF-8');
                            $parts[$i] = mb_strtoupper($first, 'UTF-8') . mb_strtolower($rest, 'UTF-8');
                        }
                        return implode('', $parts);
                    },
                    $namePart
                );
                // Final normalization: also catch sequences like "S.I.KOM., M.KESOS"
                // that may not have matched the previous pattern, and ensure each
                // dot-separated fragment is Title-cased (first letter upper, rest lower).
                $namePart = preg_replace_callback('/[A-Za-z](?:\.[A-Za-z]+)+(?:\.|\b)/u', function($m) {
                    $raw = $m[0];
                    // trim trailing punctuation except dot
                    $trailing = '';
                    if (preg_match('/([.,;:]+)$/', $raw, $mm)) {
                        $trailing = $mm[1];
                        $raw = substr($raw, 0, -strlen($trailing));
                    }
                    $parts = explode('.', $raw);
                    $parts = array_filter($parts, fn($p) => $p !== '');
                    $out = [];
                    foreach ($parts as $p) {
                        $pLow = mb_strtolower($p, 'UTF-8');
                        $out[] = mb_strtoupper(mb_substr($pLow,0,1,'UTF-8'),'UTF-8') . mb_substr($pLow,1,null,'UTF-8');
                    }
                    return implode('.', $out) . $trailing;
                }, $namePart);

                $result = $gelarDepan . $namePart . $gelarBelakang;

                // Step 4: Restore preserved (quoted) content
                foreach ($preservedMap as $key => $value) {
                    $result = str_replace($key, $value, $result);
                }

                return $result;

            default:
                return $s;
        }
    }

    private function fitSingleLinePx(string $text, string $font, float $basePx, int $maxW): float
    {
        $text = trim($text);
        if ($text === '') return $basePx;

        // allow smaller minimum font so long names can shrink further
        $lo   = max(4.0, $basePx * 0.35);
        $hi   = $basePx;
        $best = $lo;

        for ($i = 0; $i < 14; $i++) {
            $mid  = ($lo + $hi) / 2.0;
            $bbox = imagettfbbox($mid, 0, $font, $text);
            $w    = abs($bbox[2] - $bbox[0]);
            if ($w <= $maxW) {
                $best = $mid;
                $lo   = $mid;
            } else {
                $hi = $mid;
            }
        }
        return min($best, $basePx);
    }

    private function fitWrappedLinesPx(string $text, string $font, float $basePx, int $maxW, int $maxLines): float
    {
        $text = trim($text);
        if ($text === '') return $basePx;

        // allow smaller minimum font so wrapped text can fit within max lines
        $lo   = max(4.0, $basePx * 0.35);
        $hi   = $basePx;
        $best = $lo;

        $wrapCount = function (float $px) use ($text, $font, $maxW): int {
            $words = preg_split('/\s+/u', $text);
            $line  = '';
            $lines = 0;
            foreach ($words as $w) {
                $test = $line === '' ? $w : ($line . ' ' . $w);
                $bbox = imagettfbbox($px, 0, $font, $test);
                $tw   = abs($bbox[2] - $bbox[0]);
                if ($tw <= $maxW || $line === '') {
                    $line = $test;
                } else {
                    $lines++;
                    $line = $w;
                }
            }
            if ($line !== '') $lines++;
            return $lines;
        };

        for ($i = 0; $i < 14; $i++) {
            $mid = ($lo + $hi) / 2.0;
            $cnt = $wrapCount($mid);
            if ($cnt <= $maxLines) {
                $best = $mid;
                $lo   = $mid;
            } else {
                $hi = $mid;
            }
        }
        return min($best, $basePx);
    }

    private function wrapLines(string $text, int $w, string $font, float $size): array
    {
        $text = trim((string)$text);
        if ($text === '') return [];

        $paras = preg_split("/\r?\n/u", $text);
        $lines = [];

        foreach ($paras as $p) {
            $words = preg_split('/\s+/u', trim($p));
            $cur   = '';
            foreach ($words as $word) {
                $test = $cur === '' ? $word : ($cur . ' ' . $word);
                $bbox = imagettfbbox($size, 0, $font, $test);
                $tw   = abs($bbox[2] - $bbox[0]);
                if ($tw <= $w || $cur === '') {
                    $cur = $test;
                } else {
                    $lines[] = $cur;
                    $cur     = $word;
                }
            }
            if ($cur !== '') $lines[] = $cur;
        }

        return $lines;
    }

    private function drawWrappedTextAndGetHeight(
        $im,
        string $text,
        int $x,
        int $y,
        int $w,
        string $align,
        string $font,
        float $size,
        array $rgb,
        float $lineHeight = 1.25,
        ?int $maxLines = null,
        ?bool $applyAhliPostProcess = false,
        ?string $originalTextForAhli = null
    ): int {
        $lines = $this->wrapLines($text, $w, $font, $size);
        
        // Post-process untuk Ahli atomic handling (untuk FUNGSIONAL jabatan)
        if ($applyAhliPostProcess && $originalTextForAhli) {
            $lines = $this->ensureAhliAtomicAfterWrap($lines, $originalTextForAhli, $w, $font, $size);
        }
        
        if ($maxLines !== null && $maxLines >= 0) {
            $lines = array_slice($lines, 0, $maxLines);
        }
        if (!$lines) return 0;

        $color = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
        $lh_px = (int)round($size * $lineHeight);

        foreach ($lines as $i => $line) {
            $bbox = imagettfbbox($size, 0, $font, $line);
            $tw   = abs($bbox[2] - $bbox[0]);
            $tx   = match ($align) {
                'center' => $x + max(0, (int)round(($w - $tw) / 2)),
                'right'  => $x + max(0, $w - $tw),
                default  => $x,
            };
            imagettftext($im, $size, 0, $tx, $y + $i * $lh_px, $color, $font, $line);
        }

        return $lh_px * count($lines);
    }

    /**
     * Normalize dot-separated abbreviations inside a string so each fragment
     * after a dot uses Title-case (first letter uppercase, rest lowercase).
     */
    private function normalizeAbbreviations(string $s): string
    {
        if (strpos($s, '.') === false) return $s;

        return preg_replace_callback('/[A-Za-z][A-Za-z]*(?:\.[A-Za-z][A-Za-z]*)+\.?/u', function ($m) {
            $raw = $m[0];
            // strip trailing punctuation except dot
            $trail = '';
            if (preg_match('/([,;:]+)$/', $raw, $mm)) {
                $trail = $mm[1];
                $raw = substr($raw, 0, -strlen($trail));
            }
            $parts = explode('.', $raw);
            $parts = array_filter($parts, fn($p) => $p !== '');
            $out = [];
            foreach ($parts as $p) {
                $pLow = mb_strtolower($p, 'UTF-8');
                $out[] = mb_strtoupper(mb_substr($pLow,0,1,'UTF-8'),'UTF-8') . mb_substr($pLow,1,null,'UTF-8');
            }
            return implode('.', $out) . $trail;
        }, $s);
    }

    /**
     * Ensure "Ahli" jabatan tetap atomic setelah wrapping.
     * 
     * FIX (2026-02-17): MOVED "Ahli" across line boundaries instead of re-wrap
     * 
     * Problem dengan re-wrap:
     * - Saat jabatan di-scale-down via pre-scaling (fitWrappedLinesPx),
     *   font size SUDAH optimal untuk fit dalam 2 lines dengan width tertentu
     * - Jika re-wrap dengan same font size + width, hasilnya IDENTIK
     * - So re-wrap tidak menyelesaikan masalah separation
     * 
     * Solusi:
     * - Jangan re-wrap, cukup reorganize existing lines
     * - Deteksi jika line[i] END dengan "Ahli" (separated)
     * - PINDAHKAN "Ahli " dari akhir line[i] ke awal line[i+1]
     * - Result: "Jabatan Ahli" tetap dalam line[i+1]
     * 
     * Example:
     *   Before: ["Pengelola Pengadaan Barang/Jasa Ahli", "Muda"]
     *   After:  ["Pengelola Pengadaan Barang/Jasa", "Ahli Muda"]
     * 
     * @param array $lines Hasil wrap dari wrapLines()
     * @param string $fullText Text original
     * @param int $w Width dalam pixel (tidak digunakan dalam reorganize)
     * @param string $font Font path (tidak digunakan dalam reorganize)
     * @param float $size Font size (tidak digunakan dalam reorganize)
     * @return array Lines yang sudah ensure Ahli atomic
     */
    private function ensureAhliAtomicAfterWrap(array $lines, string $fullText, int $w, string $font, float $size): array
    {
        if (count($lines) <= 1 || stripos($fullText, 'ahli') === false) {
            return $lines;  // Tidak perlu post-process jika 1 baris atau tidak ada "Ahli"
        }

        // Check each line: apakah END dengan "Ahli" (terpisah dari kata setelahnya)?
        for ($i = 0; $i < count($lines) - 1; $i++) {
            $line = $lines[$i];
            
            // Pattern: baris END dengan "Ahli" (tanpa kata setelahnya)
            if (preg_match('/\bAhli\s*$/iu', $line)) {
                
                // FOUND: "Ahli" terpisah dari kata setelahnya di line[i+1]
                // Reorganize: PINDAHKAN "Ahli" dari line[i] ke line[i+1]
                
                // Extract "Ahli" dari akhir line[i]
                // Match pattern: word boundary + "Ahli" + optional spaces + end of string
                if (preg_match('/\s*(\bAhli\s*)$/iu', $line, $m)) {
                    $ahliToken = trim($m[1]);  // Extract "Ahli" token
                    
                    // Remove "Ahli" dari line[i]
                    $lineWithoutAhli = preg_replace('/\s*\bAhli\s*$/iu', '', $line);
                    
                    // Get line[i+1]
                    $nextLine = $lines[$i + 1] ?? '';
                    
                    // Combine: "Ahli " + line[i+1]
                    $ahliPrependedNextLine = $ahliToken . ' ' . $nextLine;
                    
                    // REORGANIZE lines array
                    $result = $lines;
                    $result[$i]     = trim($lineWithoutAhli);  // line[i] tanpa "Ahli"
                    $result[$i + 1] = trim($ahliPrependedNextLine);  // line[i+1] di-prepend "Ahli"
                    
                    return $result;
                }
                break;
            }
        }

        return $lines;  // Tidak ada masalah, return as-is
    }
}
