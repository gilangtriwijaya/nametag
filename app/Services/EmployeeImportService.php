<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Opd;
use App\Models\OpdUnit;

class EmployeeImportService
{
    /**
     * Parse uploaded Excel/CSV file and return preview data.
     * Returned structure:
     * [
     *   'rows' => [ ['row'=>1,'data'=>[], 'errors'=>[]], ... ],
     *   'summary' => ['valid'=>int,'invalid'=>int]
     * ]
     */
    public function parseUploadedFile(string $path): array
    {
        if (!class_exists(\Maatwebsite\Excel\Facades\Excel::class)) {
            throw new \RuntimeException('Package maatwebsite/excel tidak ditemukan. Jalankan: composer require maatwebsite/excel');
        }

        $sheets = \Maatwebsite\Excel\Facades\Excel::toArray(null, $path);
        if (empty($sheets) || !isset($sheets[0])) {
            throw new \RuntimeException('File kosong atau tidak bisa dibaca');
        }

        $sheet = $sheets[0];
        if (count($sheet) === 0) {
            throw new \RuntimeException('Sheet pertama kosong');
        }

        // Detect header row (first row) if cells are strings
        $firstRow = $sheet[0];
        $isHeader = true;
        foreach ($firstRow as $cell) {
            if (is_numeric($cell) && $cell !== '0') {
                $isHeader = false;
                break;
            }
        }

        $headers = [];
        $start = 0;
        if ($isHeader) {
            foreach ($firstRow as $h) {
                $normalized = Str::of($h)->lower()->trim()->replaceMatches('/\s+/', '_')->__toString();
                $headers[] = $normalized;
            }
            $start = 1;
        }

        $rows = [];
        $valid = 0;
        $invalid = 0;
        for ($i = $start; $i < count($sheet); $i++) {
            $raw = $sheet[$i];
            $assoc = [];
            if (!empty($headers)) {
                foreach ($headers as $colIndex => $colName) {
                    $val = isset($raw[$colIndex]) ? $raw[$colIndex] : null;
                    // normalize value to string where appropriate
                    if (is_string($val)) {
                        $val = trim($val);
                    }
                    $assoc[$colName] = $val !== null && $val !== '' ? $val : null;
                }
            } else {
                // fallback: map by positional known fields
                $assoc = [
                    'nip' => isset($raw[0]) ? trim((string)$raw[0]) : null,
                    'nama_lengkap' => isset($raw[1]) ? trim((string)$raw[1]) : null,
                    'email' => isset($raw[2]) ? trim((string)$raw[2]) : null,
                ];
            }

            // Normalize certain fields: NIP may come as excel numeric/scientific; try to coerce to integer string without decimals
            if (isset($assoc['nip']) && $assoc['nip'] !== null) {
                $nipRaw = $assoc['nip'];
                if (is_numeric($nipRaw)) {
                    // if scientific notation or float, attempt to format without decimals
                    // note: very large numbers may overflow float precision; recommend user format as text
                    $nipStr = (string) $nipRaw;
                    if (stripos($nipStr, 'E') !== false) {
                        // scientific notation -> attempt to format
                        $nipFormatted = sprintf('%.0f', (float) $nipRaw);
                        $assoc['nip'] = $nipFormatted;
                    } elseif (is_float($nipRaw + 0)) {
                        $assoc['nip'] = preg_replace('/\.0+$/', '', (string) $nipRaw);
                    } else {
                        $assoc['nip'] = (string) $nipRaw;
                    }
                } else {
                    $assoc['nip'] = trim((string) $assoc['nip']);
                }
            }
            // Normalize phone number: remove spaces and non-digits, handle leading country/zero
            if (isset($assoc['no_hp']) && $assoc['no_hp'] !== null) {
                $raw = $assoc['no_hp'];
                if (is_numeric($raw)) {
                    $raw = (string) $raw;
                    if (stripos($raw, 'E') !== false) {
                        $raw = sprintf('%.0f', (float) $raw);
                    }
                }
                // remove non-digit characters
                $digits = preg_replace('/[^0-9]/', '', (string) $raw);
                // if starts with country code 62, convert to leading 0
                if (preg_match('/^62(\d+)$/', $digits, $m)) {
                    $digits = '0' . $m[1];
                }
                // if starts with 8 (common local mobile without leading 0), add leading 0
                if (preg_match('/^[89]\d{7,}$/', $digits)) {
                    $digits = '0' . $digits;
                }
                $assoc['no_hp'] = $digits !== '' ? $digits : null;
            }

            $errors = $this->validateRow($assoc);
            if (empty($errors)) {
                $valid++;
            } else {
                $invalid++;
            }

            $rows[] = [
                'row' => $i + 1,
                'data' => $assoc,
                'errors' => $errors,
            ];
        }

        return [
            'rows' => $rows,
            'summary' => ['valid' => $valid, 'invalid' => $invalid],
        ];
    }

    protected function validateRow(array $data): array
    {
        $errors = [];
        // NIP presence and format (common Indonesian NIP is numeric, 18 digits in many setups)
        if (empty($data['nip'])) {
            $errors[] = 'NIP diperlukan';
        } else {
            if (!preg_match('/^\d{18}$/', $data['nip'])) {
                $errors[] = 'NIP harus angka 18 digit';
            }
        }

        if (empty($data['nama_lengkap'])) {
            $errors[] = 'Nama lengkap diperlukan';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email tidak valid';
        }

        // OPD identification is required (prefer local opd_id, fallback to opd_sso_id or opd name)
        if (empty($data['opd_id']) && empty($data['opd_sso_id']) && empty($data['opd'])) {
            $errors[] = 'OPD diperlukan (kolom opd_id atau opd_sso_id atau opd nama)';
        } else {
            // if an opd_id is provided, ensure it exists locally
            if (!empty($data['opd_id'])) {
                try {
                    if (!Opd::find($data['opd_id'])) {
                        $errors[] = 'OPD tidak ditemukan';
                    }
                } catch (\Throwable $e) {
                    // ignore DB errors here; deeper validation occurs during processing
                }
            }
            // if an opd_unit_id is provided, ensure it exists
            if (!empty($data['opd_unit_id'])) {
                try {
                    $unit = OpdUnit::find($data['opd_unit_id']);
                    if (!$unit) {
                        $errors[] = 'OPD unit tidak ditemukan';
                    } else {
                        // if both opd_id and unit present, ensure unit belongs to opd
                        if (!empty($data['opd_id']) && $unit->opd_id && $unit->opd_id != $data['opd_id']) {
                            $errors[] = 'OPD unit tidak sesuai dengan OPD';
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore DB errors here
                }
            }
        }

        // jabatan_type must be present and one of allowed enum values
        $allowedJabatanType = ['PELAKSANA','FUNGSIONAL','PENGAWAS','ADMINISTRATOR','PIMPINAN TINGGI PRATAMA'];
        if (empty($data['jabatan_type'])) {
            $errors[] = 'Tipe jabatan (jabatan_type) diperlukan';
        } else {
            if (!in_array(strtoupper(trim($data['jabatan_type'])), $allowedJabatanType, true)) {
                $errors[] = 'Tipe jabatan tidak valid';
            }
        }
        return $errors;
    }

    /**
     * Process preview structure (as returned from parseUploadedFile) and upsert employees.
     */
    /**
     * Process preview structure (as returned from parseUploadedFile) and upsert employees.
     * Accepts optional progress callback: function(int $percent)
     */
    public function processPreview(array $preview, callable $progressCallback = null): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $rows = $preview['rows'] ?? [];
        $total = max(1, count($rows));
        $processed = 0;

        foreach ($rows as $row) {
            if (!empty($row['errors'])) {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'errors' => $row['errors']];
                $processed++;
                if ($progressCallback) {
                    $progressCallback((int)floor(($processed / $total) * 100));
                }
                continue;
            }

            $data = $row['data'];
            $nip = $data['nip'] ?? null;
            if (!$nip) {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'errors' => ['NIP kosong']];
                continue;
            }

            // map OPD / OPD unit if provided and validate existence
            $opdId = null;
            $opdUnitId = null;

            // Prefer explicit local opd_id
            if (!empty($data['opd_id'])) {
                $opd = Opd::find($data['opd_id']);
                if ($opd) $opdId = $opd->id;
            }

            // fallback to sso id
            if (empty($opdId) && !empty($data['opd_sso_id']) && Schema::hasTable((new Opd)->getTable())) {
                if (Schema::hasColumn((new Opd)->getTable(), 'sso_id')) {
                    $opd = Opd::where('sso_id', $data['opd_sso_id'])->first();
                    if ($opd) $opdId = $opd->id;
                }
            }

            // fallback to name
            if (empty($opdId) && !empty($data['opd'])) {
                $opd = Opd::where('nama', $data['opd'])->first();
                if ($opd) $opdId = $opd->id;
            }

            // opd_unit mapping: allow null, but if provided ensure existence and matches OPD
            if (!empty($data['opd_unit_id'])) {
                $unit = OpdUnit::find($data['opd_unit_id']);
                if ($unit) $opdUnitId = $unit->id;
            } elseif (!empty($data['opd_unit_sso_id']) && Schema::hasTable((new OpdUnit)->getTable())) {
                if (Schema::hasColumn((new OpdUnit)->getTable(), 'sso_id')) {
                    $unit = OpdUnit::where('sso_id', $data['opd_unit_sso_id'])->first();
                    if ($unit) $opdUnitId = $unit->id;
                }
            } elseif (!empty($data['opd_unit'])) {
                $unit = OpdUnit::where('nama', $data['opd_unit'])->first();
                if ($unit) $opdUnitId = $unit->id;
            }

            // Validate opd existence
            $rowErrors = [];
            if (!$opdId) {
                $rowErrors[] = 'OPD tidak ditemukan';
            }
            // if unit provided but not found or doesn't belong to opd -> error
            if (!empty($data['opd_unit']) || !empty($data['opd_unit_id']) || !empty($data['opd_unit_sso_id'])) {
                if (!$opdUnitId) {
                    $rowErrors[] = 'OPD unit tidak ditemukan';
                } else {
                    $unit = OpdUnit::find($opdUnitId);
                    if ($unit && $unit->opd_id && $opdId && $unit->opd_id != $opdId) {
                        $rowErrors[] = 'OPD unit tidak sesuai dengan OPD';
                    }
                }
            }

            // Validate jabatan_type (already checked presence in validateRow) -- normalize
            $jabatanType = strtoupper(trim($data['jabatan_type'] ?? ''));
            $allowedJabatanType = ['PELAKSANA','FUNGSIONAL','PENGAWAS','ADMINISTRATOR','PIMPINAN TINGGI PRATAMA'];
            if (!in_array($jabatanType, $allowedJabatanType, true)) {
                $rowErrors[] = 'Tipe jabatan tidak valid';
            }

            if (!empty($rowErrors)) {
                $skipped++;
                $errors[] = ['row' => $row['row'], 'errors' => $rowErrors];
                continue;
            }

            $payload = [
                // DB column is `nama` (not `nama_lengkap`)
                'nama' => $data['nama_lengkap'] ?? null,
                'email' => $data['email'] ?? null,
                'alamat' => $data['alamat'] ?? null,
                'no_hp' => $data['no_hp'] ?? null,
                'pangkat' => $data['pangkat'] ?? null,
                'golongan' => $data['golongan'] ?? null,
                'jabatan' => $data['jabatan'] ?? null,
                'jabatan_type' => isset($data['jabatan_type']) ? strtoupper(trim($data['jabatan_type'])) : null,
            ];

            if (!empty($data['tgl_lahir'])) {
                try {
                    // handle Excel serial date values (numeric) as well as normal date strings
                    if (is_numeric($data['tgl_lahir'])) {
                        // use PhpSpreadsheet helper to convert excel serialized date to DateTime
                        try {
                            $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $data['tgl_lahir']);
                            $payload['tgl_lahir'] = $dt->format('Y-m-d');
                        } catch (\Throwable $e) {
                            // fallback to Carbon parse
                            $d = Carbon::parse($data['tgl_lahir']);
                            $payload['tgl_lahir'] = $d->format('Y-m-d');
                        }
                    } else {
                        $d = Carbon::parse($data['tgl_lahir']);
                        $payload['tgl_lahir'] = $d->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    // ignore invalid date here; could be validated earlier
                }
            }

            if ($opdId) $payload['opd_id'] = $opdId;
            if ($opdUnitId) $payload['opd_unit_id'] = $opdUnitId;

            $employee = Employee::where('nip', $nip)->first();
            if ($employee) {
                $employee->update($payload);
                $updated++;
            } else {
                $payload['nip'] = $nip;
                Employee::create($payload);
                $created++;
            }

            $processed++;
            if ($progressCallback) {
                $progressCallback((int)floor(($processed / $total) * 100));
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }
}
