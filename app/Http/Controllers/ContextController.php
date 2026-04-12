<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ContextController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Set/clear konteks OPD untuk superadmin / org_admin.
     * - opd_id = null  -> clear konteks (lihat semua OPD)
     * - opd_id = valid -> set konteks ke OPD tsb & reset unit
     */
    public function setOpd(Request $r)
    {
        $r->validate([
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
        ]);

        $u = $r->user();
        if (!($u->hasRole('superadmin') || $u->hasAnyRole(['org_admin','org-admin','org admin']))) {
            abort(403);
        }

        $opdId = $r->input('opd_id');
        $opdId = $opdId !== null && $opdId !== '' ? (int) $opdId : null;

        // Persist preferensi jika tabelnya ada; kalau tidak ada, abaikan (tidak error).
        if (Schema::hasTable('user_contexts')) {
            DB::table('user_contexts')->updateOrInsert(
                ['user_id' => $u->id],
                ['current_opd_id' => $opdId, 'current_opd_unit_id' => null]
            );
        }

        // Refresh session (reset unit bila ganti OPD)
        session([
            'current_opd_id'      => $opdId,
            'current_opd_unit_id' => null,
            'opd_locked'          => false,
            'opd_unit_locked'     => false,
        ]);

        return back()->with('ok', $opdId ? 'Konteks OPD diganti.' : 'Konteks OPD dihapus. Menampilkan semua OPD.');
    }

    /**
     * Set/clear konteks Unit untuk superadmin / org_admin.
     * - opd_unit_id = null  -> clear konteks unit (tetap di OPD yg terpilih bila ada)
     * - opd_unit_id = valid -> set konteks unit (harus milik OPD konteks jika OPD dipilih)
     */
    public function setUnit(Request $r)
    {
        $r->validate([
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
        ]);

        $u = $r->user();
        if (!($u->hasRole('superadmin') || $u->hasAnyRole(['org_admin','org-admin','org admin']))) {
            abort(403);
        }

        $unitId = $r->input('opd_unit_id');
        $unitId = $unitId !== null && $unitId !== '' ? (int) $unitId : null;

        // Jika ada konteks OPD aktif, pastikan unit yang dipilih memang milik OPD tsb.
        $ctxOpdId = session('current_opd_id');
        if ($unitId && $ctxOpdId) {
            $ownerOpdId = DB::table('opd_units')->where('id', $unitId)->value('opd_id');
            if ((int) $ownerOpdId !== (int) $ctxOpdId) {
                return back()->withErrors([
                    'unit' => 'Unit yang dipilih tidak termasuk ke dalam OPD yang sedang aktif.'
                ]);
            }
        }

        // Persist preferensi jika tabelnya ada
        if (Schema::hasTable('user_contexts')) {
            DB::table('user_contexts')->updateOrInsert(
                ['user_id' => $u->id],
                [
                    'current_opd_id'      => $ctxOpdId ?: null,
                    'current_opd_unit_id' => $unitId,
                ]
            );
        }

        // Refresh session
        session([
            'current_opd_unit_id' => $unitId,
            'opd_unit_locked'     => false,
        ]);

        return back()->with('ok', $unitId ? 'Konteks Unit diganti.' : 'Konteks Unit dihapus.');
    }
}
