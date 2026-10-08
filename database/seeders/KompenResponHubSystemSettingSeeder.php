<?php

namespace Database\Seeders;

use App\Models\KompenResponHubSystemSetting;
use Illuminate\Database\Seeder;

class KompenResponHubSystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        KompenResponHubSystemSetting::current();
    }
}
