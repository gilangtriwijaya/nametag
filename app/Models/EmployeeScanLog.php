<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeScanLog extends Model
{
    use HasFactory;

    protected $table = 'employee_scan_logs';

    /**
     * Kolom yang boleh di-mass assign.
     *
     * Secara default kita pakai timestamps (created_at/updated_at).
     * Kalau di tabel kamu tidak ada updated_at, tidak masalah, hanya tidak terpakai.
     */
    protected $fillable = [
        'token_id',
        'ip_address',
        'user_agent',
        'result',
    ];

    protected $casts = [
        'token_id' => 'int',
    ];

    /* ==================== Relations ==================== */

    /**
     * QR token yang discan.
     */
    public function token()
    {
        return $this->belongsTo(EmployeeQrToken::class, 'token_id');
    }

    /**
     * Pegawai yang terkait melalui token.
     *
     * scan_log -> employee_qr_tokens -> employees
     */
    public function employee()
    {
        return $this->hasOneThrough(
            Employee::class,
            EmployeeQrToken::class,
            'id',          // employee_qr_tokens.id
            'id',          // employees.id
            'token_id',    // employee_scan_logs.token_id
            'employee_id'  // employee_qr_tokens.employee_id
        );
    }

    /* ==================== Scopes helper ==================== */

    public function scopeLatest($q)
    {
        return $q->orderByDesc('id');
    }

    public function scopeResult($q, ?string $result)
    {
        if (!$result) return $q;

        return $q->where('result', $result);
    }

    public function scopeForTokenId($q, int $tokenId)
    {
        return $q->where('token_id', $tokenId);
    }

    /**
     * Filter berdasar range tanggal created_at (opsional)
     */
    public function scopeBetweenDate($q, ?string $from, ?string $to)
    {
        if ($from) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('created_at', '<=', $to);
        }
        return $q;
    }
}
