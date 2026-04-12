<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\NametagOrchestrator;
use Illuminate\Http\Request;

class NametagController extends Controller
{
    /** Halaman batch nametag lama (optional, kalau masih dipakai) */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $opdId = $request->query('opd_id');

        $employees = Employee::query()
            ->when($opdId, fn ($q) => $q->where('opd_id', $opdId))
            ->where('status_aktif', 'AKTIF')
            ->latest('id')
            ->paginate(24)
            ->withQueryString();

        return view('employees.nametag_batch', compact('employees', 'opdId'));
    }

    /**
     * Generate nametag untuk 1 pegawai (per-pegawai).
     *
     * Flash:
     *  - ok              : pesan sukses/gagal
     *  - nametag_emp     : ID pegawai terkait
     *  - nametag_front   : URL hasil front (jika ada)
     *  - nametag_back    : URL hasil back (jika ada)
     */
    public function store(Request $request, Employee $employee, NametagOrchestrator $orchestrator)
    {
        // PAKAI ability khusus, bukan update()
        $this->authorize('generateNametag', $employee);

        $force  = (bool) $request->boolean('force', true);
        // mark processing
        try { $employee->update(['nametag_status' => 'processing']); } catch (\Throwable $_) {}

        $result = $orchestrator->generateSingle($employee, $force);

        try {
            if (!empty($result['success'])) {
                $employee->update([
                    'nametag_status' => 'ready',
                    'nametag_generated_at' => now(),
                    'nametag_error' => null,
                ]);
            } else {
                $employee->update([
                    'nametag_status' => 'failed',
                    'nametag_error' => $result['reason'] ?? ($result['message'] ?? 'failed'),
                ]);
            }
        } catch (\Throwable $_) {}

        // If the caller expects JSON (AJAX), return structured JSON response
        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            $ok = !empty($result['success']);
            $payload = [
                'ok' => $ok,
                'success' => $ok,
                'message' => $result['message'] ?? null,
                'reason' => $result['reason'] ?? null,
                'front_url' => $result['front_url'] ?? null,
                'back_url' => $result['back_url'] ?? null,
                'employee_id' => $employee->id,
            ];
            return response()->json($payload, $ok ? 200 : 422);
        }

        return back()->with([
            'ok'            => $result['message'],
            'nametag_emp'   => $employee->id,
            'nametag_front' => $result['front_url'],
            'nametag_back'  => $result['back_url'],
        ]);
    }

    /**
     * Batch generate (mode lama, bukan yang baru di NametagBatchController).
     */
    public function run(Request $request, NametagOrchestrator $orchestrator)
    {
        $this->authorize('viewAny', Employee::class);

        $data = $request->validate([
            'opd_id' => 'nullable|integer',
            'limit'  => 'nullable|integer|min:1|max:1000',
        ]);

        $opdId = $data['opd_id'] ?? null;
        $limit = $data['limit']  ?? 200;

        $rows = Employee::query()
            ->when($opdId, fn ($q) => $q->where('opd_id', $opdId))
            ->where('status_aktif', 'AKTIF')
            ->limit($limit)
            ->get();

        $batch = $orchestrator->batchGenerate($rows, [
            'force'  => true,
            'opd_id' => $opdId,
            'limit'  => $limit,
        ]);

        return back()
            ->with('ok', "Nametag: Sukses {$batch['ok']}, Gagal {$batch['fail']}")
            ->with('fail_notes', $batch['notes']);
    }
}
