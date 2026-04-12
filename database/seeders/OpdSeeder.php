<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Opd;

class OpdSeeder extends Seeder
{
    public function run(): void
    {
        Opd::updateOrCreate(
            ['name' => 'Sekretariat Daerah (Setda)'],
            [
                'leader_name' => 'Sahtiar, SH, MM',
                'address'     => 'Jl. Raja Haji Fisabilillah No.1, Pasir Peti, Tarempa',
                // 'signature_path' => null, // nanti saat upload TTD
            ]
        );
    }
}
