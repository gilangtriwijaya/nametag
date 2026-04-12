<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class ScanController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        // Helper: Apply simple title case with quote preservation
        $applySimpleTitleCase = function(?string $text): string {
            $s = (string)($text ?? '');
            if ($s === '') return $s;
            
            // Extract and preserve content inside double quotes (WITHOUT the quotes)
            $preservedMap = [];
            $markerStart = chr(0);
            $markerEnd = chr(1);
            
            $s = preg_replace_callback('/"([^"]*)"/', function($matches) use (&$preservedMap, $markerStart, $markerEnd) {
                $idx = count($preservedMap);
                $key = $markerStart . 'Q' . $idx . $markerEnd;
                $preservedMap[$key] = $matches[1];
                return $key;
            }, $s);
            
            $gelarDepan = '';
            $namePart = $s;
            
            if (preg_match('/^((?:[A-Za-z]{1,3}\.\s+|[A-Z][a-z]+\.\s+)+)(.*)$/u', $namePart, $m)) {
                $gelarDepan = $m[1];
                $namePart = $m[2];
            }
            
            $namePart = mb_convert_case($namePart, MB_CASE_TITLE, 'UTF-8');
            
            $result = $gelarDepan . $namePart;
            
            foreach ($preservedMap as $key => $value) {
                $result = str_replace($key, $value, $result);
            }
            
            return $result;
        };
        
        // Ambil data token + pegawai (tanpa merakit nama lengkap di SQL)
        $row = DB::table('employee_qr_tokens as t')
            ->join('employees as e', 'e.id', '=', 't.employee_id')
            ->leftJoin('opds as o', 'o.id', '=', 'e.opd_id')
            ->where('t.token', $token)
            ->whereNull('e.deleted_at') // hormati soft delete pegawai
            ->selectRaw('
                t.id         AS token_id,
                t.token      AS token_value,
                t.status     AS token_status,
                t.expires_at AS token_expires_at,
                t.created_at AS token_created_at,

                e.id              AS employee_id,
                e.opd_id          AS opd_id,
                e.nip             AS employee_nip,
                e.nama            AS employee_name,
                e.jabatan         AS employee_position,
                e.jabatan_type    AS position_type,
                e.nama_unit_opd   AS nama_unit_opd,
                e.status_aktif    AS employee_active_status,
                e.foto_path       AS foto_path,

                o.nama            AS opd_name
            ')
            ->first();

        $result  = 'not_found';
        $payload = null;

        if ($row) {
            // Evaluasi status token & pegawai
            $nowExpired = false;
            if (!empty($row->token_expires_at)) {
                $ts = @strtotime($row->token_expires_at);
                if ($ts !== false) {
                    $nowExpired = $ts <= time();
                }
            }

            if ($row->token_status !== 'active') {
                $result = 'revoked';
            } elseif ($nowExpired) {
                $result = 'expired';
            } elseif (($row->employee_active_status ?? '') !== 'AKTIF') {
                $result = 'inactive_employee';
            } else {
                $result  = 'ok';
                $payload = $row;

                // Apply case transformation ke jabatan (remove quotes, preserve case)
                if (!empty($payload->employee_position)) {
                    $payload->employee_position = $applySimpleTitleCase($payload->employee_position);
                }

                // Rakit nama final dengan accessor Employee::nama_lengkap
                try {
                    $emp = Employee::find($row->employee_id);
                    if ($emp) {
                        // [GELAR_DEPAN.] Nama[, GELAR_BELAKANG]
                        $payload->employee_name = $emp->nama_lengkap;
                    }
                } catch (\Throwable $e) {
                    // fallback: biarkan nama mentah jika terjadi error
                }
            }

            // Log scan (selalu dicatat ketika token ditemukan)
            try {
                DB::table('employee_scan_logs')->insert([
                    'token_id'   => $row->token_id,
                    'ip_address' => substr((string) $request->ip(), 0, 45),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    'result'     => $result,
                    // tabel ini pakai kolom scanned_at (BUKAN created_at/updated_at)
                    'scanned_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Jangan mengganggu user hanya karena log gagal
            }
        }

        // Render tampilan publik
        $response = response()->view('public.scan.result', [
            'found'      => (bool) $row,
            'result'     => $result,
            'data'       => $payload, // saat ok: employee_name sudah nama_lengkap
            'scanned_at' => now(),
            'token'      => $token,
        ]);

        return $response
            ->header('Cache-Control', 'no-store, max-age=0')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
