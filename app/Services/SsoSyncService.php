<?php

namespace App\Services;

use App\Models\Opd;
use App\Models\OpdUnit;
use Illuminate\Support\Facades\Artisan;

class SsoSyncService
{
    /**
     * Ensure OPD & Unit master data from SSO exists locally; trigger mirror command if missing.
     */
    public function ensureOpdMirrorIfMissing(): void
    {
        if (!Opd::whereNotNull('sso_id')->exists() || !OpdUnit::whereNotNull('sso_id')->exists()) {
            Artisan::call('sso:mirror-opd');
        }
    }

    /**
     * Map SSO opd/opd_unit ids to local IDs.
     * Throws RuntimeException if mapping required but not found.
     *
     * @param array $ssoUser
     * @return array [opd_id => ?int, opd_unit_id => ?int]
     */
    public function mapOpdAndUnitIds(array $ssoUser): array
    {
        $localOpdId = null;
        if (!empty($ssoUser['opd_id'])) {
            $localOpdId = Opd::where('sso_id', $ssoUser['opd_id'])->value('id');
            if (!$localOpdId) {
                throw new \RuntimeException('OPD SSO belum termirror');
            }
        }

        $localUnitId = null;
        if (!empty($ssoUser['opd_unit_id'])) {
            $localUnitId = OpdUnit::where('sso_id', $ssoUser['opd_unit_id'])->value('id');
            if (!$localUnitId) {
                throw new \RuntimeException('Unit OPD SSO belum termirror');
            }
        }

        return ['opd_id' => $localOpdId, 'opd_unit_id' => $localUnitId];
    }
}
