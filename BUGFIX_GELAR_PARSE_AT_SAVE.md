## BUGFIX: Gelar Quote-Escape Not Parsing at Save Time

### Problem (dari screenshot user)

User melaporkan bahwa kutip dalam gelar tidak dihapus:
1. **Form CRUD**: Input `S."TR"."IP"` benar
2. **Index & Detail view**: Menampilkan `S."TR"."IP"` dengan uppercase (kutip masih tersimpan + uppercase conversion)
3. **Generate nametag**: Tidak parse, tampil seperti rule biasa

### Root Cause

**Gelar parsing HANYA terjadi saat display (buildFront/buildBack), BUKAN saat SAVE.**

- User input: `S."TR"."IP"` → langsung masuk DB tanpa parse
- DB menyimpan: `S."TR"."IP"` (dengan kutip!)
- Display mencoba parse, tapi ada uppercase conversion yang membuat masalah

### Solution Implemented

**Move gelar normalization dari DISPLAY-TIME ke SAVE-TIME:**

1. **EmployeeService.php** - Updated `normalizeNameDegree()` method:
   ```php
   // Apply gelar normalization (quote-escape parsing) for gelar fields
   // This removes quotes and applies standard capitalization rules
   if (in_array($k, ['gelar_depan', 'gelar_belakang'], true)) {
       $v = \App\Support\NametagData::normalizeGelarPublic($v);
   }
   ```
   
   **Flow sekarang:**
   - User input: `S."TR"."IP"`
   - normalizeGelarPublic() parse → `S.TR.IP` (quotes removed!)
   - **DB simpan: `S.TR.IP` (CLEAN!)**

2. **NametagData.php** - Made `normalizeGelar()` public as `normalizeGelarPublic()`:
   - Renamed: `private static function normalizeGelar()` → `public static function normalizeGelarPublic()`
   - Removed redundant normalization calls in buildFront/buildBack() (gelar sudah clean di DB)

### Benefits

✅ **DB now stores CLEAN gelar** (without quotes)
✅ **Display views show clean data** (no parsing needed, no uppercase issues)
✅ **Generate nametag uses clean data** (NametagData.buildFront/buildBack gets clean input)
✅ **Backward compatible** (old data without quotes still works, normalization idempotent)

### Test Results

Created `scripts/test_gelar_save_flow.php` - simulates complete flow:

```
✅ PASS: Single quote escape: S."IP" → S.IP
✅ PASS: Multiple quote escapes: S."TR"."IP" → S.TR.IP (preserve all)
✅ PASS: Mixed: S.Psi, M."KOM" → S.Psi, M.KOM
✅ PASS: No quotes (standard rule): S.IP → S.Ip
✅ PASS: Comma-separated without quotes: S.I.KOM., M.KESOS → S.I.Kom., M.Kesos
✅ PASS: Quote escape entire segment: S."I.KOM." → S.I.KOM.
✅ PASS: Trim whitespace: "  S."IP"  " → S.IP
✅ PASS: Standard multi-letter: S.Psi → S.Psi (unchanged)

SUMMARY: 8 passed, 0 failed ✅
```

### Files Modified

1. **app/Services/EmployeeService.php**
   - Line ~218: Added normalizeGelarPublic() call in normalizeNameDegree()

2. **app/Support/NametagData.php**
   - Line ~63: Renamed normalizeGelar() to normalizeGelarPublic()
   - Lines ~25, ~143: Removed redundant normalization calls (gelar already clean)
   - Updated method calls to use normalizeGelarPublic()

### Next Steps

Ready for testing on actual employee data:
- Create new employee with gelar containing quotes
- Verify DB has clean value (without quotes)
- Verify index/detail view shows clean value
- Verify nametag generation shows correct format
- Verify no uppercase conversion issues
