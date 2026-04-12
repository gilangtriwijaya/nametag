<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Models\Employee;
use App\Services\PhotoBgService;
use App\Support\EmployeeBg;

class PhotoBgBatchController extends Controller
{
    public function index(Request $req)
    {
        // Filter opsional by OPD
        $opdId = $req->query('opd_id');

        $employees = Employee::query()
            ->when($opdId, fn ($q) => $q->where('opd_id', $opdId))
            ->latest('id')
            ->limit(50)
            ->get(); // preview 50 terakhir

        return view('employees.photo_bg_batch', [
            'employees' => $employees,
            'opd_id'    => $opdId,
        ]);
    }

    public function run(Request $req)
    {
        $data = $req->validate([
            'opd_id' => 'nullable|integer',
            'limit'  => 'nullable|integer|min:1|max:1000',
            'force'  => 'nullable|boolean', // re-generate meskipun cache ada
        ]);

        $opdId = $data['opd_id'] ?? null;
        $limit = $data['limit'] ?? 200;
        $force = (bool)($data['force'] ?? false);

        $rows = Employee::query()
            ->when($opdId, fn ($q) => $q->where('opd_id', $opdId))
            ->whereNotNull('foto_path')
            ->limit($limit)
            ->get();

        /** @var PhotoBgService $svc */
        $svc = app(PhotoBgService::class);

        $ok = 0;
        $fail = 0;
        $skipped = 0;

        // Siapkan folder kerja
        $cleanBase = public_path('uploads/employees/clean');
        $nameBase  = public_path('uploads/employees/nametag');
        $finalDir  = public_path('uploads/employees');

        foreach ([$cleanBase, $nameBase, $finalDir] as $dir) {
            if (!File::isDirectory($dir)) File::makeDirectory($dir, 0755, true);
        }

        foreach ($rows as $e) {
            if (empty($e->foto_path)) { $fail++; continue; }

            $src = public_path(ltrim($e->foto_path, '/'));
            if (!is_file($src)) { $fail++; continue; }

            $dstTransparent = $cleanBase . '/' . $e->id . '.png';
            $dstComposed    = $nameBase  . '/' . $e->id . '.png';

            // Skip jika tidak force & cache compose sudah ada
            if (!$force && is_file($dstComposed)) {
                // Tetap commit final bila foto_path belum menunjuk ke final standar
                $expectedFinalRel = 'uploads/employees/emp_' . $e->id . '.png';
                if ($e->foto_path !== $expectedFinalRel && is_file($dstComposed)) {
                    $finalPath = $finalDir . '/emp_' . $e->id . '.png';
                    @copy($dstComposed, $finalPath);
                    $old = public_path(ltrim($e->foto_path, '/'));
                    if ($old && is_file($old) && realpath($old) !== realpath($finalPath)) {
                        @unlink($old);
                    }
                    $e->foto_path = $expectedFinalRel;
                    $e->save();
                }
                $skipped++;
                continue;
            }

            // Warna latar menurut jabatan
            $type = EmployeeBg::typeFromEmployee($e);
            $hex  = EmployeeBg::bgHexForType($type);

            // Proses → transparan & compose
            $done = $svc->processAndCompose($src, $dstTransparent, $dstComposed, $hex);
            if (!$done || !is_file($dstComposed)) { $fail++; continue; }

            // Permanenkan: jadikan hasil compose sebagai foto utama
            $finalPath = $finalDir . '/emp_' . $e->id . '.png';
            @copy($dstComposed, $finalPath);

            $old = public_path(ltrim($e->foto_path, '/'));
            if ($old && is_file($old) && realpath($old) !== realpath($finalPath)) {
                @unlink($old);
            }

            $e->foto_path = 'uploads/employees/emp_' . $e->id . '.png';
            $e->save();

            $ok++;
        }

        return back()->with('ok', "Batch selesai. Sukses: {$ok}, Diskip: {$skipped}, Gagal: {$fail}");
    }
}
