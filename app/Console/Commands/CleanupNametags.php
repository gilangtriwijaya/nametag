<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupNametags extends Command
{
    protected $signature = 'nametag:cleanup {--days=3} {--dry-run}';
    protected $description = 'Delete generated nametag files older than N days (default 3). Keeps DB records.';

    public function handle(): int
    {
        $days = (int)$this->option('days');
        $dry  = (bool)$this->option('dry-run');

        $cutoff = time() - ($days * 86400);
        $dirs = [public_path('nametag/front'), public_path('nametag/back')];

        $deleted = 0;
        foreach ($dirs as $dir) {
            if (!File::isDirectory($dir)) continue;
            $files = File::files($dir);
            foreach ($files as $f) {
                $path = $f->getPathname();
                $mtime = @filemtime($path) ?: 0;
                if ($mtime > 0 && $mtime <= $cutoff) {
                    if ($dry) {
                        $this->line("DRY: would delete {$path} (modified: " . date('c', $mtime) . ")");
                    } else {
                        try {
                            File::delete($path);
                            $this->info("Deleted: {$path}");
                            $deleted++;
                        } catch (\Throwable $e) {
                            $this->error("Failed to delete {$path}: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        if ($dry) {
            $this->info('Dry-run finished. No files were deleted.');
        } else {
            $this->info("Finished. Files deleted: {$deleted}");
        }

        return 0;
    }
}
