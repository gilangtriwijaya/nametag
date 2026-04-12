<?php

namespace App\Http\Controllers;

use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitKerjaController extends Controller
{
    public function index(Request $r)
    {
        // Authorization: mirror OpdUnitPolicy.viewAny logic to avoid unexpected Gate denial
        $me = Auth::user();
        if (! ($me->hasRole('superadmin') || $me->hasAnyRole(['org_admin','org-admin','org admin']) || $me->hasAnyRole(['Admin OPD','admin opd','admin-opd','admin_opd']) || $me->hasAnyRole(['Verifikator OPD','verifikator opd','verifikator-opd','verifikator_opd']) || $me->hasAnyRole(['Admin Unit','admin unit','admin-unit','admin_unit']) || $me->hasAnyRole(['Verifikator Unit','verifikator unit','verifikator-unit','verifikator_unit']))) {
            abort(403);
        }

        $q = trim((string) $r->query('q', ''));
        $units = UnitKerja::query()
            ->when($q !== '', fn($qq) => $qq->where('nama', 'like', "%{$q}%"))
            ->orderBy('nama')
            ->paginate(50);

        return view('unit_kerja.index', [
            'units' => $units,
            'q' => $q,
        ]);
    }

    public function create()
    {
        $me = Auth::user();
        if (! ($me->hasRole('superadmin') || $me->hasAnyRole(['org_admin','org-admin','org admin']) || $me->hasAnyRole(['Admin OPD','admin opd','admin-opd','admin_opd']))) {
            abort(403);
        }
        return view('unit_kerja.create');
    }

    public function store(Request $r)
    {
        $me = Auth::user();
        if (! ($me->hasRole('superadmin') || $me->hasAnyRole(['org_admin','org-admin','org admin']) || $me->hasAnyRole(['Admin OPD','admin opd','admin-opd','admin_opd']))) {
            abort(403);
        }

        $data = $r->validate([
            'opd_id' => ['nullable','integer','exists:opds,id'],
            'code'   => ['nullable','string','max:50'],
            'nama'   => ['required','string','max:150', Rule::unique('unit_kerja','nama')->where(fn($q)=>$q->whereNull('deleted_at'))],
            'alamat' => ['nullable','string','max:255'],
            'kecamatan' => ['nullable','string','max:255'],
            'is_active' => ['nullable','boolean'],
        ]);

        $payload = [
            'opd_id' => $data['opd_id'] ?? null,
            'code'   => $data['code'] ?? null,
            'nama'   => trim($data['nama']),
            'alamat' => $data['alamat'] ?? null,
            'kecamatan' => $data['kecamatan'] ?? null,
            'status' => (array_key_exists('is_active', $data) && ! $data['is_active']) ? 'NONAKTIF' : 'AKTIF',
        ];

        $unit = UnitKerja::create($payload);

        try {
            activity('unit_kerja')->causedBy(Auth::user())->performedOn($unit)->event('created')->log('Membuat unit kerja');
        } catch (\Throwable $e) {
            logger()->warning('activitylog unit_kerja.create failed: '.$e->getMessage());
        }

        return redirect()->route('unit-kerja.index')->with('ok', 'Unit Kerja berhasil dibuat.');
    }

    public function edit(UnitKerja $unitKerja)
    {
        $me = Auth::user();
        // Admin OPD may update units within their OPD; super/org can too
        if (! ($me->hasRole('superadmin') || $me->hasAnyRole(['org_admin','org-admin','org admin']) || ($me->hasAnyRole(['Admin OPD','admin opd','admin-opd','admin_opd']) && (int)$me->opd_id === (int)$unitKerja->opd_id))) {
            abort(403);
        }
        return view('unit_kerja.edit', ['unit' => $unitKerja]);
    }

    public function update(Request $r, UnitKerja $unitKerja)
    {
        $this->authorize('update', $unitKerja);

        $data = $r->validate([
            'opd_id' => ['nullable','integer','exists:opds,id'],
            'code'   => ['sometimes','nullable','string','max:50'],
            'nama'   => ['sometimes','filled','string','max:150', Rule::unique('unit_kerja','nama')->ignore($unitKerja->id)->where(fn($q)=>$q->whereNull('deleted_at'))],
            'alamat' => ['sometimes','nullable','string','max:255'],
            'kecamatan' => ['sometimes','nullable','string','max:255'],
            'is_active' => ['sometimes','boolean'],
        ]);

        $payload = [];
        if ($r->has('opd_id')) $payload['opd_id'] = $data['opd_id'];
        if ($r->has('code')) $payload['code'] = $data['code'];
        if ($r->has('nama')) $payload['nama'] = trim($data['nama']);
        if ($r->has('alamat')) $payload['alamat'] = $data['alamat'];
        if ($r->has('kecamatan')) $payload['kecamatan'] = $data['kecamatan'];
        if ($r->has('is_active')) $payload['status'] = $data['is_active'] ? 'AKTIF' : 'NONAKTIF';

        DB::transaction(function () use ($payload, $unitKerja) {
            $before = $unitKerja->only(['opd_id','code','nama','alamat','kecamatan','status']);
            $unitKerja->fill($payload);
            $dirty = $unitKerja->getDirty();
            $unitKerja->save();

            try {
                activity('unit_kerja')->performedOn($unitKerja)->event('updated')->log('Memperbarui unit kerja');
            } catch (\Throwable $e) {
                logger()->warning('activitylog unit_kerja.update failed: '.$e->getMessage());
            }
        });

        return redirect()->route('unit-kerja.index')->with('ok', 'Unit Kerja diperbarui.');
    }

    public function destroy(UnitKerja $unitKerja)
    {
        $me = Auth::user();
        if (! ($me->hasRole('superadmin') || $me->hasAnyRole(['org_admin','org-admin','org admin']) || ($me->hasAnyRole(['Admin OPD','admin opd','admin-opd','admin_opd']) && (int)$me->opd_id === (int)$unitKerja->opd_id))) {
            abort(403);
        }

        $me = Auth::user();
        DB::transaction(function () use ($unitKerja, $me) {
            $unitKerja->forceFill(['status'=>'NONAKTIF'])->save();
            DB::table('employees')->where('unit_kerja_id', $unitKerja->id)->update(['unit_kerja_id' => null, 'unit_kerja' => null]);
            $unitKerja->delete();

            try { activity('unit_kerja')->causedBy($me)->performedOn($unitKerja)->event('deleted')->log('Menghapus unit kerja'); } catch (\Throwable $e) {}
        });

        return redirect()->route('unit-kerja.index')->with('ok', 'Unit Kerja dihapus (soft).');
    }

    public function toggleActive(Request $r, UnitKerja $unitKerja)
    {
        $me = Auth::user();
        if (! ($me->hasRole('superadmin') || $me->hasAnyRole(['org_admin','org-admin','org admin']) || ($me->hasAnyRole(['Admin OPD','admin opd','admin-opd','admin_opd']) && (int)$me->opd_id === (int)$unitKerja->opd_id))) {
            abort(403);
        }
        $me = Auth::user();
        $new = $unitKerja->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';
        $unitKerja->update(['status' => $new]);
        try { activity('unit_kerja')->causedBy($me)->performedOn($unitKerja)->event($new==='AKTIF'?'activated':'deactivated')->log('Toggle status unit kerja'); } catch (\Throwable $e) {}
        return back()->with('ok', "Unit di-set {$new}.");
    }
}
