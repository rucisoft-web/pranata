<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Instalasi produksi tidak memuat data apa pun. Data contoh (tiga perusahaan DEMO)
     * hanya dimuat bila PRANATA_SEED_DEMO=true.
     */
    public function run(): void
    {
        if (config('demo.seed')) {
            $this->call(DemoSeeder::class);
        }
    }
}
