<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Models\NametagArchive;
use App\Services\NametagOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class CreateNametagArchiveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $employeeIds;
    public int $userId;
    public int $archiveId;

    public function __construct(array $employeeIds, int $userId, int $archiveId)
    {
        $this->employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $this->userId = $userId;
        $this->archiveId = $archiveId;
    }

    public function handle(NametagOrchestrator $orchestrator)
    {
        $archive = NametagArchive::find($this->archiveId);
        if (!$archive) return;

        $archive->status = 'processing';
        $archive->save();

        $tmpDir = storage_path('app/archives');
        if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

        $zipName = 'nametag_archive_' . now()->format('Ymd_His') . '_' . $this->archiveId . '.zip';
        $zipPath = $tmpDir . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $archive->status = 'failed';
            $archive->notes = 'failed_create_zip';
            $archive->save();
            return;
        }

        $added = 0;
        foreach ($this->employeeIds as $id) {
            try {
                /** @var Employee|null $e */
                $e = Employee::find($id);
                if (!$e) continue;

                // Ensure nametags exist; orchestrator->generateSingle will render if missing
                try {
                    $orchestrator->generateSingle($e, true);
                } catch (\Throwable $ex) {
                    Log::warning('nametag.archive_render_failed', ['employee_id' => $id, 'err' => $ex->getMessage()]);
                }

                $frontPath = public_path("nametag/front/{$id}.png");
                $backPath  = public_path("nametag/back/{$id}.png");

                $namePart = trim($e->nama ?: 'pegawai_' . $id);
                $nipPart  = trim((string) ($e->nip ?? '')) ?: (string)$id;
                $combined = $namePart . '_' . $nipPart;
                $dirName = preg_replace('/[^A-Za-z0-9 _\-\.]/', '', $combined);
                $dirName = trim($dirName);
                $dirName = str_replace(' ', '_', $dirName);
                if ($dirName === '') $dirName = (string)$id;

                foreach (['front' => $frontPath, 'back' => $backPath] as $side => $filePath) {
                    if (!is_file($filePath)) continue;
                    $localName = "{$dirName}/{$side}.png";
                    $zip->addFile($filePath, $localName);
                    if (method_exists($zip, 'setCompressionName')) {
                        try { $zip->setCompressionName($localName, ZipArchive::CM_STORE); } catch (\Throwable $e) {}
                    }
                    $added++;
                }
            } catch (\Throwable $t) {
                Log::error('nametag.archive_item_error', ['employee_id' => $id, 'err' => $t->getMessage()]);
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($zipPath);
            $archive->status = 'failed';
            $archive->notes = 'no_files';
            $archive->save();
            return;
        }

        $archive->path = 'archives/' . $zipName;
        $archive->status = 'ready';
        $archive->save();
    }
}
