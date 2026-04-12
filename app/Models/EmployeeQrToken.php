<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class EmployeeQrToken extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $table = 'employee_qr_tokens';

    protected $fillable = [
        'employee_id',
        'token',
        'status',
        'revoked_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'employee_id' => 'int',
        'revoked_at'  => 'datetime',
        'created_by'  => 'int',
        'updated_by'  => 'int',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /* ============================================================
       RELATIONS
       ============================================================ */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /* ============================================================
       SCOPES
       ============================================================ */
    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOfEmployee($q, int $employeeId)
    {
        return $q->where('employee_id', $employeeId);
    }

    public function scopeLatest($q)
    {
        return $q->orderByDesc('id');
    }

    /* ============================================================
       HELPERS
       ============================================================ */
    public function revoke(?int $byUserId = null): void
    {
        if ($this->status === self::STATUS_REVOKED) {
            return;
        }

        $this->status     = self::STATUS_REVOKED;
        $this->revoked_at = now();

        if ($byUserId) {
            $this->updated_by = $byUserId;
        }

        $this->save();
    }

    /* ============================================================
       MODEL EVENTS — SINGLE-ACTIVE GUARANTEE
       ============================================================ */
    protected static function booted()
    {
        /**
         * Ketika token BARU dibuat:
         * - Isi created_by / updated_by secara otomatis jika user login.
         * - Revoke seluruh token aktif sebelumnya (hanya status, bukan soft delete).
         */
        static::creating(function (EmployeeQrToken $m) {
            $uid = Auth::id(); // bisa null, jangan dipaksa

            if (!isset($m->created_by) && $uid) {
                $m->created_by = $uid;
            }
            if (!isset($m->updated_by) && $uid) {
                $m->updated_by = $uid;
            }

            if ($m->status === self::STATUS_ACTIVE && $m->employee_id) {
                // Revoke semua token aktif sebelumnya
                static::where('employee_id', $m->employee_id)
                    ->where('status', self::STATUS_ACTIVE)
                    ->update([
                        'status'     => self::STATUS_REVOKED,
                        'revoked_at' => now(),
                        'updated_at' => now(),
                        'updated_by' => $uid,
                    ]);
            }
        });

        /**
         * Saat update status menjadi ACTIVE:
         * - Pastikan tidak ada token aktif lain.
         */
        static::updating(function (EmployeeQrToken $m) {
            $uid = Auth::id();

            // Update selalu mengisi updated_by kalau ada user login
            if ($uid) {
                $m->updated_by = $uid;
            }

            if ($m->isDirty('status') && $m->status === self::STATUS_ACTIVE) {
                static::where('employee_id', $m->employee_id)
                    ->where('id', '!=', $m->id)
                    ->where('status', self::STATUS_ACTIVE)
                    ->update([
                        'status'     => self::STATUS_REVOKED,
                        'revoked_at' => now(),
                        'updated_at' => now(),
                        'updated_by' => $uid,
                    ]);
            }
        });
    }
}
