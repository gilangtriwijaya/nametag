<?php

namespace App\Repositories;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repository tipis untuk model Employee.
 *
 * - Semua urusan scoping akses & filter pencarian ditangani oleh EmployeeQueryService.
 * - Repository fokus ke CRUD dan helper query dasar saja.
 */
class EmployeeRepository
{
    /**
     * Buat query builder dasar Employee.
     *
     * Dipakai kalau service lain ingin merangkai query kustom.
     */
    public function newQuery(): Builder
    {
        return Employee::query();
    }

    /**
     * Paginasi sederhana dari query yang sudah disiapkan di luar.
     *
     * Biasanya dipakai lewat EmployeeQueryService.
     */
    public function paginateFromQuery(Builder $query, int $perPage = 20): LengthAwarePaginator
    {
        return $query
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Membuat pegawai baru.
     *
     * Validasi & normalisasi sudah dilakukan di FormRequest + Service.
     * Di sini cukup mass-assign sesuai $fillable pada model Employee.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    /**
     * Mengupdate data pegawai.
     *
     * @param  \App\Models\Employee  $employee
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);

        // Kembalikan instance yang sudah fresh dari DB
        return $employee->refresh();
    }

    /**
     * Menghapus pegawai.
     *
     * Soft delete / hard delete mengikuti pengaturan di model Employee.
     */
    public function delete(Employee $employee): void
    {
        $employee->delete();
    }

    /**
     * Cari pegawai berdasarkan ID.
     */
    public function find(int $id): ?Employee
    {
        return Employee::find($id);
    }
}
