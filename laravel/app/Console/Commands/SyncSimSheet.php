<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncSimSheet extends Command
{
    protected $signature = 'sim:sync';
    protected $description = 'Sync SIM data from public Google Sheet and store as JSON';

    public function handle()
    {
        $sheetId = env('GOOGLE_SHEET_ID');
        $sheetName = env('GOOGLE_SHEET_NAME', 'VIP');

        if (!$sheetId) {
            $this->error('❌ GOOGLE_SHEET_ID not set');
            return;
        }

        // Public Google Sheet CSV URL
        $url = "https://docs.google.com/spreadsheets/d/{$sheetId}/gviz/tq?tqx=out:csv&sheet=" . urlencode($sheetName);

        $this->info('📥 Fetching sheet...');

        $csv = @file_get_contents($url);

        if ($csv === false) {
            $this->error('❌ Cannot fetch Google Sheet');
            return;
        }

        $rows = array_map('str_getcsv', explode("\n", $csv));

        if (count($rows) < 2) {
            $this->error('❌ Sheet has no data');
            return;
        }

        // Remove header row
        array_shift($rows);

        $simMap = [];
        $count = 0;

        foreach ($rows as $row) {
            if (count($row) < 4) continue;

            $sim = trim($row[0] ?? '');

            if (!$sim) continue;

            $simMap[$sim] = [
                'hienthi' => trim($row[1] ?? 0),
                'giaban'  => (int) preg_replace('/[^0-9]/', '', $row[2] ?? 0),
                'giathu'  => (int) preg_replace('/[^0-9]/', '', $row[3] ?? 0),
                'mang'    => trim($row[4] ?? ''),
                'ck'      => trim($row[5] ?? ''),
                'th'      => trim($row[6] ?? ''),
                'gop12th' => trim($row[9] ?? ''),
            ];

            $count++;
        }

        $path = storage_path('app/sim_data.json');

        file_put_contents(
            $path,
            json_encode($simMap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        // Clear cache
        Cache::forget('sim_data');

        $this->info("✅ Sync xong: {$count} SIM");
        $this->info("💾 File: storage/app/sim_data.json");
    }
}
