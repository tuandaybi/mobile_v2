<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceStorageSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('storages')->insert([
            ['name'=>'16GB','size_gb'=>16],
            ['name'=>'32GB','size_gb'=>32],
            ['name'=>'64GB','size_gb'=>64],
            ['name'=>'128GB','size_gb'=>128],
            ['name'=>'256GB','size_gb'=>256],
            ['name'=>'512GB','size_gb'=>512],
            ['name'=>'1TB','size_gb'=>1024],
        ]);
    }
}
