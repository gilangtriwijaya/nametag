<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\EmployeeImportService;

class ProcessEmployeeImportJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $previewId;
    public $previewPath;
    public $jobId;

    public function __construct(string $previewId)
    {
        $this->previewId = $previewId;
        $this->jobId = 'import_job_' . time() . '_' . Str::random(6);
    }

    public function handle(EmployeeImportService $service)
    {
        $statusPath = 'tmp/import_jobs/' . $this->jobId . '.json';
        if (!Storage::exists('tmp/import_jobs')) {
            Storage::makeDirectory('tmp/import_jobs');
        }
        Storage::put($statusPath, json_encode(['status' => 'running', 'progress' => 0]));

        try {
            // try to locate preview JSON on configured disks (public, then local)
            $preview = null;
            $previewDisk = null;
            if (Storage::disk('public')->exists('tmp/import_previews/' . $this->previewId)) {
                $json = Storage::disk('public')->get('tmp/import_previews/' . $this->previewId);
                $preview = json_decode($json, true);
                $previewDisk = 'public';
            } elseif (Storage::disk('local')->exists('tmp/import_previews/' . $this->previewId)) {
                $json = Storage::disk('local')->get('tmp/import_previews/' . $this->previewId);
                $preview = json_decode($json, true);
                $previewDisk = 'local';
            }

            if (empty($preview)) {
                Storage::put($statusPath, json_encode(['status' => 'failed', 'message' => 'Preview not found on configured disks']));
                return;
            }

            // process and get result
            $result = $service->processPreview($preview, function($percent) use ($statusPath) {
                // update progress
                Storage::put($statusPath, json_encode(['status' => 'running', 'progress' => $percent]));
            });

            // store result
            Storage::put($statusPath, json_encode(['status' => 'done', 'progress' => 100, 'result' => $result]));

            // generate errors file if any
            if (!empty($result['errors'])) {
                if (!Storage::exists('tmp/import_errors')) {
                    Storage::makeDirectory('tmp/import_errors');
                }
                $errorsFile = 'employee_import_errors_' . time() . '_' . Str::random(6) . '.csv';
                $fp = fopen(storage_path('app/tmp/import_errors/' . $errorsFile), 'w');
                fputcsv($fp, ['row', 'errors']);
                foreach ($result['errors'] as $err) {
                    fputcsv($fp, [$err['row'], implode('; ', $err['errors'])]);
                }
                fclose($fp);
                // attach errors file name to status
                $status = json_decode(Storage::get($statusPath), true);
                $status['errors_file'] = $errorsFile;
                Storage::put($statusPath, json_encode($status));
            }

            // cleanup preview and upload file: try to delete upload using both public and local disks
            if (!empty($preview['upload_path'])) {
                try {
                    if (Storage::disk('public')->exists($preview['upload_path'])) {
                        Storage::disk('public')->delete($preview['upload_path']);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                try {
                    if (Storage::disk('local')->exists($preview['upload_path'])) {
                        Storage::disk('local')->delete($preview['upload_path']);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            // delete preview JSON from whichever disk it was on (or both)
            try { Storage::disk('public')->delete('tmp/import_previews/' . $this->previewId); } catch (\Throwable $e) {}
            try { Storage::disk('local')->delete('tmp/import_previews/' . $this->previewId); } catch (\Throwable $e) {}

        } catch (\Throwable $e) {
            Storage::put($statusPath, json_encode(['status' => 'failed', 'message' => $e->getMessage()]));
        }
    }
}
