<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /* ========================== Helpers akses & scope ========================== */

    private function isSuper(): bool
    {
        $u = auth()->user();
        return $u && $u->hasRole('superadmin');
    }

    /**
     * Scope aktif:
     *  - super  : superadmin
     *  - opd    : admin/verifikator OPD (punya opd_id, opd_unit_id null)
     *  - unit   : admin/verifikator Unit (punya opd_id + opd_unit_id)
     * @return array{0:string,1:int|null,2:int|null}
     */
    private function myScope(): array
    {
        if ($this->isSuper()) return ['super', null, null];

        /** @var User $me */
        $me = auth()->user();
        if ($me?->opd_unit_id) {
            return ['unit', (int) $me->opd_id, (int) $me->opd_unit_id];
        }
        return ['opd', (int) $me->opd_id, null];
    }

    /** Larang menyentuh akun di luar scope. */
    private function abortIfCannotTouch(User $target): void
    {
        if ($this->isSuper()) return;

        [$scope, $opdId, $unitId] = $this->myScope();

        // Tidak boleh sentuh superadmin
        if ($target->hasRole('superadmin')) {
            abort(403, 'Tidak boleh mengelola superadmin.');
        }

        if ($scope === 'unit') {
            if ((int) $target->opd_unit_id !== (int) $unitId) {
                abort(403, 'Hanya boleh mengelola user di unit Anda.');
            }
        } else { // scope OPD
            if ((int) $target->opd_id !== (int) $opdId) {
                abort(403, 'Hanya boleh mengelola user di OPD Anda.');
            }
        }
    }

    /** Paksa nilai opd_id / opd_unit_id sesuai scope non-super. */
    private function enforceOrgForNonSuper(array &$data): void
    {
        if ($this->isSuper()) return;

        [$scope, $opdId, $unitId] = $this->myScope();
        $data['opd_id'] = $opdId;
        $data['opd_unit_id'] = ($scope === 'unit') ? $unitId : null;
    }

    /* =========================== Index & Form ========================= */

    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));
        [$scope, $opdId, $unitId] = $this->myScope();

        $users = User::query()
            ->with(['opd','opdUnit','roles'])
            ->when(!$this->isSuper(), function ($qr) use ($scope, $opdId, $unitId) {
                if ($scope === 'unit') {
                    $qr->where('opd_unit_id', $unitId);
                } else {
                    $qr->where('opd_id', $opdId);
                }
            })
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('email','like',"%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $opds = $this->isSuper()
            ? Opd::orderBy('nama')->get(['id','nama'])
            : Opd::where('id', $opdId)->get(['id','nama']);

        return view('users.index', compact('users','q','opds'));
    }

    public function create()
    {
        [$scope, $opdId, $unitId] = $this->myScope();

        $opds = $this->isSuper()
            ? Opd::orderBy('nama')->get(['id','nama'])
            : Opd::where('id', $opdId)->get(['id','nama']);

        // Siapkan peta unit per OPD (agar dropdown Unit OPD terisi tanpa AJAX)
        $units = OpdUnit::query()
            ->when(!$this->isSuper(), fn($q) => $q->where('opd_id', $opdId))
            ->whereIn('opd_id', $opds->pluck('id'))
            ->orderBy('nama')
            ->get(['id','opd_id','nama','status']);

        $unitMap = $units->groupBy('opd_id')->map->values();

        $roles = Role::query()
            ->when(!$this->isSuper(), fn($q)=>$q->where('opd_id', $opdId)->orWhereNull('opd_id'))
            ->orderBy('opd_id')->orderBy('name')->get(['id','name','opd_id']);

        return view('users.create', [
            'opds'    => $opds,
            'roles'   => $roles,
            'user'    => new User(),
            'unitMap' => $unitMap,
        ]);
    }

    public function edit(User $user)
    {
        $this->abortIfCannotTouch($user);

        [$scope, $opdId] = $this->myScope();

        $opds = $this->isSuper()
            ? Opd::orderBy('nama')->get(['id','nama'])
            : Opd::where('id', $opdId)->get(['id','nama']);

        // Peta unit untuk semua OPD yang muncul di dropdown
        $units = OpdUnit::query()
            ->when(!$this->isSuper(), fn($q) => $q->where('opd_id', $opdId))
            ->whereIn('opd_id', $opds->pluck('id'))
            ->orderBy('nama')
            ->get(['id','opd_id','nama','status']);

        $unitMap = $units->groupBy('opd_id')->map->values();

        $roles = Role::query()
            ->when(!$this->isSuper(), fn($q)=>$q->where('opd_id', $opdId)->orWhereNull('opd_id'))
            ->orderBy('opd_id')->orderBy('name')->get(['id','name','opd_id']);

        $currentRoleIds = $user->roles()->pluck('id')->all();

        return view('users.edit', compact('user','opds','roles','currentRoleIds','unitMap'));
    }

    /* ================================ CRUD ================================ */

    public function store(UserStoreRequest $request)
    {
        $data = $request->validated();

        // Non-super → paksa organisasi sesuai scope
        $this->enforceOrgForNonSuper($data);

        // Validasi: bila unit diisi, harus belong to OPD yang dipilih
        $request->validate([
            'opd_unit_id' => [
                'nullable','integer',
                Rule::exists('opd_units','id')
                    ->when(($data['opd_id'] ?? null), fn($q) => $q->where('opd_id', (int)$data['opd_id']))
            ],
        ]);

        if (!$this->isSuper() && empty($data['opd_id'])) {
            return back()->withErrors(['opd_id' => 'OPD wajib dipilih.'])->withInput();
        }

        $user = new User();
        $user->name        = $data['name'];
        $user->email       = $data['email'];
        $user->password    = Hash::make($data['password']);
        $user->opd_id      = $data['opd_id'] ?? null;
        $user->opd_unit_id = $data['opd_unit_id'] ?? null;
        $user->is_active   = (int) ($data['is_active'] ?? 1);
        $user->save();

        $this->syncRolesWithOpd($user, $data['roles'] ?? []);

        // ==== Activity Log (best-effort) ====
        try {
            $roleNames = $user->roles()->pluck('name')->values();
            activity('users')
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->event('created')
                ->withProperties([
                    'user' => [
                        'id'   => $user->id,
                        'name' => $user->name,
                        'email'=> $user->email,
                    ],
                    'org'  => ['opd_id' => $user->opd_id, 'opd_unit_id' => $user->opd_unit_id],
                    'roles'=> $roleNames,
                ])
                ->log('Membuat pengguna');
        } catch (\Throwable $e) {
            logger()->warning('Activitylog users.create failed: '.$e->getMessage());
        }

        return redirect()->route('users.index')->with('ok','Pengguna berhasil ditambahkan.');
    }

    public function update(UserUpdateRequest $request, User $user)
    {
        $this->abortIfCannotTouch($user);

        $data = $request->validated();

        // Non-super → paksa organisasi sesuai scope
        $this->enforceOrgForNonSuper($data);

        $request->validate([
            'opd_unit_id' => [
                'nullable','integer',
                Rule::exists('opd_units','id')
                    ->when(($data['opd_id'] ?? null), fn($q) => $q->where('opd_id', (int)$data['opd_id']))
            ],
        ]);

        // Simpan nilai awal untuk keperluan audit perubahan
        $before = $user->only(['name','email','opd_id','opd_unit_id','is_active']);

        // Set dan tangkap field yang akan berubah
        $user->name  = $data['name'];
        $user->email = $data['email'];
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->opd_id      = $data['opd_id'] ?? null;
        $user->opd_unit_id = $data['opd_unit_id'] ?? null;
        $user->is_active   = (int) ($data['is_active'] ?? 0);

        // Field yang kotor (akan berubah)
        $dirtyKeys = array_keys($user->getDirty());

        $user->save();

        // Filter role aman
        $rawRoleIds = collect($data['roles'] ?? [])->map(fn($v)=>(int)$v)->filter()->unique()->values();
        $roleQuery  = Role::query()->whereIn('id', $rawRoleIds);

        if ($this->isSuper()) {
            if ($user->opd_id) {
                $roleQuery->where(function ($w) use ($user) {
                    $w->where('opd_id', $user->opd_id)->orWhereNull('opd_id');
                });
            } else {
                $roleQuery->whereNull('opd_id');
            }
        } else {
            [, $opdId] = $this->myScope();
            $roleQuery->where(function ($w) use ($opdId) {
                $w->whereNull('opd_id')->orWhere('opd_id', $opdId);
            })->where('name','!=','superadmin');
        }

        $this->syncRolesWithOpd($user, $roleQuery->pluck('id')->all());

        // ==== Activity Log (best-effort) ====
        try {
            $after = $user->only(['name','email','opd_id','opd_unit_id','is_active']);
            $changes = [];
            foreach ($dirtyKeys as $k) {
                if (array_key_exists($k, $after)) {
                    $changes[$k] = ['from' => $before[$k] ?? null, 'to' => $after[$k]];
                }
            }
            $roleNames = $user->roles()->pluck('name')->values();

            activity('users')
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->event('updated')
                ->withProperties([
                    'user'    => ['id' => $user->id, 'email' => $user->email],
                    'changes' => $changes,
                    'roles'   => $roleNames,
                ])
                ->log('Memperbarui pengguna');
        } catch (\Throwable $e) {
            logger()->warning('Activitylog users.update failed: '.$e->getMessage());
        }

        return redirect()->route('users.index')->with('ok', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(User $user)
    {
        $this->abortIfCannotTouch($user);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['msg'=>'Tidak boleh menghapus akun Anda sendiri.']);
        }

        // Ambil info sebelum dihapus untuk log
        $snap = $user->only(['id','name','email','opd_id','opd_unit_id']);

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        $user->delete();

        // ==== Activity Log (best-effort) ====
        try {
            activity('users')
                ->causedBy(auth()->user())
                ->event('deleted')
                ->withProperties(['user' => $snap])
                ->log('Menghapus pengguna');
        } catch (\Throwable $e) {
            logger()->warning('Activitylog users.delete failed: '.$e->getMessage());
        }

        return back()->with('ok','Pengguna berhasil dihapus.');
    }

    /* ======================= Sinkronisasi Role ======================== */

    /**
     * Sinkronkan role user + simpan kolom opd_id di tabel pivot model_has_roles.
     * - Role global (opd_id NULL) disimpan apa adanya.
     * - Role OPD disimpan dengan opd_id milik role tersebut.
     * - Non-super tidak bisa memberi role 'superadmin'.
     */
    private function syncRolesWithOpd(User $user, array $roleIds): void
    {
        $roleIds = array_map('intval', $roleIds);

        $roles = Role::query()
            ->whereIn('id', $roleIds)
            ->when(!$this->isSuper(), function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('opd_id')->orWhere('opd_id', auth()->user()->opd_id);
                })->where('name','!=','superadmin');
            })
            ->get(['id','name','opd_id']);

        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->delete();

        $rows = [];
        foreach ($roles as $r) {
            if ($r->name === 'superadmin' && !$this->isSuper()) continue;
            $rows[] = [
                'role_id'    => $r->id,
                'model_type' => User::class,
                'model_id'   => $user->id,
                'opd_id'     => $r->opd_id, // NULL = global, angka = role OPD
            ];
        }
        if ($rows) {
            DB::table('model_has_roles')->insert($rows);
        }
    }
}
