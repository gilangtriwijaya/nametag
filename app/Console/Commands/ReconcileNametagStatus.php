<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;

/**
 * Reconcile nametag_status based on actual file existence.
 * 
 * If a nametag file (front or back) exists but DB status is 'processing' or 'queued',
 * marks it as 'ready' since the actual render succeeded.
 * This prevents stuck status states when job updates fail.
 */
class ReconcileNametagStatus extends Command
{
    protected $signature = 'nametag:reconcile-status {--dry-run}';
    protected $description = 'Reconcile nametag status based on file existence (fix stuck processing)';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');
        
        $this->info('Reconciling nametag status based on file existence...');
        
        // Find all employees with 'processing' or 'queued' status
        $stuck = Employee::whereIn('nametag_status', ['processing', 'queued'])->get();
        
        $this->info("Found " . $stuck->count() . " employees with processing/queued status");
        
        if ($stuck->isEmpty()) {
            $this->info("✅ No stuck records found");
            return 0;
        }
        
        $fixed = 0;
        foreach ($stuck as $emp) {
            $frontFile = public_path("nametag/front/{$emp->id}.png");
            $backFile = public_path("nametag/back/{$emp->id}.png");
            
            $frontExists = file_exists($frontFile);
            $backExists = file_exists($backFile);
            
            // If either file exists, the render succeeded - mark as ready
            if ($frontExists || $backExists) {
                $this->line("ID {$emp->id} - {$emp->nama}");
                $this->line("  Status: {$emp->nametag_status} → ready");
                $this->line("  Files: front=" . ($frontExists ? "✅" : "❌") . " back=" . ($backExists ? "✅" : "❌"));
                
                if (!$dryRun) {
                    try {
                        $emp->update([
                            'nametag_status' => 'ready',
                            'nametag_error' => null,
                        ]);
                        $fixed++;
                        $this->line("  ✅ Updated");
                    } catch (\Throwable $ex) {
                        $this->error("  ❌ Failed to update: " . $ex->getMessage());
                    }
                } else {
                    $fixed++;
                    $this->line("  [DRY-RUN] Would update");
                }
                $this->line("");
            }
        }
        
        $this->info("=" . str_repeat("=", 50));
        if ($dryRun) {
            $this->info("DRY-RUN: Would fix $fixed stuck records");
            $this->info("Run without --dry-run to apply changes");
        } else {
            $this->info("✅ Fixed $fixed stuck records");
        }
        
        return 0;
    }
}
