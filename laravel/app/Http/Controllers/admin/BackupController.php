<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Spatie\Backup\Events\DumpingDatabase;
use Spatie\DbDumper\Databases\MySql;

class BackupController extends Controller
{
    private function backupDisk(): string
    {
        // Lấy disk đầu tiên trong config backup (ví dụ: 'backups' hay 'local')
        return config('backup.backup.destination.disks.0', 'local');
    }

    private function backupName(): string
    {
        // Tên thư mục con CHÍNH XÁC Spatie đang dùng (KHÔNG slug)
        // fallback: app.name hoặc 'Laravel'
        return config('backup.backup.name', config('app.name', 'Laravel'));
    }

    public function index()
    {
        try {
            $disk = $this->backupDisk();
            $name = $this->backupName(); // ví dụ: 'Laravel' (giữ hoa/thường)

            // Đảm bảo thư mục tồn tại trên disk (đúng tên)
            if (!Storage::disk($disk)->exists($name)) {
                Storage::disk($disk)->makeDirectory($name);
                Log::info('Tạo thư mục backup (đúng tên): ' . $name);
            }

            // Liệt kê file .zip trong thư mục $name trên đúng disk
            $files = collect(Storage::disk($disk)->files($name))
                ->filter(fn ($path) => Str::of($path)->lower()->endsWith('.zip'))
                ->map(fn ($path) => [
                    'path'      => $path,
                    'fileName'  => basename($path),
                    'sizeBytes' => Storage::disk($disk)->size($path),
                    'mtime'     => Storage::disk($disk)->lastModified($path),
                ])
                ->sortByDesc('mtime')
                ->values()
                ->map(function ($f, $i) {
                    return [
                        'id'        => $i + 1,
                        'fileName'  => $f['fileName'],
                        'size'      => $this->formatSize($f['sizeBytes']),
                        'createdAt' => date('Y-m-d H:i:s', $f['mtime']),
                    ];
                });

            return response()->json(['backups' => $files]);
        } catch (\Exception $e) {
            Log::error('Error fetching backups: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách sao lưu.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function formatSize($size)
    {
        if ($size === 0) return '0 B';
        $units = ['B','KB','MB','GB','TB'];
        $i = (int) floor(log($size, 1024));
        return round($size / (1024 ** $i), 2) . ' ' . $units[$i];
    }

    public function create(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|in:full,db',
            ]);

            // Ép mysqldump tắt SSL cho luồng Web
            Event::forget(DumpingDatabase::class);
            Event::listen(DumpingDatabase::class, function (DumpingDatabase $e) {
                if ($e->dbDumper instanceof MySql) {
                    $e->dbDumper->addExtraOption('--skip-ssl'); // tương thích MariaDB client
                }
            });

            $type   = $request->input('type'); // 'full' | 'db'
            $onlyDb = $type === 'db';

            Artisan::call('backup:run', [
                '--only-db' => $onlyDb,
                // '--verbose' => true, // nếu muốn log chi tiết
            ]);

            return response()->json(['message' => 'Tạo sao lưu thành công']);
        } catch (\Exception $e) {
            Log::error('Error creating backup: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi tạo sao lưu.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function download($id)
    {
        try {
            $disk = $this->backupDisk();
            $name = $this->backupName();

            $files = collect(Storage::disk($disk)->files($name))
                ->filter(fn ($p) => Str::of($p)->lower()->endsWith('.zip'))
                ->sortByDesc(fn ($p) => Storage::disk($disk)->lastModified($p))
                ->values();

            $index = (int) $id - 1;
            if ($index < 0 || $index >= $files->count()) {
                return response()->json(['message' => 'ID sao lưu không hợp lệ'], 404);
            }

            $path = $files[$index];
            if (!Storage::disk($disk)->exists($path)) {
                Log::warning('File sao lưu không tồn tại: ' . $path);
                return response()->json(['message' => 'File sao lưu không tồn tại'], 404);
            }

            // Lấy stream và header
            $fileName = basename($path);
            $stream   = Storage::disk($disk)->readStream($path);

            return response()->streamDownload(function () use ($stream) {
                fpassthru($stream);
            }, $fileName, [
                'Content-Type' => 'application/zip',
            ]);
        } catch (\Exception $e) {
            Log::error('Error downloading backup: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi tải file sao lưu.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $disk = $this->backupDisk();
            $name = $this->backupName();

            $files = collect(Storage::disk($disk)->files($name))
                ->filter(fn ($p) => Str::of($p)->lower()->endsWith('.zip'))
                ->sortByDesc(fn ($p) => Storage::disk($disk)->lastModified($p))
                ->values();

            $index = (int) $id - 1;
            if ($index < 0 || $index >= $files->count()) {
                return response()->json(['message' => 'ID sao lưu không hợp lệ'], 404);
            }

            $path = $files[$index];
            if (!Storage::disk($disk)->exists($path)) {
                Log::warning('File sao lưu không tồn tại: ' . $path);
                return response()->json(['message' => 'File sao lưu không tồn tại'], 404);
            }

            Storage::disk($disk)->delete($path);
            return response()->json(['message' => 'Xóa sao lưu thành công']);
        } catch (\Exception $e) {
            Log::error('Error deleting backup: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi xóa file sao lưu.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
