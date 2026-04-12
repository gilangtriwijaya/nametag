<?php

namespace App\Http\Controllers;

use App\Models\OpdUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OpdUnitController extends Controller
{
    public function index(Request $r)
    {
        $this->authorize('viewAny', OpdUnit::class);
        $opdId = (int) $r->query('opd_id', 0);

        return redirect()
            ->route('opd.index', $opdId ? ['focus_opd' => $opdId] : [])
            ->with('info', 'Manajemen Unit dilakukan di halaman OPD.');
    }

    public function create()
    {
        $this->authorize('create', OpdUnit::class);

        return redirect()
            ->route('opd.index')
            ->with('info', 'Gunakan form tambah unit cepat pada halaman OPD.');
    }

    public function store(Request $r)
    {
        $this->authorize('create', OpdUnit::class);

        $me              = Auth::user();
        $opdRequired     = $me->hasAnyRole(['superadmin','org_admin','org-admin','org admin']);
        $opdIdInput      = (int) $r->input('opd_id');
        $effectiveOpdId  = $opdRequired ? $opdIdInput : (int) $me->opd_id;

        $data = $r->validate([
            'opd_id'    => [Rule::requiredIf($opdRequired), 'nullable', 'exists:opds,id'],
            'kode'      => ['nullable','max:64',
                Rule::unique('opd_units','code')
                    ->where(fn($q) => $q->where('opd_id', $effectiveOpdId)->whereNull('deleted_at'))
            ],
            'nama'      => ['required','max:191',
                Rule::unique('opd_units','nama')
                    ->where(fn($q) => $q->where('opd_id', $effectiveOpdId)->whereNull('deleted_at'))
            ],
            'alamat'    => ['nullable','max:255'],
            'kecamatan' => ['nullable','max:100'],
            'is_active' => ['nullable','boolean'],
        ]);

        $payload = [
            'opd_id'    => $effectiveOpdId,
            'code'      => $data['kode'] ?? null,
            'nama'      => trim($data['nama']),
            'alamat'    => $data['alamat'] ?? null,
            'kecamatan' => $data['kecamatan'] ?? null,
            'status'    => (array_key_exists('is_active', $data) && !$data['is_active']) ? 'NONAKTIF' : 'AKTIF',
        ];

        $unit = OpdUnit::create($payload);

        // Log: created (best-effort)
        try {
            activity('opd_unit')
                ->causedBy($me)
                ->performedOn($unit)
                ->event('created')
                ->withProperties($payload)
                ->log('Membuat unit OPD');
        } catch (\Throwable $e) {
            logger()->warning('activitylog opd_unit.create failed: '.$e->getMessage());
        }

        return redirect()
            ->route('opd.index', ['focus_opd' => $payload['opd_id']])
            ->with('ok', 'Unit berhasil dibuat.');
    }

    public function edit(OpdUnit $opdUnit)
    {
        $this->authorize('update', $opdUnit);

        return redirect()
            ->route('opd.index', ['focus_opd' => $opdUnit->opd_id])
            ->with('info', 'Ubah unit dari halaman OPD.');
    }

    public function update(Request $r, OpdUnit $opdUnit)
    {
        $this->authorize('update', $opdUnit);

        $me              = Auth::user();
        $opdEditable     = $me->hasAnyRole(['superadmin','org_admin','org-admin','org admin']);
        $effectiveOpdId  = $opdEditable
            ? (int) ($r->input('opd_id') ?: $opdUnit->opd_id)
            : (int) $opdUnit->opd_id;

        // Validasi “sometimes” (hanya field yang dikirim)
        $rules = [
            'opd_id'    => [Rule::requiredIf($opdEditable), 'nullable', 'exists:opds,id'],
            'nama'      => ['sometimes','filled','max:191'],
            'kode'      => ['sometimes','nullable','max:64'],
            'alamat'    => ['sometimes','nullable','max:255'],
            'kecamatan' => ['sometimes','nullable','max:100'],
            'is_active' => ['sometimes','boolean'],
        ];
        if ($r->has('nama')) {
            $rules['nama'][] = Rule::unique('opd_units','nama')
                ->ignore($opdUnit->id)
                ->where(fn($q) => $q->where('opd_id', $effectiveOpdId)->whereNull('deleted_at'));
        }
        if ($r->has('kode')) {
            $rules['kode'][] = Rule::unique('opd_units','code')
                ->ignore($opdUnit->id)
                ->where(fn($q) => $q->where('opd_id', $effectiveOpdId)->whereNull('deleted_at'));
        }
        $data = $r->validate($rules);

        // Susun payload dari input yang ada
        $payload = ['opd_id' => $effectiveOpdId];
        if ($r->has('kode'))      { $payload['code']      = $data['kode']; }
        if ($r->has('nama'))      { $payload['nama']      = trim($data['nama']); }
        if ($r->has('alamat'))    { $payload['alamat']    = $data['alamat']; }
        if ($r->has('kecamatan')) { $payload['kecamatan'] = $data['kecamatan']; }
        if ($r->has('is_active')) { $payload['status']    = $data['is_active'] ? 'AKTIF' : 'NONAKTIF'; }

        DB::transaction(function () use ($payload, $opdUnit, $me) {
            // hitung perubahan yang nyata
            $before = $opdUnit->only(['opd_id','code','nama','alamat','kecamatan','status']);
            $opdUnit->fill($payload);
            $dirty = $opdUnit->getDirty();   // hanya field yang berubah
            $opdUnit->save();

            // Log: updated
            try {
                activity('opd_unit')
                    ->causedBy($me)
                    ->performedOn($opdUnit)
                    ->event('updated')
                    ->withProperties([
                        'changes' => collect($dirty)->map(fn($v,$k)=>['from'=>$before[$k] ?? null,'to'=>$v]),
                    ])
                    ->log('Memperbarui unit OPD');
            } catch (\Throwable $e) {
                logger()->warning('activitylog opd_unit.update failed: '.$e->getMessage());
            }

            // Jika dinonaktifkan → kick sesi semua user unit tsb
            if (($payload['status'] ?? null) === 'NONAKTIF') {
                $userIds = User::where('opd_unit_id', $opdUnit->id)->pluck('id');
                if ($userIds->isNotEmpty()) {
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                }
            }
        });

        if ($r->expectsJson() || $r->wantsJson()) {
            return response()->json([
                'ok'     => true,
                'unit'   => $opdUnit->fresh(['opd']),
                'status' => $opdUnit->status,
            ]);
        }

        return redirect()
            ->route('opd.index', ['focus_opd' => $effectiveOpdId])
            ->with('ok', 'Unit diperbarui.');
    }

    /** Toggle aktif/nonaktif. */
    public function toggleActive(Request $r, OpdUnit $opdUnit)
    {
        $this->authorize('update', $opdUnit);

        $me  = Auth::user();
        $new = $opdUnit->status === 'AKTIF' ? 'NONAKTIF' : 'AKTIF';

        DB::transaction(function () use ($new, $opdUnit, $me) {
            $opdUnit->update(['status' => $new]);

            // Log: activated/deactivated
            try {
                activity('opd_unit')
                    ->causedBy($me)
                    ->performedOn($opdUnit)
                    ->event($new === 'AKTIF' ? 'activated' : 'deactivated')
                    ->withProperties(['status' => $new])
                    ->log('Toggle status unit OPD');
            } catch (\Throwable $e) {
                logger()->warning('activitylog opd_unit.toggle failed: '.$e->getMessage());
            }

            if ($new === 'NONAKTIF') {
                $userIds = User::where('opd_unit_id', $opdUnit->id)->pluck('id');
                if ($userIds->isNotEmpty()) {
                    DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                }
            }
        });

        if ($r->expectsJson() || $r->wantsJson()) {
            return response()->json(['ok' => true, 'status' => $new]);
        }

        return back()->with('ok', "Unit di-set {$new}.");
    }

    public function destroy(OpdUnit $opdUnit)
    {
        $this->authorize('delete', $opdUnit);

        $me   = Auth::user();
        $opdId= (int) $opdUnit->opd_id;

        DB::transaction(function () use ($opdUnit, $me) {
            // paksa nonaktif + tendang sesi
            $opdUnit->forceFill(['status' => 'NONAKTIF'])->save();

            $userIds = User::where('opd_unit_id', $opdUnit->id)->pluck('id');
            if ($userIds->isNotEmpty()) {
                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            }

            $opdUnit->delete(); // soft delete

            // Log: deleted
            try {
                activity('opd_unit')
                    ->causedBy($me)
                    ->performedOn($opdUnit)
                    ->event('deleted')
                    ->log('Menghapus (soft) unit OPD');
            } catch (\Throwable $e) {
                logger()->warning('activitylog opd_unit.delete failed: '.$e->getMessage());
            }
        });

        return redirect()
            ->route('opd.index', ['focus_opd' => $opdId])
            ->with('ok', 'Unit dinonaktifkan & dihapus (soft delete).');
    }
}
