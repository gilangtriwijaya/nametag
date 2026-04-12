<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BackgroundRemovalService;

class CleanRembg extends Command
{
    protected $signature = 'rembg:clean {filename}';
    protected $description = 'Clean background for employee image using BackgroundRemovalService';

    public function handle(BackgroundRemovalService $bg)
    {
        $filename = $this->argument('filename');
        $path = public_path('uploads/employees/' . $filename);
        if (!is_file($path)) {
            $this->error('source_not_found: ' . $path);
            return 2;
        }

        $this->info('Processing ' . $path);
        $dst = $bg->clean($path);
        if ($dst) {
            $this->info('ok: ' . $dst);
            return 0;
        }

        $this->error('processing_failed');
        return 1;
    }
}
