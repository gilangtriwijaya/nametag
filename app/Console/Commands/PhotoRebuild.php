<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Services\EmployeePhotoProcessor;
use App\Services\JobPhotoStyle;

class PhotoRebuild extends Command
{
    protected $signature = 'photo:rebuild {--employee=} {--job-type=} {--all}';
    protected $description = 'Rebuild derived employee photos by employee or job-type or all';

    public function handle()
    {
        $empId = $this->option('employee');
        $jobType = $this->option('job-type');
        $all = $this->option('all');

        $q = Employee::query();
        if ($empId) $q->where('id', (int)$empId);
        if ($jobType) $q->whereRaw('UPPER(jabatan_type) = ?', [mb_strtoupper($jobType)]);
        if (!$empId && !$jobType && !$all) {
            $this->error('Specify --employee or --job-type or --all');
            return 1;
        }

        $proc = app(EmployeePhotoProcessor::class);

        $this->info('Starting rebuild...');
        $q->chunkById(100, function($rows) use ($proc) {
            foreach ($rows as $e) {
                $src = public_path(ltrim((string)$e->foto_path, '/'));
                if (!is_file($src)) {
                    // try originals
                    $orig = public_path('uploads/originals/employees/' . $e->id . '.jpg');
                    if (is_file($orig)) $src = $orig; else { $this->warn("skip {$e->id}: no source"); continue; }
                }

                $style = JobPhotoStyle::forJob($e->jabatan_type ?? $e->jabatan);
                $out = $proc->process($src, $style->bgColor(), config('photo_pipeline.version'), $e->jabatan_type);
                $this->info("{$e->id}: " . ($out ? 'ok' : 'failed'));
            }
        });

        $this->info('Done.');
        return 0;
    }
}
