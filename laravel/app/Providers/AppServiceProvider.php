<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Spatie\Backup\Events\DumpingDatabase;
use Spatie\DbDumper\Databases\MySql;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(DumpingDatabase::class, function (DumpingDatabase $event) {
            // Chỉ áp dụng cho MySql dumper
            if ($event->dbDumper instanceof MySql) {

                // ✅ Cách “ăn chắc” cho cả MySQL & MariaDB client:
                $event->dbDumper->addExtraOption('--skip-ssl');
            }
        });
    }
}
 