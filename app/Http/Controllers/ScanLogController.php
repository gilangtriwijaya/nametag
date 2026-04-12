<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScanLogController extends Controller
{
    public function index(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $result = trim((string) $request->query('result', ''));
        $from   = trim((string) $request->query('from', ''));
        $to     = trim((string) $request->query('to', ''));

        $query = DB::table('employee_scan_logs as s')
            ->leftJoin('employee_qr_tokens as t', 's.token_id', '=', 't.id')
            ->leftJoin('employees as e', 't.employee_id', '=', 'e.id')
            ->leftJoin('opds as o', 'e.opd_id', '=', 'o.id')
            ->selectRaw('
                s.id,
                s.token_id,
                s.ip_address,
                s.user_agent,
                s.scanned_at,
                s.result,
                t.token,
                t.employee_id,
                e.nip,
                e.nama,
                e.status_aktif,
                e.opd_unit_id,
                o.nama as opd_name
            ');

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('e.nama', 'like', $like)
                    ->orWhere('e.nip', 'like', $like)
                    ->orWhere('t.token', 'like', $like)
                    ->orWhere('s.ip_address', 'like', $like);
            });
        }

        if ($result !== '') {
            $query->where('s.result', $result);
        }

        if ($from !== '') {
            $query->whereDate('s.scanned_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('s.scanned_at', '<=', $to);
        }

        $query->orderByDesc('s.id');

        $logs = $query->paginate(30)->appends($request->query());

        return view('scan_logs.index', [
            'logs'   => $logs,
            'q'      => $q,
            'result' => $result,
            'from'   => $from,
            'to'     => $to,
        ]);
    }

    public function show(int $id)
    {
        $log = DB::table('employee_scan_logs as s')
            ->leftJoin('employee_qr_tokens as t', 's.token_id', '=', 't.id')
            ->leftJoin('employees as e', 't.employee_id', '=', 'e.id')
            ->leftJoin('opds as o', 'e.opd_id', '=', 'o.id')
            ->where('s.id', $id)
            ->selectRaw('
                s.id,
                s.token_id,
                s.ip_address,
                s.user_agent,
                s.scanned_at,
                s.result,

                t.token,
                t.status      as token_status,
                t.created_at  as token_created_at,
                t.expires_at  as token_expires_at,
                t.employee_id as token_employee_id,

                e.id           as employee_id,
                e.nip,
                e.nama,
                e.status_aktif,
                e.opd_id,
                e.opd_unit_id,

                o.nama         as opd_name
            ')
            ->first();

        abort_if(!$log, 404);

        return view('scan_logs.show', [
            'log' => $log,
        ]);
    }
}
