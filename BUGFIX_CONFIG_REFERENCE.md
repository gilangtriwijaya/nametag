# SUMMARY: Config Reference Bug Fix

## Problem Identified

When doing batch render (NametagController@run or NametagBatchController batch jobs), 
the "Ahli" rule was being affected differently than single render.

**Root Cause: Shared Config Array Reference**

File: `app/Services/NametagRenderService.php` line 99 (renderFront) and line 413 (renderBack)

```php
// OLD CODE - Creates REFERENCE to config, not copy
$items = $cfgFront['texts'] ?? [];
```

In PHP, when you assign an array to another variable like above, you get a **reference** 
to the same underlying array, not a copy.

### What This Means:

1. **First render call**: Load $items from config
2. **Pre-scaling logic modifies**: `$items[$idx]['wrap'] = 1` and `$items[$idx]['__scaled_px'] = 123.45`
3. **Config automatically updated**:  The config array now permanently has `wrap=1`!
4. **Second render call**: Load $items from config - but config still has `wrap=1` from before!
5. **Result**: Each subsequent render starts with a modified config state

### Batch Impact:

When batch rendering multiple employees:
- First employee: Config state = NORMAL → render produces result A
- Second employee: Config state = MODIFIED from first render → render produces result B  
- Third employee: Config state = DOUBLY MODIFIED → render produces result C

This explains why Ahli rule behavior differed between single (1 render) and batch (N renders).

## Solution Applied

Use `array_merge()` to create an actual COPY:

```php
// FIX: Create actual copy of config, not reference
$items = array_merge([], $cfgFront['texts'] ?? []);
```

Now each render gets a fresh, unpolluted copy of the config items array.

## Files Modified

1. **app/Services/NametagRenderService.php** (renderFront method, line 99)
   - Changed: `$items = $cfgFront['texts'] ?? [];`
   - To: `$items = array_merge([], $cfgFront['texts'] ?? []);`

2. **app/Services/NametagRenderService.php** (renderBack method, line 413)
   - Changed: `$items = $cfgBack['texts'] ?? [];`
   - To: `$items = array_merge([], $cfgBack['texts'] ?? []);`

## Testing

To verify fix is working:
```bash
# Render same employee multiple times in a row
php artisan nametag:re-render 6 6 6  # 3 times should produce identical output

# Or test batch sync render (which is what user reported)
# Use NametagController@run to test batch
```

## Related Files

- app/Http/Controllers/NametagController.php - NametagController@run (batch sync)
- app/Http/Controllers/NametagBatchController.php - NametagBatchController@dispatch (batch queue)
- app/Services/NametagOrchestrator.php - batchGenerate() method
- app/Services/NametagRenderService.php - renderFront(), renderBack() methods (FIXED)
- app/Jobs/RenderSingleNametagJob.php - Per-employee render job
- app/Jobs/RenderNametagBatchJob.php - Batch dispatcher job

## Why This Fixes Ahli Rule Issue

The "Ahli atomic" post-processing rule depends on:
- Pre-scaling logic setting `wrap` value correctly
- ensureAhliAtomicAfterWrap() applying based on that wrap value

When config was polluted by previous render:
- wrap value might already be 1 instead of 2 (pre-set)
- Pre-scaling becomes ineffective or behaves unexpectedly  
- Ahli atomic post-process may not trigger correctly
- Result: Ahli still forced to next line even when it shouldn't

With the fix:
- Each render starts with clean config
- Pre-scaling works consistently across all renders
- Ahli rule behavior is identical regardless of batch order
