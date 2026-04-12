<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\User as UserModel;
use App\Models\Role as RoleModel;
use App\Models\UserUnitRole as UserUnitRoleModel;
use App\Models\SsoSyncLog;

class MirrorUsersFromSso extends Command
{
    protected $signature = 'sso:mirror-users {--since= : only mirror users updated since this datetime} {--chunk=500 : chunk size}';

    protected $description = 'Mirror users from SSO DB into local users (by sso_user_id)';

    public function handle(): int
    {
        $chunk = (int)$this->option('chunk');
        $since = $this->option('since');

        $query = DB::connection('sso')->table('users')->orderBy('id');
        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        $total = 0; $inserted = 0; $updated = 0; $skipped = 0;

        $opdMap = DB::table('opds')->pluck('id', 'sso_id')->all();

        // determine nametag app id in SSO (if present)
        $ssoAppId = DB::connection('sso')->table('apps')->where('code', 'nametag')->value('id');

        // create a log entry and run chunk inside try/catch so we always store finish timestamp
        $log = null;
        try {
            try { $log = SsoSyncLog::create(['command' => 'sso:mirror-users', 'started_at' => now(), 'status' => 'running']); } catch (\Throwable $_) {}

            $query->chunk($chunk, function ($rows) use (&$total, &$inserted, &$updated, &$skipped, $opdMap, $ssoAppId) {
                foreach ($rows as $r) {
                    $total++;
                    $ssoId = (int)($r->id ?? 0);
                    if (!$ssoId) { $skipped++; continue; }

                    $localOpdId = $opdMap[(int)($r->opd_id ?? 0)] ?? null;
                    $localUnitId = null; // unit mapping requires opd_units map; skip if missing
                    if (!empty($r->opd_unit_id)) {
                        $localUnitId = DB::table('opd_units')->where('sso_id', (int)$r->opd_unit_id)->value('id');
                    }

                    $payload = [
                        'sso_user_id'  => $ssoId,
                        'username'     => $r->username ?? null,
                        'name'         => $r->name ?? null,
                        'email'        => $r->email ?? null,
                        'user_type_id' => $r->user_type_id ?? null,
                        'opd_id'       => $localOpdId,
                        'opd_unit_id'  => $localUnitId,
                        'is_active'    => (int)($r->is_active ?? 1),
                        'password'     => null,
                        'updated_at'   => now(),
                    ];

                    $existing = DB::table('users')->where('sso_user_id', $ssoId)->value('id');
                    if ($existing) {
                        DB::table('users')->where('id', $existing)->update($payload);
                        $updated++;
                    } else {
                        $payload['created_at'] = now();
                        DB::table('users')->insert($payload);
                        $inserted++;
                    }

                    // Sync role from SSO if available: SSO maps user_type_id -> app_role in `user_app_roles`.
                    try {
                        if (! empty($ssoAppId) && ! empty($r->user_type_id)) {
                            $appRole = DB::connection('sso')
                                ->table('user_app_roles')
                                ->where('user_type_id', $r->user_type_id)
                                ->where('app_id', $ssoAppId)
                                ->value('app_role');

                            if ($appRole) {
                                // determine target local role name based on SSO role and whether user is unit-level
                                $appRoleLower = mb_strtolower(trim((string)$appRole));

                                // simple mapping rules; extend as needed
                                if ($appRoleLower === 'opd') {
                                    // if SSO user has opd_unit_id -> treat as unit operator
                                    $targetRole = !empty($r->opd_unit_id) ? 'verifikator_unit' : 'admin_opd';
                                } else {
                                    // default: normalize name (spaces/dashes -> underscore)
                                    $targetRole = str_replace([' ', '-'], '_', $appRoleLower);
                                }

                                // ensure local role exists (create if missing)
                                try {
                                    $role = RoleModel::where('name', $targetRole)->first();
                                    if (! $role) {
                                        $role = RoleModel::create(['name' => $targetRole, 'guard_name' => 'web']);
                                    }

                                    // assign role to local user
                                    $localUserId = $existing ?: DB::table('users')->where('sso_user_id', $ssoId)->value('id');
                                    if ($localUserId) {
                                        $userModel = UserModel::find($localUserId);
                                        if ($userModel) {
                                            $userModel->syncRoles([$role->name]);

                                            // if unit-level role, also create a user_unit_roles entry (role with space)
                                            if (!empty($r->opd_unit_id) && in_array($targetRole, ['verifikator_unit','admin_unit'])) {
                                                $unitRoleName = str_replace('_', ' ', $targetRole);
                                                UserUnitRoleModel::updateOrCreate([
                                                    'user_id' => $userModel->id,
                                                    'opd_unit_id' => $localUnitId,
                                                    'role' => $unitRoleName,
                                                ], [
                                                    'user_id' => $userModel->id,
                                                    'opd_unit_id' => $localUnitId,
                                                    'role' => $unitRoleName,
                                                ]);
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    // ignore role creation/assign errors
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // ignore role sync errors to avoid breaking the mirror job
                    }
                }
            });

            $this->info("OK: total={$total}, inserted={$inserted}, updated={$updated}, skipped={$skipped}");
            if ($log) { try { $log->update(['finished_at'=>now(), 'status'=>'success', 'message'=>"inserted={$inserted}, updated={$updated}, skipped={$skipped}"]); } catch (\Throwable $_) {} }
            return self::SUCCESS;
        } catch (\Throwable $e) {
            try { if ($log) $log->update(['finished_at'=>now(), 'status'=>'failed', 'message'=>$e->getMessage()]); } catch (\Throwable $_) {}
            $this->error('GAGAL: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
