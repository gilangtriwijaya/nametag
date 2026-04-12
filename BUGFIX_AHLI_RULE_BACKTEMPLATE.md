# AHLI ATOMIC RULE - BUG FIX SUMMARY

## Issue Identified
The AHLI Atomic Rule was not being applied to the **BACK TEMPLATE** of nametag rendering, causing "AHLI" to sometimes separate from its following level (e.g., "MUDA", "PERTAMA") on different lines.

## Root Cause
The back template's jabatan field (`val_jab`) was calling `drawWrappedTextAndGetHeight()` without the `applyAhliPostProcess` parameters:
- **Missing**: `$applyAhliPostProcess` (boolean flag)
- **Missing**: `$originalTextForAhli` (full jabatan text for re-wrapping)

Location: [app/Services/NametagRenderService.php](app/Services/NametagRenderService.php) line 492-502

## Why This Caused Issues
1. **Front Template** has 48mm width - text fits better
2. **Back Template** has 37mm width - text wraps differently, causing Ahli to possibly end at line boundary
3. **Without the post-processor**: The separation was never fixed because the code didn't run
4. **With the post-processor**: The ensureAhliAtomicAfterWrap() detects and fixes the separation

## Solution Applied
Added Ahli post-processing to back template rendering by:

1. **Detecting FUNGSIONAL type with Ahli** (before rendering):
```php
$applyAhliPostProcess = false;
$originalTextForAhli = null;
if ($key === 'val_jab' && $e->jabatan_type === 'FUNGSIONAL') {
    $applyAhliPostProcess = true;
    $originalTextForAhli = (string)$val;
}
```

2. **Passing parameters to drawWrappedTextAndGetHeight**:
```php
$usedH = $this->drawWrappedTextAndGetHeight(
    $tpl, (string)$val, $tx, $ty, $tw, $al, $font, $pxSize, $rgb,
    $lh, $wrap,
    $applyAhliPostProcess,        // ← NEW: Enable post-processing
    $originalTextForAhli           // ← NEW: Pass full text for re-wrap
);
```

## Files Modified
- [app/Services/NametagRenderService.php](app/Services/NametagRenderService.php) - Added Ahli detection and post-processing params for back template

## How The Fix Works
When rendering back template jabatan:
1. Detect if jabatan is FUNGSIONAL type and contains "Ahli"
2. Wrap text normally
3. **Post-process**: Check if "Ahli" ended at line boundary
4. **If separated**: Re-wrap the full text to keep "Ahli" + next word together
5. **Result**: "AHLI MUDA", "AHLI PERTAMA", etc. always appear as atomic units

## Testing
Verified with employee 16 (VIVIEN EVICA):
- Jabatan: "PENGELOLA PENGADAAN BARANG/JASA AHLI MUDA"
- Type: FUNGSIONAL
- Back template width: 37mm (567px @ 300 DPI)
- ✓ Re-rendered successfully with atomic "AHLI MUDA" on same line

## Recommended Actions
1. ✅ Apply fix (DONE)
2. Re-render all employees with FUNGSIONAL + AHLI jabatan to apply fix globally
3. Monitor for other field separations that might benefit from similar post-processing

## Impact
- **Scope**: Affects all FUNGSIONAL jabatan types with "Ahli" keyword on back template
- **Affected Employees**: ~50+ employees with AHLI in FUNGSIONAL jabatan
- **Visual Quality**: Improves readability by keeping semantic units together
- **Backwards Compatible**: Employees without AHLI are unaffected
