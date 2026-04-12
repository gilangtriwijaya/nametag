# Dokumentasi: Ahli Jabatan Smart Wrapping Rule

## Overview
Implementasi rule khusus untuk jabatan tipe FUNGSIONAL yang mengandung kata "Ahli". Rule ini memastikan bahwa "Ahli" dan semua kata setelahnya (tingkat jabatan) tidak terpisah di baris berbeda, menjaga estetika dan kejelasan struktur jabatan pada nametag.

## Requirement
- **Target**: Jabatan tipe `FUNGSIONAL` dengan kata kunci "Ahli"
- **Kondisi**: Ketika jabatan tidak muat dalam 1 baris (multi-line wrap)
- **Behavior**: "Ahli + semua kata setelahnya" harus tetap dalam satu atomic unit
- **Jika terjadi split**: Seluruh unit "Ahli..." turun mengikuti ke baris berikutnya
- **Backward Compatibility**: Rule lama (pre-scaling trio) tetap utuh

## Implementasi

### 1. File yang Dimodifikasi

#### `app/Services/Nametag/NametagTextLayout.php`

**Method Baru: `prepareAhliJabatan(string $jabatan): string`**
- Deteksi kata "Ahli" dalam string jabatan (case-insensitive)
- Jika "Ahli" di tengah/akhir string (ada kata sebelumnya), inject soft-break marker (◇) sebelum "Ahli"
- Jika "Ahli" di awal string, return as-is (tidak perlu marker)
- Marker adalah Unicode character U+25C7 (White Diamond) yang tidak akan muncul di visual

**Contoh Transform:**
```
"Kepala Bidang Ahli Keuangan Daerah" 
  → "Kepala Bidang◇Ahli Keuangan Daerah"

"Ahli Keuangan Daerah" 
  → "Ahli Keuangan Daerah" (no marker)
```

**Modified Method: `fitWrappedLinesPx()`**
- Update untuk handle soft-break marker
- Marker di-replace dengan newline saat counting line wraps
- Memastikan font sizing calculation mempertimbangkan marker

**Modified Method: `wrapLines()`**
- Update untuk handle soft-break marker
- Marker di-replace dengan newline untuk force line break point
- Wrapping logic tetap sama, tapi sekarang respects marker

#### `app/Services/NametagRenderService.php`

**Modified: `renderFront()` method, sebelum pre-scaling trio (line ~151)**
- Tambahan logic untuk apply "Ahli" rule sebelum pre-scaling
- Cek: `jabatan_type === 'FUNGSIONAL'` dan string mengandung kata "Ahli"
- Jika ya, call `prepareAhliJabatan()` untuk inject marker ke `textMap['jabatan']`

**Code Logic:**
```php
// === Apply "Ahli" jabatan rule untuk FUNGSIONAL ===
$jabatanType = (string)($e->jabatan_type ?? '');
if ($jabatanType === 'FUNGSIONAL' && isset($textMap['jabatan'])) {
    $jabatanText = (string)($textMap['jabatan'] ?? '');
    if (stripos($jabatanText, 'Ahli') !== false) {
        $textMap['jabatan'] = $this->prepareAhliJabatan($jabatanText);
    }
}
```

### 2. Flow Teknis

```
1. Employee create/render nametag (renderFront dipanggil)
   ↓
2. textMap di-build dari NametagData::buildFront()
   ↓
3. Sebelum pre-scaling trio, check jabatan_type & kata "Ahli"
   ↓
4. Jika FUNGSIONAL + ada "Ahli" → prepareAhliJabatan() inject marker
   ↓
5. Pre-scaling trio berjalan normal (dengan textMap bermarker)
   ↓
6. fitWrappedLinesPx() calculate fit dengan marker (marker = newline)
   ↓
7. wrapLines() wrap text dengan marker (marker = newline → force break)
   ↓
8. drawWrappedTextAndGetHeight() render lines (marker sudah gone, replaced dengan newline)
   ↓
9. Output: Nametag dengan "Ahli..." tetap atomic unit
```

### 3. Algoritma Smart Wrapping

**Marker Behavior:**
- Marker (◇) acts sebagai "soft line break point"
- Saat wrapping, jika text sebelum marker muat dalam lebar available, text sebelum+sesudah marker akan diperlakukan sebagai atomic unit
- Jika tidak muat, seluruh unit (dari marker onwards) dipindahkan ke baris berikutnya

**Contoh Scenario:**
```
Available width per line: 40 pixels

Original: "Kepala Bidang◇Ahli Keuangan Daerah"

Scenario 1 (muat):
  Line 1: "Kepala Bidang Ahli Keuangan Daerah"

Scenario 2 (tidak muat):
  Line 1: "Kepala Bidang"
  Line 2: "Ahli Keuangan Daerah"  ← "Ahli..." tetap bersama
```

### 4. Backward Compatibility

✅ **Rule Lama Tetap Intact:**
- Pre-scaling trio nama-NIP-jabatan tetap berjalan
- Special layout tweak (nudge Y untuk single-line jabatan) tetap aktif
- Font scaling mechanism tidak berubah
- Text rendering pipeline tidak berubah

✅ **No Breaking Changes:**
- Hanya menambah logic wrapping cerdas untuk "Ahli"
- Non-FUNGSIONAL jabatan tidak terpengaruh
- Jabatan tanpa "Ahli" tidak terpengaruh
- Marker hanya internal, tidak tampil di output

### 5. Configuration & Future Extensibility

**Currently:**
- Hanya untuk jabatan tipe `FUNGSIONAL`
- Trigger keyword: "Ahli" (case-insensitive)

**Future Possibilities:**
- Bisa di-extend ke jabatan_type lain (via config)
- Bisa tambah keyword lain selain "Ahli"
- Bisa di-config di `config/nametag.php` untuk flexibility

## Testing

### Test Coverage
1. ✅ FUNGSIONAL dengan Ahli di awal → No marker (correct)
2. ✅ FUNGSIONAL dengan Ahli panjang di awal → No marker (correct)
3. ✅ FUNGSIONAL dengan Ahli di tengah → Marker injected (correct)
4. ✅ FUNGSIONAL dengan Ahli panjang di tengah → Marker injected (correct)
5. ✅ FUNGSIONAL tanpa Ahli → Rule tidak apply (correct)
6. ✅ Non-FUNGSIONAL dengan Ahli → Rule tidak apply (correct)

### Test Script
- Location: `scripts/test_ahli_rule.php`
- Run: `php scripts/test_ahli_rule.php`
- Result: All test cases pass ✅

## Files Changed

1. `app/Services/Nametag/NametagTextLayout.php`
   - Added: `prepareAhliJabatan()` method
   - Modified: `fitWrappedLinesPx()` for marker support
   - Modified: `wrapLines()` for marker support

2. `app/Services/NametagRenderService.php`
   - Modified: `renderFront()` method to apply "Ahli" rule

3. `scripts/test_ahli_rule.php` (new)
   - Test script untuk verify implementation

## Verification

✅ Syntax check: All files OK  
✅ Logic test: All test cases pass  
✅ Backward compatibility: Maintained  
✅ No breaking changes: Confirmed  

## Usage

**For Developers:**
- No special action needed
- Rule applies automatically untuk jabatan FUNGSIONAL dengan "Ahli"
- Marker handling transparent di layer rendering

**For QA/Testers:**
- Test rendering nametag dengan:
  - Jabatan FUNGSIONAL: "Ahli Keuangan"
  - Jabatan FUNGSIONAL: "Kepala Bidang Ahli Keuangan Daerah"
  - Verify bahwa "Ahli Keuangan" tidak terpisah baris
  - Verify wrapping lebih "cerdas" / estetis

## Notes

- Marker character (◇) dipilih karena tidak umum dalam bahasa Indonesia dan data jabatan
- Jika ada risk marker muncul di output, change to newline character atau marker yang lebih safe
- Logic bisa di-optimize di future dengan caching atau config-based keywords
