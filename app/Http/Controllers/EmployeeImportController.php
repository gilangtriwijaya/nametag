<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\EmployeeImportService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response;
use App\Jobs\ProcessEmployeeImportJob;

class EmployeeImportController extends Controller
{
    protected $service;

    public function __construct(EmployeeImportService $service)
    {
        $this->service = $service;
    }

    public function show()
    {
        return view('employees.import');
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:5120'
        ]);

        $uploaded = $request->file('file');
        $name = 'employee_import_' . time() . '_' . Str::random(6) . '.' . $uploaded->getClientOriginalExtension();

        // create a preview id (keep .json suffix for compatibility)
        $previewId = 'employee_import_preview_' . time() . '_' . Str::random(6) . '.json';
        $folder = 'tmp/import_previews/' . pathinfo($previewId, PATHINFO_FILENAME);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (!$disk->exists($folder)) {
            $disk->makeDirectory($folder);
        }
        $path = $uploaded->storeAs($folder, $name, 'local');

        $fullPath = $disk->path($path);
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            $debug = [
                'path' => $fullPath,
                'exists' => file_exists($fullPath),
                'is_readable' => is_readable($fullPath),
                'files_in_folder' => $disk->files($folder),
            ];
            // write debug file next to upload for inspection
            $disk->put($folder . '/debug.txt', json_encode($debug, JSON_PRETTY_PRINT));
            return back()->with('error', 'Gagal memproses file: upload tidak ditemukan atau tidak dapat dibaca. Debug ditulis.');
        }

        try {
            $preview = $this->service->parseUploadedFile($fullPath);
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses file: ' . $e->getMessage());
        }

        // include original upload path in preview metadata
        $previewMeta = [
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
            'upload_path' => $path,
        ];

        // persist preview JSON to temp storage and pass id
        if (!\Illuminate\Support\Facades\Storage::exists('tmp/import_previews')) {
            \Illuminate\Support\Facades\Storage::makeDirectory('tmp/import_previews');
        }
        \Illuminate\Support\Facades\Storage::put('tmp/import_previews/' . $previewId, json_encode($previewMeta));

        return view('employees.import_preview', [
            'preview' => $previewMeta,
            'preview_id' => $previewId,
        ]);
    }

    public function uploadAjax(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:51200'
        ]);

        $uploaded = $request->file('file');
        $name = 'employee_import_' . time() . '_' . Str::random(6) . '.' . $uploaded->getClientOriginalExtension();

        // create preview id and folder to keep uploaded file together with preview
        $previewId = 'employee_import_preview_' . time() . '_' . Str::random(6) . '.json';
        $folder = 'tmp/import_previews/' . pathinfo($previewId, PATHINFO_FILENAME);
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        if (!$disk->exists($folder)) {
            $disk->makeDirectory($folder);
        }
        $path = $uploaded->storeAs($folder, $name, 'local');
        $fullPath = $disk->path($path);
        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            $debug = [
                'path' => $fullPath,
                'exists' => file_exists($fullPath),
                'is_readable' => is_readable($fullPath),
                'files_in_folder' => $disk->files($folder),
            ];
            $disk->put($folder . '/debug.txt', json_encode($debug, JSON_PRETTY_PRINT));
            return response()->json(['success' => false, 'message' => 'Upload tidak ditemukan atau tidak dapat dibaca. Debug ditulis.'], 422);
        }

        try {
            $preview = $this->service->parseUploadedFile($fullPath);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }


        $previewMeta = [
            'rows' => $preview['rows'],
            'summary' => $preview['summary'],
            'upload_path' => $path,
        ];

        \Illuminate\Support\Facades\Storage::put('tmp/import_previews/' . $previewId, json_encode($previewMeta));

        $previewUrl = route('employees.import.preview.view', ['id' => $previewId]);

        return response()->json(['success' => true, 'preview_id' => $previewId, 'preview_url' => $previewUrl]);
    }

    public function previewFromId($id)
    {
        if (!Storage::exists('tmp/import_previews/' . $id)) {
            return redirect()->route('employees.import.show')->with('error', 'Preview tidak ditemukan');
        }
        $json = Storage::get('tmp/import_previews/' . $id);
        $preview = json_decode($json, true);
        return view('employees.import_preview', ['preview' => $preview, 'preview_id' => $id]);
    }

    public function rerunPreview(Request $request, $id)
    {
        if (!Storage::exists('tmp/import_previews/' . $id)) {
            return back()->with('error', 'Preview tidak ditemukan');
        }
        $json = Storage::get('tmp/import_previews/' . $id);
        $preview = json_decode($json, true);
        if (empty($preview['upload_path']) || !Storage::exists($preview['upload_path'])) {
            return back()->with('error', 'File upload tidak ditemukan, silakan upload ulang');
        }

        try {
            $newPreview = $this->service->parseUploadedFile(storage_path('app/' . $preview['upload_path']));
        } catch (\Exception $e) {
            return back()->with('error', 'Gagal memproses file: ' . $e->getMessage());
        }

        $previewMeta = [
            'rows' => $newPreview['rows'],
            'summary' => $newPreview['summary'],
            'upload_path' => $preview['upload_path'],
        ];
        Storage::put('tmp/import_previews/' . $id, json_encode($previewMeta));

        return redirect()->route('employees.import.preview.view', ['id' => $id])->with('success', 'Preview diperbarui');
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'preview_id' => 'required|string'
        ]);

        $previewId = $request->input('preview_id');
        if (!Storage::exists('tmp/import_previews/' . $previewId)) {
            return back()->with('error', 'Preview tidak ditemukan');
        }

        // ensure uploaded file for this preview still exists
        $json = Storage::get('tmp/import_previews/' . $previewId);
        $preview = json_decode($json, true);
        if (empty($preview['upload_path']) || !Storage::exists($preview['upload_path'])) {
            return back()->with('error', 'File upload untuk preview ini tidak ditemukan. Silakan upload ulang file dan buat preview kembali.');
        }

        // dispatch job to process preview
        $job = new ProcessEmployeeImportJob($previewId);
        $dispatched = dispatch($job);

        // return a result view that will poll job status
        return view('employees.import_result', ['job_id' => $job->jobId, 'result' => null, 'errors_file' => null]);
    }

    public function jobStatus($jobId)
    {
        $path = 'tmp/import_jobs/' . $jobId . '.json';
        if (!Storage::exists($path)) {
            return response()->json(['status' => 'unknown']);
        }
        $json = Storage::get($path);
        $data = json_decode($json, true);
        return response()->json($data);
    }

    public function downloadErrors($file)
    {
        $path = storage_path('app/tmp/import_errors/' . $file);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $file)->deleteFileAfterSend(true);
    }

    public function downloadTemplate()
    {
        $relative = 'templates/employee_import_template.csv';
        // try Storage facade first (disk: local)
        try {
            if (\Illuminate\Support\Facades\Storage::exists($relative)) {
                return \Illuminate\Support\Facades\Storage::download($relative, 'employee_import_template.csv');
            }
        } catch (\Exception $e) {
            // ignore and fallback to direct file path
        }

        $path = storage_path('app/' . $relative);
        if (file_exists($path)) {
            return response()->download($path, 'employee_import_template.csv');
        }

        abort(404);
    }
}
