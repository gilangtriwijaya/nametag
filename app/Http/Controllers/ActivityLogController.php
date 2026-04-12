<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Daftar log aktivitas dengan filter dan ringkasan yang rapi.
     */
    public function index(Request $r)
    {
        // Opsi filter (semua opsional)
        $q        = trim((string) $r->query('q', ''));
        $event    = trim((string) $r->query('event', ''));   // created|updated|deleted|...
        $logName  = trim((string) $r->query('log', ''));     // mis. "employees", "qr"
        $userId   = (int) $r->query('user_id', 0);
        $dateFrom = $r->query('d1');                         // yyyy-mm-dd
        $dateTo   = $r->query('d2');                         // yyyy-mm-dd

        $query = ActivityLog::query()
            ->with(['causer:id,name']) // hindari N+1, cukup ambil nama user
            ->when($q !== '', function ($qr) use ($q) {
                $qr->where(function ($w) use ($q) {
                    $w->where('description', 'like', "%{$q}%")
                        ->orWhere('properties', 'like', "%{$q}%")
                        ->orWhere('subject_type', 'like', "%{$q}%")
                        ->orWhere('subject_id', 'like', "%{$q}%");
                });
            })
            ->when($event !== '', fn ($qr) => $qr->where('event', $event))
            ->when($logName !== '', fn ($qr) => $qr->where('log_name', $logName))
            ->when($userId > 0, fn ($qr) => $qr->where('causer_id', $userId))
            ->when($dateFrom, fn ($qr) => $qr->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($qr) => $qr->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('id');

        $logs = $query
            ->paginate(20)
            ->withQueryString();

        // Tambah field turunan yang dipakai di Blade (causer_name, subject_label, short_properties)
        $logs->getCollection()->transform(function (ActivityLog $log) {
            // Nama user penyebab (kalau ada)
            $log->causer_name = $log->causer?->name;

            // Decode properti supaya gampang dibaca
            $props = $this->decodeProps($log->properties);

            // Ringkasan perubahan / isi properties
            $log->short_properties = $this->buildShortPropertiesSummary($props);

            // Label subject (coba ambil nama / title kalau ada)
            $log->subject_label = $this->guessSubjectLabel($props, $log);

            return $log;
        });

        // Sumber dropdown user (opsional; kalau user ribuan nanti bisa dibatasi)
        $users = User::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Daftar nilai distinct untuk event & log_name (buat future UI dropdown)
        $events   = ActivityLog::query()->select('event')->distinct()->pluck('event')->filter()->values();
        $logNames = ActivityLog::query()->select('log_name')->distinct()->pluck('log_name')->filter()->values();

        return view('logs.index', [
            'logs'      => $logs,
            'q'         => $q,
            'event'     => $event,
            'logName'   => $logName,
            'userId'    => $userId,
            'dateFrom'  => $dateFrom,
            'dateTo'    => $dateTo,
            'users'     => $users,
            'events'    => $events,
            'logNames'  => $logNames,
        ]);
    }

    /**
     * Detail satu log.
     * - Untuk request AJAX (modal) → balas JSON.
     * - Untuk request biasa (akses langsung) → bisa diarahkan ke view detail (opsional).
     */
    public function show(Request $request, ActivityLog $activity)
    {
        $propsArray = $this->decodeProps($activity->properties);

        $payload = [
            'id'          => $activity->id,
            'log_name'    => $activity->log_name,
            'event'       => $activity->event,
            'description' => $activity->description,
            'causer'      => $activity->causer?->only(['id', 'name']),
            'subject'     => [
                'type'  => $activity->subject_type,
                'id'    => $activity->subject_id,
                'label' => $this->guessSubjectLabel($propsArray, $activity),
            ],
            'properties'      => $propsArray,
            'short_properties'=> $this->buildShortPropertiesSummary($propsArray),
            'created_at'      => $activity->created_at?->toDateTimeString(),
        ];

        // Kalau dari fetch() / AJAX → JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json($payload);
        }

        // Kalau someday mau halaman detail full:
        return view('logs.show', [
            'log'     => $activity,
            'payload' => $payload,
        ]);
    }

    /* ================= Helpers ================= */

    /**
     * Normalisasi / decode kolom properties.
     */
    private function decodeProps($props)
    {
        if (is_array($props)) {
            return $props;
        }

        if (is_string($props)) {
            $json = json_decode($props, true);
            return $json ?? $props;
        }

        // Bisa berupa stdClass dari cast JSON
        if (is_object($props)) {
            return json_decode(json_encode($props), true);
        }

        return $props;
    }

    /**
     * Buat ringkasan kecil dari properties untuk ditampilkan di tabel.
     * Misal: "nama: A → B, status_aktif: NONAKTIF → AKTIF"
     */
    private function buildShortPropertiesSummary($props): ?string
    {
        if (!is_array($props) || empty($props)) {
            return null;
        }

        // Struktur umum Spatie activity: attributes + old
        $attributes = $props['attributes'] ?? $props['new'] ?? null;
        $old        = $props['old']        ?? null;

        // Kalau tidak ada "old", coba ringkaskan atribut saja
        if (is_array($attributes) && !is_array($old)) {
            // Ambil beberapa field penting kalau ada
            $interestingKeys = ['nama', 'name', 'nip', 'email', 'status_aktif', 'status', 'log_name'];
            $pairs = [];

            foreach ($interestingKeys as $k) {
                if (array_key_exists($k, $attributes)) {
                    $pairs[] = $k . ': ' . (string) $attributes[$k];
                }
            }

            if (!empty($pairs)) {
                $str = implode(', ', $pairs);
            } else {
                // fallback: ambil maksimal 3 field pertama
                $slice = array_slice($attributes, 0, 3, true);
                $pairs = [];
                foreach ($slice as $k => $v) {
                    $pairs[] = $k . ': ' . (is_scalar($v) ? $v : json_encode($v));
                }
                $str = implode(', ', $pairs);
            }

            return mb_strimwidth($str, 0, 160, '…', 'UTF-8');
        }

        // Kalau ada old vs attributes → tampilkan perubahan
        if (is_array($attributes) && is_array($old)) {
            $changes = [];

            foreach ($attributes as $key => $newVal) {
                if (!array_key_exists($key, $old)) {
                    continue;
                }
                $oldVal = $old[$key];

                if ($oldVal === $newVal) {
                    continue;
                }

                // Batasi panjang nilai
                $oldStr = mb_strimwidth((string) $oldVal, 0, 30, '…', 'UTF-8');
                $newStr = mb_strimwidth((string) $newVal, 0, 30, '…', 'UTF-8');

                $changes[] = "{$key}: {$oldStr} → {$newStr}";
            }

            if (empty($changes)) {
                return null;
            }

            $summary = implode(', ', $changes);

            return mb_strimwidth($summary, 0, 200, '…', 'UTF-8');
        }

        return null;
    }

    /**
     * Coba tebak label subject dari properties (nama pegawai, nama OPD, dsb.)
     */
    private function guessSubjectLabel($props, ActivityLog $activity): ?string
    {
        if (!is_array($props)) {
            return null;
        }

        $attributes = $props['attributes'] ?? $props['new'] ?? $props;

        if (!is_array($attributes)) {
            return null;
        }

        // Prioritas field yang biasanya ada
        foreach (['nama', 'name', 'title', 'judul'] as $key) {
            if (!empty($attributes[$key])) {
                return (string) $attributes[$key];
            }
        }

        // Kalau tidak ketemu, tapi ini log pegawai, coba dari nip
        if (
            $activity->subject_type &&
            stripos($activity->subject_type, 'Employee') !== false &&
            !empty($attributes['nip'])
        ) {
            return 'NIP: ' . $attributes['nip'];
        }

        return null;
    }
}
