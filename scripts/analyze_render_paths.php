<?php
/**
 * Diagram: Semua path render nametag dan posisi Ahli rule
 */

echo <<<'DIAGRAM'
=== SEMUA PATH RENDER NAMETAG ===

PATH 1: Single Generate (show blade "Proses/Perbarui" button)
  NametagController@store
    -> NametagOrchestrator::generateSingle($employee, $force)
       -> renderForEmployee()
          -> NametagRenderService::renderFront($employee, $template)
             -> drawWrappedTextAndGetHeight(..., $applyAhliPostProcess=true if FUNGSIONAL, $originalTextForAhli)
                -> wrapLines() + IF ($applyAhliPostProcess) ensureAhliAtomicAfterWrap()
                
✓ FLOW AMAN:
  - Employee object passed directly, jabatan_type available
  - renderFront called with jabatan_type check enabled


PATH 2: Batch Generate OLD (NametagController@run)
  NametagController@run
    -> load employees with Employee::query()->where('status_aktif','AKTIF')->limit()
    -> NametagOrchestrator::batchGenerate($rows, $options)
       -> foreach $e in $rows
          -> renderForEmployee($e)
             -> NametagRenderService::renderFront($e, $template)
                -> drawWrappedTextAndGetHeight( ..., $applyAhliPostProcess=true if FUNGSIONAL, ...)
                   -> wrapLines() + ensureAhliAtomicAfterWrap()

✓ FLOW AMAN:
  - Employee objects already loaded with all attributes including jabatan_type
  - Same renderFront logic as PATH 1
  - BUT: This is synchronous (blocking), runs inline


PATH 3: Batch Generate NEW (NametagBatchController@dispatch)
  NametagBatchController@dispatch
    -> validate IDs
    -> RenderNametagBatchJob::dispatch($ids, $userId, $batch, $options)
       -> RenderNametagBatchJob::handle()
          -> foreach $id in $ids
             -> RenderSingleNametagJob::dispatch($id, ...)
          
       -> RenderSingleNametagJob::handle(NametagRenderService $renderer)
          -> $e = Employee::find($this->employeeId)  ← RELOAD FROM DB!
          -> $renderer->renderFront($e, $template)
             -> drawWrappedTextAndGetHeight(..., $applyAhliPostProcess=true if $e->jabatan_type=FUNGSIONAL, ...)
                -> wrapLines() + ensureAhliAtomicAfterWrap()

✓ FLOW SHOULD BE SAFE:
  - Employee reloaded from DB in job handle()
  - jabatan_type should be loaded
  - Same renderFront logic


QUESTION: Dimana masalahnya?
==========================================================

Jika PATH 1 (single) aman dan PATH 3 (queue-based) aman, 
maka user mungkin menggunakan PATH 2 (NametagController@run) 
atau ada edge case di queue handling.

Atau... kemungkinan lain:
- Ada conditional logic yang berbeda?
- Ada konfigurasi yang di-override?
- Ada caching yang tidak tepat?
- Ada timing issue pada queue?

DIAGRAM;

echo "\n\nCEK: Apakah ada difference di configuration atau conditional?\n";
echo "Mari trace lebih dalam ke drawWrappedTextAndGetHeight\n";
