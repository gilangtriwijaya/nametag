<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'superadmin@anambaskab.go.id'],
            [
                'name'     => 'Superadmin',
                'password' => Hash::make('Gi081277624722'),
                'opd_id'   => null,   // superadmin tidak dikunci OPD
                'status'   => 'active' // jika kamu punya kolom status
            ]
        );

        if (! $user->hasRole('superadmin')) {
            $user->assignRole('superadmin');
        }
    }
}
