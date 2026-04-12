<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // reset cache spatie (penting kalau pernah cache)
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            // OPD & User (khusus superadmin)
            'opd.manage',
            'user.manage',
            'user.impersonate',
            'logs.view',

            // Pegawai (admin OPD)
            'employee.create',
            'employee.update',
            'employee.import',
            'employee.deactivate',
            'employee.delete',

            // Verifikasi (verifikator OPD)
            'employee.verify',

            // Produksi QR/nametag
            'qrcode.generate',
            'nametag.generate',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // Roles
        $superadmin   = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $adminOpd     = Role::firstOrCreate(['name' => 'admin-opd', 'guard_name' => 'web']);
        $verifikator  = Role::firstOrCreate(['name' => 'verifikator-opd', 'guard_name' => 'web']);

        // Assign permission per role (superadmin: semua — tetap kita assign untuk awal)
        $superadmin->syncPermissions(Permission::all());

        $adminOpd->syncPermissions([
            'employee.create', 'employee.update', 'employee.import',
            'employee.deactivate', 'employee.delete',
            'qrcode.generate', 'nametag.generate', 'logs.view',
        ]);

        $verifikator->syncPermissions([
            'employee.verify', 'logs.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
