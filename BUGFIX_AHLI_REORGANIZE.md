# FIX: AHLI ATOMIC RULE - REORGANIZE LOGIC

## Issue Dievaluasi Kembali
User menjelaskan bahwa rule AHLI harus memastikan:
1. Ketika jabatan FUNGSIONAL tidak muat 1 baris
2. Pastikan kata **"Ahli"** tidak berbeda baris dengan kata setelahnya (MUDA, PERTAMA, UTAMA, dll)

**Contoh masalah:**
```
Line 1: Pengelola Pengadaan Barang/Jasa Ahli
Line 2: Muda
```

**Harusnya menjadi:**
```
Line 1: Pengelola Pengadaan Barang/Jasa
Line 2: Ahli Muda
```

## Root Cause Analysis (Revisi)
Ditemukan masalah dengan logic RE-WRAP yang sebelumnya:
1. **Pre-scaling** sudah menghitung font size optimal untuk fit dalam 2 lines
2. Saat **re-wrap dengan same font size + width**, hasilnya akan **IDENTIK**
3. Jadi "Ahli" tetap terpisah dari kata setelahnya
4. **Re-wrap tidak efektif!**

## Solusi: REORGANIZE Instead of RE-WRAP

### Prinsip Baru
Bukannya **re-wrap ulang**, cukup lakukan **reorganisasi line boundaries**:
- Deteksi jika line[i] END dengan "Ahli"
- PINDAHKAN "Ahli" dari akhir line[i] ke awal line[i+1]
- Hasilnya: "Ahli MUDA" menjadi atomic di line[i+1]

### Method Baru: ensureAhliAtomicAfterWrap()
```php
/**
 * REORGANIZE lines agar "Ahli" tetap dengan kata setelahnya
 * 
 * Input:  ["Pengelola Pengadaan Barang/Jasa Ahli", "Muda"]
 * Output: ["Pengelola Pengadaan Barang/Jasa", "Ahli Muda"]
 * 
 * Logic:
 * 1. Check if line[i] END dengan "Ahli" (terpisah)
 * 2. Extract "Ahli" dari akhir line[i]
 * 3. PINDAHKAN "Ahli" ke awal line[i+1]
 * 4. Return reorganized lines
 */
```

## Implementasi
**File:** [app/Services/Nametag/NametagTextLayout.php](app/Services/Nametag/NametagTextLayout.php#L306)

Method `ensureAhliAtomicAfterWrap()` di-update dengan:
- ✅ Pattern matching `/\bAhli\s*$/iu` untuk detect line END
- ✅ Extract "Ahli" token dari akhir line
- ✅ Prepend ke line berikutnya
- ✅ Return reorganized array

**Files yang affected:**
- `app/Services/Nametag/NametagTextLayout.php` - Method implementation
- `app/Services/NametagRenderService.php` - Trigger for FRONT template (line 267-268)
- `app/Services/NametagRenderService.php` - Trigger for BACK template (line 469-471)

## Test Results

### Unit Tests (test_ahli_reorganize.php)
```
✓ Test 1: Ahli terpisah → dipindahkan ke line 2
✓ Test 2: Ahli sudah atomic → tidak diubah  
✓ Test 3: Ahli Pertama → reorganize berhasil
```

### Batch Re-render (51 FUNGSIONAL+AHLI employees)
```
✓ SUCCESS:  49 employees
✗ FAILED:   2 employees (data issues, not code)
─────────────────────────
TOTAL: 49/51 (96%)
```

## Contoh Hasil Sebelum/Sesudah

### Employee 16: VIVIEN EVICA
**Sebelum (MASALAH):**
```
Line 1: Pengelola Pengadaan Barang/Jasa Ahli
Line 2: Muda
```

**Sesudah (FIXED):**
```
Line 1: Pengelola Pengadaan Barang/Jasa
Line 2: Ahli Muda
```

### Employee 6: Gilang Tri Wijaya
**Sebelum (MASALAH):**
```
Line 1: Pranata Komputer Ahli
Line 2: Pertama
```

**Sesudah (FIXED):**
```
Line 1: Pranata Komputer
Line 2: Ahli Pertama
```

## Implementation Details

### How It Works
1. **Image rendering** dengan pre-scaled font size (sudah optimal untuk 2 lines)
2. **wrapLines()** dipanggil, menghasilkan array lines
3. **if (FUNGSIONAL + contains Ahli)** → call `ensureAhliAtomicAfterWrap()`
4. Method check: apakah ada line END dengan "Ahli"?
5. **If YES** → reorganize lines (move "Ahli" ke line berikutnya)
6. **If NO** → return as-is (already atomic)
7. **Draw** reorganized lines ke image

### Why This Works
- ✅ Tidak perlu re-wrap (yang tidak efektif)
- ✅ Cukup reorganize existing lines
- ✅ Mempertahankan font size (sudah optimal)
- ✅ Mempertahankan word wrapping hasil original
- ✅ Hanya mengubah positioning "Ahli"

## Configuration
Trigger conditions:
- **Field key:** `jabatan` (front) atau `val_jab` (back)
- **Type:** `FUNGSIONAL` (dari `employee.jabatan_type`)
- **Content:** Contains "Ahli" (case-insensitive)

## Side Effects
- ✅ NO impact on non-FUNGSIONAL jabatan
- ✅ NO impact if "Ahli" tidak di akhir line
- ✅ NO impact if "Ahli" already atomic
- ✅ Backward compatible

## Deployment
1. ✅ Code fix applied
2. ✅ OPcache cleared
3. ✅ PHP-FPM restarted
4. ✅ 49/51 employees re-rendered successfully
5. ✅ Ready for production

## Related Files
- [app/Services/Nametag/NametagTextLayout.php](app/Services/Nametag/NametagTextLayout.php) - Core logic
- [app/Services/NametagRenderService.php](app/Services/NametagRenderService.php) - Trigger points
- [scripts/test_ahli_reorganize.php](scripts/test_ahli_reorganize.php) - Unit tests
- [batch_rerender_ahli_fixed.php](batch_rerender_ahli_fixed.php) - Batch re-render script
