# Summary: Active Rules untuk Generate Jabatan pada Nametag Front

**Last Updated**: 2026-02-18  
**Status**: All rules ✅ ACTIVE

---

## 📋 Daftar Lengkap Rule yang Aktif

### 1. 🎯 AHLI JABATAN ATOMIC RULE

**Status**: ✅ ACTIVE  
**Target**: Jabatan tipe `FUNGSIONAL` yang mengandung kata "Ahli"  
**Purpose**: Menjaga "Ahli" dan semua kata setelahnya tetap dalam satu baris (atomic unit)

#### Implementation Details

| Aspek | Detail |
|-------|--------|
| **File** | `app/Services/Nametag/NametagTextLayout.php` |
| **Method** | `ensureAhliAtomicAfterWrap()` (private) |
| **Trigger** | renderFront() saat `key === 'jabatan'` AND `jabatan_type === 'FUNGSIONAL'` |
| **Line** | 279-320 (NametagTextLayout.php) |
| **Detection Pattern** | `/\bAhli\s*$/iu` (case-insensitive word boundary) |

#### How It Works

```
Flow:
1. Text wrapped normally (standard line wrapping)
2. Check setiap line apakah END dengan "Ahli" (terpisah dari kata berikutnya)
3. Jika YES:
   - Extract "Ahli ..." phrase dari full text
   - Remove "Ahli" dari baris saat ini
   - Combine: [line_without_ahli] + [ahli_phrase]
   - Re-wrap combined text
   - Replace lines di array
4. Jika NO: return lines as-is (sudah atomic)
```

#### Example Behavior

**Scenario 1: Already Atomic (tidak perlu fix)**
```
Full text: "Kepala Bidang Ahli Keuangan Daerah"

Wrap result:
  Line 1: "Kepala Bidang Ahli Keuangan"
  Line 2: "Daerah"

Detection: "Ahli Keuangan" di tengah line 1 (tidak di end)
Action: ✅ Leave as-is (sudah atomic)
```

**Scenario 2: Separated Ahli (perlu fix)**
```
Full text: "Kepala Bidang Ahli Keuangan Utama"

Before fix:
  Line 1: "Kepala Bidang Ahli"         ← ⚠️ Ahli at END
  Line 2: "Keuangan Utama"

Detection: "Ahli" di END line 1, terpisah dari "Keuangan Utama"
Action: Re-wrap
After fix:
  Line 1: "Kepala Bidang"
  Line 2: "Ahli Keuangan Utama"       ← ✅ Atomic sekarang!
```

#### Regex Pattern

```regex
/\bAhli\s*$/iu
```

- `\b` = word boundary
- `Ahli` = literal string (case-insensitive karena 'i' flag)
- `\s*` = optional whitespace
- `$` = end of string
- `u` = UTF-8 mode
- `i` = case-insensitive

---

### 2. 📊 PRE-SCALING TRIO RULE (Nama-NIP-Jabatan)

**Status**: ✅ ACTIVE  
**Target**: Ketiga field utama: `nama`, `nip`, `jabatan`  
**Purpose**: Scale ketiga field secara proporsional agar tampil harmonis

#### Implementation Details

| Aspek | Detail |
|-------|--------|
| **File** | `app/Services/NametagRenderService.php` |
| **Method** | `renderFront()` - pre-scaling section (line 151-190) |
| **Trigger** | Default untuk semua nametag (tidak conditional) |

#### How It Works

```
Algorithm:
1. Get base font sizes untuk ketiga field (dari config/template)
2. Calculate fitSingleLinePx() untuk NAMA @ base size
   → min size agar nama muat 1 baris
3. Calculate fitWrappedLinesPx() untuk JABATAN @ base size (max 2 lines)
   → min size agar jabatan muat 2 baris
4. Calculate scale = MIN(nama_fit, jabatan_fit, 1.0)
   → ambil paling ketat, jangan scale UP
5. Apply scale ke semua tiga field
```

#### Key Parameters

- **Line height (front)**: 1.5x (dari `config/nametag.php`)
- **Max lines jabatan**: 2
- **Max lines nama**: 1
- **Font family**: Primary (OpenSans)
- **Available width**: Dari template slot definition

#### Example

```
Template available width: 40mm = 480px @ 300 DPI

Scenario:
  nama: "DEDI EKA PUTRA"
  jabatan: "Ahli Keuangan Daerah"

Step 1: Fit nama @ base 5.5mm
  → needs 4.2mm to fit 1 line
  → scale_nama = 4.2 / 5.5 = 0.76

Step 2: Fit jabatan @ base 5mm
  → needs 5.8mm to wrap 2 lines
  → scale_jabatan = 5.0 / 5.8 = 0.86

Step 3: Apply scale
  → scale = MIN(0.76, 0.86, 1.0) = 0.76
  → nama font: 5.5 × 0.76 = 4.2mm
  → jabatan font: 5.0 × 0.76 = 3.8mm
  → nip font: scaled proportionally
```

---

### 3. ✨ GELAR NORMALIZATION RULE

**Status**: ✅ ACTIVE  
**Target**: `gelar_depan`, `gelar_belakang` fields  
**Purpose**: Normalize degree/title formatting (title-case, proper punctuation)

#### Implementation Details

| Aspek | Detail |
|-------|--------|
| **File** | `app/Support/NametagData.php` |
| **Method** | `normalizeGelar()` (private static) |
| **Trigger** | buildFront() saat ada gelar_belakang |
| **Char set**: UTF-8 (MB functions) |

#### Normalization Logic

```
Input: "S.IKOM., M.KESOS"

Process:
1. Split by comma: ["S.IKOM.", "M.KESOS"]
2. For each part:
   a. Split by dot (keep dots): ["S", ".", "I", ".", "K", ".", "O", ".", "M", "."]
   b. ucfirst each non-dot segment:
      - "S" → "S"
      - "I" → "I"
      - "K" → "K"
      - "O" → "O"
      - "M" → "M"
   c. Rejoin: "S.I.K.O.M."
3. Rejoin by comma: "S.I.K.O.M., M.K.E.S.O.S"

Actually more correct:
1. Split by comma: ["S.IKOM.", "M.KESOS"]
2. For each part:
   - Replace "IKOM" → "Ikom" (ucfirst whole segment)
   - Replace "KESOS" → "Kesos"
3. Result: ["S.Ikom.", "M.Kesos"]
4. Rejoin: "S.Ikom., M.Kesos"
```

#### Examples

| Input | Output | Notes |
|-------|--------|-------|
| `S.H.` | `S.H.` | Already correct |
| `S.H., M.HUKUM` | `S.H., M.Hukum` | Second part title-cased |
| `S.IKOM., M.KESOS` | `S.I.Kom., M.Kesos` | Degree case normalized |
| `DR. IR. BUDI SANTOSO` | `Dr. Ir. Budi Santoso` | Title-case each part |

---

### 4. 🎨 MULTI-LINE WRAPPING & TEXT FIT RULE

**Status**: ✅ ACTIVE  
**Target**: Semua text fields (nama, nip, jabatan, instansi, etc)  
**Purpose**: Smart text wrapping untuk fit dalam available space dengan optimal sizing

#### Implementation Details

| Aspek | Detail |
|-------|--------|
| **File** | `app/Services/Nametag/NametagTextLayout.php` |
| **Methods** | `fitSingleLinePx()`, `fitWrappedLinesPx()`, `wrapLines()` |
| **Algorithm** | Binary search for optimal font size |
| **Fallback** | If cannot fit: use smallest allowed size |

#### How It Works

**fitSingleLinePx()**
```
Purpose: Find min font size untuk text muat dalam 1 line

Algorithm:
1. Binary search: from 2px to 50px
2. For each size candidate:
   - Measure text width
   - If fits: try larger
   - If not fits: try smaller
3. Return minimum size that fits
```

**fitWrappedLinesPx()**
```
Purpose: Find min font size untuk text dengan multi-line wrapping

Parameters:
- text: string to fit
- maxLines: max number of lines allowed
- width: available width in pixels
- font: font file path
- size: base font size to test

Algorithm:
1. Binary search: from 2px to 50px
2. For each size candidate:
   - Wrap text to multiple lines
   - Count actual lines needed
   - If lines ≤ maxLines & fits width: try larger
   - Otherwise: try smaller
3. Return minimum size that fits within constraints
```

**wrapLines()**
```
Purpose: Actual text wrapping logic

Algorithm:
1. Start with first word
2. Try adding next word to current line
3. If fits: add and continue
4. If not fits: move word to next line
5. Repeat until all words processed

Returns: array of lines
```

#### Configuration

From `config/nametag.php`:
```php
'line_height' => [
    'front'   => 1.5,  // For front side
    'back'    => 1.5,  // For back side
    'default' => 1.25, // Fallback
],
```

---

## 📊 RULE EXECUTION ORDER

Saat merender nametag front (`renderFront()` method):

```
1. Load template image & setup dimensions
↓
2. Build text map from NametagData::buildFront()
   • Get nama (dengan gelar)
   • Get nip
   • Get jabatan apa adanya
↓
3. Apply gelar normalization (if ada gelar_belakang)
   ├─ Normalize degree formatting
   └─ Update textMap['nama']
↓
4. PRE-SCALING TRIO calculation
   ├─ Fit nama (single line)
   ├─ Fit jabatan (up to 2 lines)
   ├─ Calculate common scale factor
   └─ Store scaled font sizes in items[]
↓
5. For each text field in template:
   a. Get field config (position, width, font, size, etc)
   b. Get text value dari textMap
   c. Wrap text ke multiple lines (if needed)
   d. Special handling untuk jabatan field:
      ├─ if (jabatan_type === 'FUNGSIONAL' && contains 'Ahli')
      └─ → applyAhliPostProcess = true
   e. Calculate optimal font size (respecting scale)
   f. Draw text to image with line-height spacing
   g. Track vertical offset untuk next field
↓
6. Post-processing untuk jabatan (FUNGSIONAL + Ahli):
   ├─ Check if "Ahli" terpisah across lines
   ├─ If separated: re-wrap untuk keep atomic
   └─ If atomic: leave as-is
↓
7. Insert QR code (jika ada)
↓
8. Finalize image & save to public/nametag/front/
```

---

## 🧪 Testing & Verification

### Test Scripts Available

| Script | Location | Purpose |
|--------|----------|---------|
| `test_ahli_rule.php` | `scripts/test_ahli_rule.php` | Test Ahli atomic rule with various cases |
| `test_ahli_separation.php` | `scripts/test_ahli_separation.php` | Test detection of separated Ahli |
| `test_ahli_postprocess.php` | `scripts/test_ahli_postprocess.php` | Test post-process logic |

### How to Run Tests

```bash
cd /home/deploy/apps/nametag
php scripts/test_ahli_rule.php
php scripts/test_ahli_separation.php
php scripts/test_ahli_postprocess.php
```

---

## ⚡ Performance Notes

- **Ahli rule**: Only applied to FUNGSIONAL + "Ahli" (minimal overhead)
- **Pre-scaling trio**: Calculated once per render (no per-line penalty)
- **Wrapping**: Binary search capped at ~5-7 iterations
- **Font metrics**: Cached in-request (no repeated I/O)

---

## 🔧 Configuration Reference

### config/nametag.php Relevant Sections

```php
'line_height' => [
    'front' => 1.5,      // ← Used for jabatan wrapping calculation
    'back' => 1.5,
    'default' => 1.25,
],

'role_colors' => [      // ← Optional: color by job type
    'FUNGSIONAL' => '#a9aaad',
    // ...
],

'templates' => [
    'front' => [
        'texts' => [
            [
                'key' => 'jabatan',
                'w' => 40,              // ← Available width for wrapping
                'h' => 8,
                'wrap' => 2,            // ← Max lines
                // ...
            ],
            // ...
        ],
    ],
],
```

---

## 📝 Important Notes

### Rule Interactions

1. **Ahli rule** depends on pre-scaling being done first (font size already calculated)
2. **Gelar normalization** applied before pre-scaling, but doesn't affect scaling calculation
3. **Multi-line wrapping** happens independently for each field
4. Rules are **non-breaking and composable** (can all be active simultaneously)

### FUNGSIONAL Type Detection

The rule specifically checks:
```php
if ($key === 'jabatan' && $e->jabatan_type === 'FUNGSIONAL')
```

This means:
- ✅ Rule applies to FUNGSIONAL positions with "Ahli"
- ❌ Rule doesn't apply to other position types (PELAKSANA, PENGAWAS, etc)
- ❌ Rule doesn't apply if jabatan_type is NULL or missing

### Quality Assurance

All rules are:
- ✅ Tested with real data
- ✅ Backward compatible (existing nametags still render correctly)
- ✅ Configurable (can be extended or modified in future)
- ✅ Non-breaking (rule failures don't crash, fall back gracefully)

---

## 🚀 Future Extensibility

Rules can be extended:
- Add more keywords besides "Ahli"
- Apply Ahli rule to non-FUNGSIONAL types
- Configurable behavior via `config/nametag.php`
- Custom font sizing rules per position type
- Custom wrapping rules per OPD/role

---

**Last Verified**: 2026-02-18 02:30:00 UTC  
**All Rules**: ✅ ACTIVE & WORKING
