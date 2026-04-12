<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    public function export(Request $request)
    {
        $secret = env('SSO_TICKET_SECRET');
        if (!$secret) abort(500, 'SSO_TICKET_SECRET not set');

        $given = (string) $request->header('X-SSO-Signature');
        $expected = hash_hmac('sha256', 'master_export', $secret);

        if (!$given || !hash_equals($expected, $given)) {
            abort(401, 'Invalid signature');
        }

        $opds = DB::table('opds')
            ->select('id','nama','slug','is_active')
            ->orderBy('id')
            ->get();

        $units = DB::table('opd_units')
            ->select('id','opd_id','nama','slug','is_active')
            ->orderBy('id')
            ->get();

        return response()->json([
            'opds' => $opds,
            'opd_units' => $units,
        ]);
    }
}
