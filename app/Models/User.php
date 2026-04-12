<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * Guard Spatie yang dipakai aplikasi.
     */
    protected string $guard_name = 'web';

    /**
     * Kita izinkan mass-assign semua kolom yang aman.
     * Pastikan validasi request ketat.
     */
    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'opd_id'            => 'int',
        'opd_unit_id'       => 'int',
        'sso_app_roles'     => 'array',
        'sso_allowed_opds_by_app' => 'array',
    ];

    /* ========= Relations ========= */

    /** OPD induk (akun level OPD atau Unit). */
    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    /** Unit OPD (null jika akun level OPD). */
    public function opdUnit(): BelongsTo
    {
        return $this->belongsTo(OpdUnit::class, 'opd_unit_id');
    }

    /**
     * (Opsional) Role level unit khusus jika Anda memang punya tabel user_unit_roles.
     * Abaikan relasi ini jika tidak diperlukan.
     */
    public function unitRoles(): HasMany
    {
        return $this->hasMany(UserUnitRole::class, 'user_id');
    }

    /* ========= Scopes ========= */

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /* ========= Helpers ========= */

    /**
     * Auto-hash password hanya jika input BELUM berupa hash.
     * Jangan gunakan Hash::info() untuk mendeteksi — itu butuh string hash.
     */
    public function setPasswordAttribute($value): void
    {
        if (blank($value)) {
            // biarkan Eloquent/validator yang menolak jika wajib
            return;
        }

        // Heuristik sederhana untuk mendeteksi string hash yang lazim di Laravel
        $looksHashed = is_string($value) && Str::startsWith($value, [
            '$2y$',         // bcrypt
            '$argon2',      // argon2/argon2id
        ]);

        $this->attributes['password'] = $looksHashed ? $value : Hash::make($value);
    }

    /** Cek cepat role superadmin. */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /** Admin Bagian Organisasi (lintas OPD, bukan superadmin). */
    public function isOrgAdmin(): bool
    {
        return $this->hasRole('org_admin');
    }

    /** Akun operator level OPD (admin/verifikator OPD). */
    public function isOpdOperator(): bool
    {
        return $this->hasAnyRole([
            'admin opd', 'admin-opd', 'admin_opd',
            'verifikator opd', 'verifikator-opd', 'verifikator_opd',
        ]) && is_null($this->opd_unit_id);
    }

    /**
     * Akun operator level Unit.
     * Mendukung dua cara: via Spatie roles (nama role) ATAU via tabel user_unit_roles (opsional).
     */
    public function isUnitOperator(): bool
    {
        $byRoleName = $this->hasAnyRole([
            'admin unit', 'admin-unit', 'admin_unit',
            'verifikator unit', 'verifikator-unit', 'verifikator_unit',
        ]);

        $byCustomTable = $this->relationLoaded('unitRoles')
            ? $this->unitRoles->whereIn('role', ['admin unit', 'verifikator unit'])->isNotEmpty()
            : $this->unitRoles()->whereIn('role', ['admin unit', 'verifikator unit'])->exists();

        return (bool) ($this->opd_unit_id) || $byRoleName || $byCustomTable;
    }

    /**
     * Daftar ID unit yang dikelola user.
     * - Jika user adalah operator unit → [opd_unit_id]
     * - Jika operator OPD → seluruh unit di OPD tsb
     * - Jika superadmin → kosongkan (biar caller tentukan sendiri) atau kembalikan semua unit (sesuaikan kebutuhan)
     */
    public function managedUnitIds(): array
    {
        if ($this->opd_unit_id) {
            return [(int) $this->opd_unit_id];
        }

        if ($this->isSuperAdmin()) {
            return []; // interpretasi: tanpa batasan; biarkan query pemanggil abaikan filter
        }

        if ($this->opd_id) {
            return OpdUnit::where('opd_id', (int) $this->opd_id)->pluck('id')->map(fn ($v) => (int)$v)->all();
        }

        return [];
    }

    /**
     * Return array of allowed OPD ids for given app_code from sso_allowed_opds table.
     * If no restrictions present, returns empty array.
     */
    public function ssoAllowedOpdIds(string $appCode = null): array
    {
        $appCode = $appCode ?? (string) config('services.sso.app_code', env('SSO_APP_CODE', 'nametag'));
        return \App\Models\SsoAllowedOpd::where('user_id', $this->id)
            ->where('app_code', $appCode)
            ->pluck('opd_id')
            ->map(fn($v) => (int)$v)
            ->all();
    }
}
