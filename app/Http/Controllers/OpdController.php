<?php

namespace App\Http\Controllers;

use App\Models\Opd;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class OpdController extends Controller
{
    public function index(Request $request)
    {
        $q         = trim((string) $request->get('q', ''));
        $focusOpd  = (int) $request->get('focus_opd', 0); // agar bisa di-scroll/expand ke OPD tertentu di view

        $opds = Opd::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = "%{$q}%";
                $query->where(function ($w) use ($like) {
                    $w->where('nama', 'like', $like)
                      ->orWhere('slug', 'like', $like)
                      ->orWhere('pimpinan', 'like', $like)
                      ->orWhere('nip', 'like', $like)
                      ->orWhere('pangkat', 'like', $like)
                      ->orWhere('golongan', 'like', $like);
                });
            })
            // === penting: muat unit beserta status, urutkan agar tombol "Ubah/Nonaktifkan" punya target jelas
            ->with(['units' => function ($q) {
                $q->orderBy('nama');                 // daftar unit tampil rapi
            }])
            ->withCount(['units' => function ($q) {
                $q->whereNull('deleted_at');         // badge jumlah unit aktif (soft delete diabaikan)
            }])
            ->orderBy('nama')
            ->paginate(12)
            ->withQueryString();

        return view('opd.index', [
            'opds'      => $opds,
            'q'         => $q,
            'focus_opd' => $focusOpd,               // dipakai view untuk auto-expand kartu OPD terkait
        ]);
    }

    public function create()
    {
        $opd = new Opd;
        return view('opd.create', compact('opd'));
    }

    public function store(Request $request)
    {
        // Validasi + siapkan data (slug unik & upload ttd jika ada)
        [$data, $filePath] = $this->validateAndPrepare($request);

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();
        if ($filePath) {
            $data['ttd_file_path'] = $filePath;
        }

        $opd = Opd::create($data);

        // Buat 2 role default untuk OPD ini
        $this->provisionDefaultRoles($opd);

        return redirect()->route('opd.index', ['focus_opd' => $opd->id])->with('ok', 'OPD berhasil ditambahkan.');
    }

    public function edit(Opd $opd)
    {
        return view('opd.edit', compact('opd'));
    }

    public function update(Request $request, Opd $opd)
    {
        [$data, $filePath] = $this->validateAndPrepare($request, $opd->id);

        $data['updated_by'] = auth()->id();

        if ($filePath) {
            if ($opd->ttd_file_path && is_file(public_path($opd->ttd_file_path))) {
                @unlink(public_path($opd->ttd_file_path));
            }
            $data['ttd_file_path'] = $filePath;
        }

        $opd->update($data);

        return redirect()->route('opd.index', ['focus_opd' => $opd->id])->with('ok', 'OPD berhasil diperbarui.');
    }

    public function destroy(Opd $opd)
    {
        if ($opd->ttd_file_path && is_file(public_path($opd->ttd_file_path))) {
            @unlink(public_path($opd->ttd_file_path));
        }

        $opd->delete();

        return back()->with('ok', 'OPD berhasil dihapus.');
    }

    private function validateAndPrepare(Request $request, $ignoreId = null): array
    {
        $rules = [
            'nama'     => [
                'required', 'string', 'max:255',
                Rule::unique('opds', 'nama')->ignore($ignoreId),
            ],
            'pimpinan' => ['nullable', 'string', 'max:255'],
            'nip'      => ['nullable', 'string', 'max:25'],
            'pangkat'  => ['nullable', 'string', 'max:50'],
            'golongan' => ['nullable', 'string', 'max:10'],
            'alamat'   => ['nullable', 'string', 'max:255'],
            'telepon'  => ['nullable', 'string', 'max:50'],
            'ttd'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:512'],
        ];

        $data = $request->validate($rules);

        // Slug unik otomatis dari nama
        $baseSlug    = Str::slug($data['nama']);
        $data['slug'] = $this->uniqueSlug($baseSlug, $ignoreId);

        // Upload TTD (ke webroot)
        $filePath = null;
        if ($request->hasFile('ttd')) {
            $dir = public_path('uploads/opd');
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $ext      = strtolower($request->file('ttd')->getClientOriginalExtension() ?: 'png');
            $filename = 'ttd-' . $data['slug'] . '-' . time() . '.' . $ext;

            $request->file('ttd')->move($dir, $filename);
            $filePath = 'uploads/opd/' . $filename;
        }

        return [$data, $filePath];
    }

    private function uniqueSlug(string $base, $ignoreId = null): string
    {
        $slug   = $base ?: 'opd';
        $suffix = 2;

        $exists = Opd::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        while ($exists) {
            $candidate = $base . '-' . $suffix++;
            $exists = Opd::where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();

            if (!$exists) {
                $slug = $candidate;
            }
        }

        return $slug;
    }

    private function provisionDefaultRoles(Opd $opd): void
    {
        $guard = 'web';

        Role::firstOrCreate(
            ['name' => 'Admin OPD', 'guard_name' => $guard, 'opd_id' => $opd->id],
            []
        );

        Role::firstOrCreate(
            ['name' => 'Verifikator OPD', 'guard_name' => $guard, 'opd_id' => $opd->id],
            []
        );
    }
}
