<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Services\SsoSyncService;
use App\Services\UserSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SsoLoginController extends Controller
{
    public function __construct(protected SsoSyncService $ssoSync, protected UserSyncService $userSync)
    {
    }
    private function ssoBase(): string
    {
        $base = rtrim(config('services.sso.base_url', env('SSO_BASE_URL', '')), '/');
        if ($base === '') abort(500, 'SSO_BASE_URL not set');
        if (!Str::startsWith($base, 'https://')) abort(500, 'SSO_BASE_URL must start with https://');
        return $base;
    }

    private function appCode(): string
    {
        return (string) config('services.sso.app_code', env('SSO_APP_CODE', 'nametag'));
    }

    private function secret(): string
    {
        $secret = (string) config('services.sso.ticket_secret', env('SSO_TICKET_SECRET', ''));
        if ($secret === '') abort(500, 'SSO_TICKET_SECRET not set');
        return $secret;
    }

    public function redirectToSso(Request $request)
    {
        $intended = url()->previous();
        if (!$intended || Str::contains($intended, ['/sso/callback', '/sso/login'])) {
            $intended = url('/anambas-id/dashboard');
        }

        $state = Str::random(24);
        session(['sso.intended' => $intended, 'sso.state' => $state]);

        return redirect()->away(
            $this->ssoBase() . '/sso/authorize?' . http_build_query([
                'app'          => $this->appCode(),
                'redirect_uri' => url('/anambas-id/sso/callback'),
                'state'        => $state,
            ])
        );
    }

    public function callback(Request $request)
    {
        $request->validate([
            'ticket' => ['required', 'string'],
            'state'  => ['nullable', 'string'],
        ]);

        if (session('sso.state') && $request->state &&
            !hash_equals(session('sso.state'), $request->state)) {
            abort(419, 'Invalid SSO state');
        }
        session()->forget('sso.state');

        $ticket    = (string) $request->ticket;
        $appCode   = $this->appCode();
        $signature = hash_hmac('sha256', $ticket.'|'.$appCode, $this->secret());

        $resp = Http::withHeaders([
                'X-SSO-Signature' => $signature,
                'Accept' => 'application/json',
            ])
            ->asForm()
            ->timeout(15)
            ->post($this->ssoBase().'/api/sso/ticket/consume', [
                'ticket' => $ticket,
                'app'    => $appCode,
            ]);

        if (!$resp->successful()) abort(401, 'SSO ticket invalid');

        $ssoUser = $resp->json('user');
        if (!$ssoUser || empty($ssoUser['id'])) abort(401, 'Invalid SSO payload');

        // Audit log: record ticket and effective allowed list for this app (best-effort)
        try {
            $appCode = $this->appCode();
            $allowedByApp = $resp->json('user.allowed_opd_ids_by_app') ?? null;
            $allowedForApp = null;
            if (is_array($allowedByApp) && array_key_exists($appCode, $allowedByApp)) {
                $allowedForApp = $allowedByApp[$appCode];
            } else {
                $allowedForApp = $ssoUser['allowed_opd_ids'] ?? null;
            }
            logger()->info('SSO ticket consumed', [
                'ticket' => $ticket,
                'sso_user_id' => $ssoUser['id'] ?? null,
                'app' => $appCode,
                'allowed_for_app' => $allowedForApp,
            ]);
        } catch (\Throwable $e) {
            logger()->warning('Failed to log SSO ticket consume: '.$e->getMessage());
        }

        // ensure local master OPD/Unit exists
        $this->ssoSync->ensureOpdMirrorIfMissing();

        // map OPD/unit to local ids (may throw if missing)
        $mapped = $this->ssoSync->mapOpdAndUnitIds($ssoUser);

        // sync user record
        $user = $this->userSync->syncFromPayload($ssoUser, $mapped);

        if (!$user->is_active) abort(403, 'User inactive');

        Auth::login($user);
        $request->session()->regenerate();

        // update last_login_at for SSO logins (LoginController handles this for local auth)
        try {
            if (Schema::hasColumn($user->getTable(), 'last_login_at')) {
                $user->forceFill(['last_login_at' => now()])->save();
            }
        } catch (\Throwable $e) {
            logger()->warning('Failed to update last_login_at for SSO login: ' . $e->getMessage());
        }

        // Log SSO login activity (best-effort, should not block login)
        try {
            activity('auth')
                ->causedBy($user)
                ->event('login')
                ->withProperties([
                    'ip' => $request->ip(),
                    'ua' => $request->userAgent(),
                    'via' => 'sso',
                ])
                ->log('Login berhasil (SSO)');
        } catch (\Throwable $e) {
            logger()->warning('Activitylog SSO login failed: '.$e->getMessage());
        }

        return redirect(session()->pull('sso.intended', '/anambas-id/dashboard'));
    }

    public function backToSso(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->away(
            config('services.sso.home_url', 'https://sistagor.anambaskab.go.id/dashboard')
        );
    }
}
