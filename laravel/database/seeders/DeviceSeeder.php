<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DeviceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('devices')->insert([
            ['code'=>'6','name'=>'iPhone 6','sort_order'=>1,'is_active'=>0],
            ['code'=>'6S','name'=>'iPhone 6S','sort_order'=>3,'is_active'=>1],
            ['code'=>'6SP','name'=>'iPhone 6S Plus','sort_order'=>4,'is_active'=>1],
            ['code'=>'7','name'=>'iPhone 7','sort_order'=>5,'is_active'=>1],
            ['code'=>'7P','name'=>'iPhone 7 Plus','sort_order'=>6,'is_active'=>1],
            ['code'=>'8','name'=>'iPhone 8','sort_order'=>7,'is_active'=>1],
            ['code'=>'8P','name'=>'iPhone 8 Plus','sort_order'=>8,'is_active'=>1],
            ['code'=>'XR','name'=>'iPhone XR','sort_order'=>9,'is_active'=>1],
            ['code'=>'XS','name'=>'iPhone XS','sort_order'=>10,'is_active'=>1],
            ['code'=>'XSM','name'=>'iPhone XS Max','sort_order'=>-91,'is_active'=>1],
            ['code'=>'11','name'=>'iPhone 11','sort_order'=>12,'is_active'=>1],
            ['code'=>'11PRO','name'=>'iPhone 11 Pro','sort_order'=>-92,'is_active'=>1],
            ['code'=>'11PRM','name'=>'iPhone 11 Pro Max','sort_order'=>-93,'is_active'=>1],
            ['code'=>'12M','name'=>'iPhone 12 Mini','sort_order'=>15,'is_active'=>1],
            ['code'=>'12','name'=>'iPhone 12','sort_order'=>16,'is_active'=>1],
            ['code'=>'12PRO','name'=>'iPhone 12 Pro','sort_order'=>-94,'is_active'=>1],
            ['code'=>'12PRM','name'=>'iPhone 12 Pro Max','sort_order'=>-95,'is_active'=>1],
            ['code'=>'13','name'=>'iPhone 13','sort_order'=>19,'is_active'=>1],
            ['code'=>'13PRO','name'=>'iPhone 13 Pro','sort_order'=>-96,'is_active'=>1],
            ['code'=>'13PRM','name'=>'iPhone 13 Pro Max','sort_order'=>-97,'is_active'=>1],
            ['code'=>'WATCH','name'=>'Apple Watch','sort_order'=>22,'is_active'=>1],
            ['code'=>'AIRPOD','name'=>'AirPod','sort_order'=>23,'is_active'=>1],
            ['code'=>'AIRTAG','name'=>'AirTag','sort_order'=>24,'is_active'=>1],
            ['code'=>'SE','name'=>'iPhone SE','sort_order'=>25,'is_active'=>1],
            ['code'=>'SE2','name'=>'iPhone SE 2','sort_order'=>26,'is_active'=>1],
            ['code'=>'IPAD','name'=>'iPad','sort_order'=>27,'is_active'=>1],
            ['code'=>'6P','name'=>'iPhone 6 Plus','sort_order'=>2,'is_active'=>0],
            ['code'=>'SamSung','name'=>'Sam Sung','sort_order'=>28,'is_active'=>1],
            ['code'=>'X','name'=>'iPhone X','sort_order'=>9,'is_active'=>1],
            ['code'=>'Laptop','name'=>'Laptop','sort_order'=>26,'is_active'=>1],
            ['code'=>'Android','name'=>'Android','sort_order'=>27,'is_active'=>1],
            ['code'=>'SE3','name'=>'iPhone SE 3','sort_order'=>27,'is_active'=>1],
            ['code'=>'14','name'=>'iPhone 14','sort_order'=>-1,'is_active'=>1],
            ['code'=>'14PLUS','name'=>'iPhone 14 Plus','sort_order'=>18,'is_active'=>1],
            ['code'=>'14PRO','name'=>'iPhone 14 Pro','sort_order'=>-98,'is_active'=>1],
            ['code'=>'14PRM','name'=>'iPhone 14 Pro Max','sort_order'=>-99,'is_active'=>1],
            ['code'=>'15','name'=>'iPhone 15','sort_order'=>-100,'is_active'=>1],
            ['code'=>'15PRO','name'=>'iPhone 15 Pro','sort_order'=>-101,'is_active'=>1],
            ['code'=>'15PRM','name'=>'iPhone 15 Pro Max','sort_order'=>-102,'is_active'=>1],
            ['code'=>'15PLUS','name'=>'iPhone 15 Plus','sort_order'=>-100,'is_active'=>1],
            ['code'=>'13MINI','name'=>'iPhone 13 Mini','sort_order'=>-90,'is_active'=>1],
            ['code'=>'5SE','name'=>'iPhone 5 SE','sort_order'=>60,'is_active'=>0],
            ['code'=>'16','name'=>'iPhone 16','sort_order'=>-100,'is_active'=>1],
            ['code'=>'16PLUS','name'=>'iPhone 16 Plus','sort_order'=>-101,'is_active'=>1],
            ['code'=>'16PRO','name'=>'iPhone 16 Pro','sort_order'=>-102,'is_active'=>1],
            ['code'=>'16PRM','name'=>'iPhone 16 Pro Max','sort_order'=>-103,'is_active'=>1],
        ]);
    }
}
