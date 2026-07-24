<?php

namespace App\Models;

use App\Models\EmployeeQrToken;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employees';

    /**
     * Hanya kolom yang benar-benar ada di tabel yang dimasukkan ke $fillable.
     */
    protected $fillable = [
        'opd_id',
        'opd_unit_id',
        'unit_kerja_id',
        'nip',
        'nama',
        'jenis_kelamin',
        'gelar_depan',
        'gelar_belakang',
        'gelar_belakang_input',
        'jabatan',
        'jabatan_type',
        'tmt_jabatan',
        'nama_unit_opd',
        'tingkat_pendidikan',
        'nama_pendidikan',
        'pangkat',
        'golongan',
        'gol_darah',
        'status_kepegawaian',
        'status_aktif',
        'no_hp',
        'email',
        'tgl_lahir',
        'alamat',
        'foto_path',
        'foto_is_manual',
        'sk_file_path',
        'sk_uploaded_at',
        'created_by',
        'updated_by',
        'deleted_by',
        // nametag status fields (allow mass assignment from jobs/controllers)
        'nametag_status',
        'nametag_generated_at',
        'nametag_error',
    ];

    /**
     * Kolom virtual untuk tampilan.
     */
    protected $appends = [
        'foto_url',
        'nama_lengkap',
    ];

    protected $casts = [
        'opd_id'         => 'integer',
        'opd_unit_id'    => 'integer',
        'created_by'     => 'integer',
        'updated_by'     => 'integer',
        'deleted_by'     => 'integer',
        'tmt_jabatan'    => 'date',
        'tgl_lahir'      => 'date',
        'sk_uploaded_at' => 'datetime',
        'foto_is_manual' => 'boolean',
    ];

    /**
     * Kolom hasil constraint / generated column: jangan di-mass assign.
     */
    protected $guarded = [
        'nip_if_active',
    ];

    /* ===================== Accessors ===================== */

    /**
     * URL publik foto pegawai (atau null kalau tidak ada).
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (! $this->foto_path) {
            return null;
        }

        $path = ltrim($this->foto_path, '/');

        // Kalau sudah URL penuh, langsung balikin apa adanya.
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // Default: diasumsikan file di public/...
        return asset($path);
    }

    /**
     * Nama lengkap dengan gelar depan & belakang,
     * sekaligus menghindari gelar dobel (mis. sudah nempel di kolom nama).
     */
    public function getNamaLengkapAttribute(): string
    {
        $rawName = trim((string) $this->nama);

        $gdRaw     = trim((string) $this->gelar_depan);
        $gdDisplay = $gdRaw !== '' ? rtrim($gdRaw, " .") . '.' : '';

        $gbRaw     = trim((string) $this->gelar_belakang, " ,");
        $gbDisplay = $gbRaw;

        $name = $rawName;

        // Buang gelar depan yang sudah nempel di kolom nama
        if ($gdRaw !== '') {
            $prefixCore = preg_quote(rtrim($gdRaw, " ."), '/');
            $name = preg_replace('/^' . $prefixCore . '\.?\s+/iu', '', $name) ?? $name;
        }

        // Buang gelar belakang yang sudah nempel di kolom nama
        if ($gbRaw !== '') {
            $suffixCore = preg_quote($gbRaw, '/');
            $name = preg_replace('/(?:,\s*)?' . $suffixCore . '\.?\s*$/iu', '', $name) ?? $name;
            $name = rtrim($name, " ,");
        }

        $full = $name;

        if ($gdDisplay !== '') {
            $full = $gdDisplay . ' ' . $full;
        }
        if ($gbDisplay !== '') {
            $full .= ', ' . $gbDisplay;
        }

        return trim($full);
    }

    /**
     * Token QR terbaru:
     * - coba ambil yang status = 'active'
     * - kalau tidak ada, ambil token terakhir (id terbesar)
     */
    public function getLatestQrTokenAttribute(): ?string
    {
        try {
            $q = $this->qrTokens()->orderByDesc('id');

            $active = (clone $q)->where('status', 'active')->value('token');
            if ($active) {
                return $active;
            }

            return $q->value('token') ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Alias legacy: $employee->qr_token
     *
     * Kalau ada kolom lama qr_token di DB, pakai itu dulu.
     * Kalau tidak, fallback ke accessor latest_qr_token.
     */
    public function getQrTokenAttribute(): ?string
    {
        if (
            isset($this->attributes['qr_token']) &&
            ! empty($this->attributes['qr_token'])
        ) {
            return $this->attributes['qr_token'];
        }

        return $this->latest_qr_token;
    }

    /* ===================== Relations ===================== */

    /**
     * OPD induk pegawai.
     */
    public function opd()
    {
        return $this->belongsTo(Opd::class);
    }

    /**
     * Unit kerja (jika ada).
     */
    public function opdUnit()
    {
        return $this->belongsTo(OpdUnit::class, 'opd_unit_id');
    }

    /**
     * Normalized Unit Kerja relation (FK to unit_kerja table)
     */
    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class, 'unit_kerja_id');
    }

    /**
     * User pembuat.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User pengubah terakhir.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * User penghapus (soft delete).
     */
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Semua token QR pegawai, terbaru dulu.
     */
    public function qrTokens()
    {
        // urut terbaru dulu supaya akses cepat
        return $this->hasMany(EmployeeQrToken::class, 'employee_id')
            ->orderByDesc('id');
    }

    /* ===================== Scopes ===================== */

    /**
     * Scope: pegawai yang status_aktif = 'AKTIF'.
     */
    public function scopeActive($q)
    {
        return $q->where('status_aktif', 'AKTIF');
    }

    /**
     * Scope: filter berdasarkan OPD ID (null = no-op).
     */
    public function scopeOfOpd($q, $opdId)
    {
        return $q->when(
            $opdId,
            fn ($qq) => $qq->where('opd_id', (int) $opdId)
        );
    }

    /**
     * Scope: pencarian bebas (nama, jabatan, email, pendidikan, NIP).
     * Pakai full-text kalau tersedia, fallback LIKE kalau tidak.
     */
    public function scopeSearch($q, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $q;
        }

        return $q->where(function ($qq) use ($term) {
            try {
                $qq->whereFullText(
                    ['nama', 'jabatan', 'email', 'nama_pendidikan'],
                    $term
                );
            } catch (\Throwable $e) {
                $like = '%' . $term . '%';

                $qq->where('nama', 'like', $like)
                    ->orWhere('jabatan', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('nama_pendidikan', 'like', $like)
                    ->orWhere('nip', 'like', $like);
            }
        });
    }

    /**
     * Scope helper "visibleTo" — cadangan bila tidak pakai EmployeeQueryService.
     * Diselaraskan secara kasar dengan pola role di policy & service.
     */
    public function scopeVisibleTo($q, ?User $user)
    {
        if (! $user) {
            // tanpa user, tidak boleh lihat apa-apa
            return $q->whereRaw('1=0');
        }

        $isGlobal =
            $user->hasRole('superadmin') ||
            $user->hasAnyRole([
                'org_admin',
                'admin_organisasi',
                'admin_bagor',
                'Admin Bagor',
                'admin bagor',
            ]);

        $isOpd = $user->hasAnyRole([
            'admin_opd',
            'admin opd',
            'admin-opd',
            'verifikator_opd',
            'verifikator opd',
            'verifikator-opd',
        ]);

        // Role level unit = bukan global & bukan admin/verifikator OPD
        $isUnit = ! $isGlobal && ! $isOpd;

        if ($isGlobal) {
            // Global: lihat semua pegawai
            return $q;
        }

        // Lock ke OPD user
        $q->where('opd_id', (int) $user->opd_id);

        if ($isUnit && method_exists($user, 'managedUnitIds')) {
            $unitIds = array_map('intval', (array) $user->managedUnitIds());
            if (! empty($unitIds)) {
                $q->whereIn('opd_unit_id', $unitIds);
            }
        }

        // Admin OPD & Verifikator OPD tidak dibatasi unit (bisa lihat induk + unit)
        return $q;
    }

    /* ===================== Helpers ===================== */

    public function isActive(): bool
    {
        return (string) $this->status_aktif === 'AKTIF';
    }

    /**
     * Presentation helper: label to use for NIP field depending on
     * status_kepegawaian. Returns 'NIPPPK.' when status is PPPK, otherwise 'NIP.'.
     */
    public function getNipLabelAttribute(): string
    {
        return ((string) $this->status_kepegawaian === 'PPPK') ? 'NIPPPK.' : 'NIP.';
    }

    /* ===================== Audit fields ===================== */

    protected static function booted()
    {
        // created_by & updated_by otomatis
        static::creating(function (Employee $m) {
            if (auth()->check()) {
                $m->created_by ??= auth()->id();
                $m->updated_by ??= auth()->id();
            }
        });

        static::updating(function (Employee $m) {
            if (auth()->check()) {
                $m->updated_by = auth()->id();
            }
        });

        // saat soft delete, set deleted_by supaya terekam pelakunya
        static::deleting(function (Employee $m) {
            if (auth()->check()) {
                $m->deleted_by = auth()->id();
            }
        });
    }
}
