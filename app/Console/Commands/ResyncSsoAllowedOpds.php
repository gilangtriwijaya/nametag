<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\UserSyncService;
use App\Models\SsoSyncLog;

class ResyncSsoAllowedOpds extends Command
{
    protected $signature = 'sso:resync-allowed-opds {--user= : optional sso_user_id to resync}';

    protected $description = 'Resync sso_allowed_opds table for users using stored sso_allowed_opds_by_app in users table.';

    public function handle(): int
    {
        $svc = new UserSyncService();

        $ssoId = $this->option('user');

        $query = User::query()->whereNotNull('sso_user_id');
        if ($ssoId) {
            $query->where('sso_user_id', (int)$ssoId);
        }

        $log = null;
        try { $log = SsoSyncLog::create(['command'=>'sso:resync-allowed-opds','started_at'=>now(),'status'=>'running']); } catch (\Throwable $_) {}

        $count = 0;
        try {
            $query->chunk(200, function ($users) use ($svc, &$count) {
                foreach ($users as $u) {
                    $count++;
                    $payload = [
                        'id' => $u->sso_user_id,
                        'app_role' => $u->sso_app_role ?? null,
                        'app_roles' => $u->sso_app_roles ?? null,
                        'app_role_slug' => null,
                        'allowed_opd_ids_by_app' => $u->sso_allowed_opds_by_app ?? null,
                        'allowed_opd_ids' => null,
                        'is_opd_locked' => null,
                    ];

                    try {
                        $svc->syncFromPayload($payload, []);
                        $this->info("Resynced user sso_user_id={$u->sso_user_id}");
                    } catch (\Throwable $e) {
                        $this->error("Failed resync sso_user_id={$u->sso_user_id}: {$e->getMessage()}");
                    }
                }
            });

            $this->info("Done resync for {$count} users.");
            try { if ($log) $log->update(['finished_at'=>now(),'status'=>'success','message'=>"resynced={$count}"]); } catch (\Throwable $_) {}
            return self::SUCCESS;
        } catch (\Throwable $e) {
            try { if ($log) $log->update(['finished_at'=>now(),'status'=>'failed','message'=>$e->getMessage()]); } catch (\Throwable $_) {}
            $this->error('GAGAL: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
