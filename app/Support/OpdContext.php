<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;

class OpdContext
{
    protected ?int $opdId = null;
    protected bool $locked = false;

    public function get(): ?int
    {
        return $this->opdId;
    }

    /** Set konteks saat ini. $persistForSuperadmin=true => simpan ke user_contexts */
    public function set(?int $opdId, bool $persistForSuperadmin = false): void
    {
        $this->opdId = $opdId;

        // simpan juga di session agar mudah di Blade / controller
        Session::put('current_opd_id', $opdId);

        // persist hanya untuk superadmin (opsional di jalur 1; UI menyusul)
        if ($persistForSuperadmin && Auth::check() && Auth::user()->hasRole('superadmin')) {
            DB::table('user_contexts')->updateOrInsert(
                ['user_id' => Auth::id()],
                ['current_opd_id' => $opdId, 'updated_at' => now()]
            );
        }
    }

    /** Kunci konteks (admin OPD) — superadmin tidak dikunci */
    public function lock(): void
    {
        $this->locked = true;
        Session::put('opd_locked', true);
    }

    public function locked(): bool
    {
        return $this->locked;
    }
}
