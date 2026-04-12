<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupImportPreviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * --hours= number of hours to keep (default 24)
     */
    protected $signature = 'import:cleanup {--hours=24 : Files older than this (hours) will be removed}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup old import preview/upload/job/error files from storage';

    public function handle()
    {
        $hours = (int) $this->option('hours');
        $threshold = Carbon::now()->subHours($hours)->timestamp;

        $disks = ['public', 'local'];
        $targets = [
            'tmp/import_previews',
            'tmp/imports',
            'tmp/import_jobs',
            'tmp/import_errors',
        ];

        foreach ($disks as $disk) {
            foreach ($targets as $target) {
                try {
                    if (!Storage::disk($disk)->exists($target)) {
                        continue;
                    }

                    // delete files older than threshold
                    $files = Storage::disk($disk)->allFiles($target);
                    foreach ($files as $file) {
                        try {
                            $ts = Storage::disk($disk)->lastModified($file);
                            if ($ts <= $threshold) {
                                Storage::disk($disk)->delete($file);
                                $this->info("Deleted file {$disk}:{$file}");
                            }
                        } catch (\Throwable $e) {
                            // ignore individual file errors
                        }
                    }

                    // delete empty directories older than threshold
                    $dirs = Storage::disk($disk)->allDirectories($target);
                    foreach ($dirs as $dir) {
                        try {
                            $files = Storage::disk($disk)->files($dir);
                            if (empty($files)) {
                                // no files — remove directory if its modification is older than threshold
                                $full = Storage::disk($disk)->path($dir);
                                if (file_exists($full) && filemtime($full) <= $threshold) {
                                    Storage::disk($disk)->deleteDirectory($dir);
                                    $this->info("Deleted directory {$disk}:{$dir}");
                                }
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                } catch (\Throwable $e) {
                    $this->error("Error cleaning {$disk}:{$target} — " . $e->getMessage());
                }
            }
        }

        $this->info('Import preview cleanup completed.');
        return 0;
    }
}
