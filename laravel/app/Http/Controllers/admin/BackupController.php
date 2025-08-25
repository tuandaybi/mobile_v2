<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    public function index()
    {
        try {
            $backupPath = storage_path('app/backup/' . Str::slug(env('APP_NAME', 'Laravel')));
            
            // Kiểm tra và tạo thư mục nếu chưa tồn tại
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0755, true);
                Log::info('Tạo thư mục backup: ' . $backupPath);
            }

            $files = collect(File::files($backupPath))
                ->filter(fn ($file) => $file->getExtension() === 'zip')
                ->sortByDesc(fn ($file) => $file->getMTime())
                ->map(function ($file, $index) {
                    return [
                        'id' => $index + 1,
                        'fileName' => $file->getFilename(),
                        'size' => $this->formatSize($file->getSize()),
                        'createdAt' => date('Y-m-d H:i:s', $file->getMTime()),
                    ];
                })->values();

            return response()->json(['backups' => $files]);
        } catch (\Exception $e) {
            Log::error('Error fetching backups: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách sao lưu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function formatSize($size)
    {
        if ($size === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($size, 1024));
        return round($size / pow(1024, $i), 2) . ' ' . $units[$i];
    }

    public function create(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|in:full,db',
            ]);

            // $type = $request->input('type');
            $type = 'db';
            $onlyDb = $type === 'db';

            Artisan::call('backup:run', [
                '--only-db' => $onlyDb,
            ]);

            return response()->json(['message' => 'Tạo sao lưu thành công']);
        } catch (\Exception $e) {
            Log::error('Error creating backup: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi tạo sao lưu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function download($id)
    {
        try {
            $backupPath = storage_path('app/backup/' . Str::slug(env('APP_NAME', 'Laravel')));
            $files = collect(File::files($backupPath))
                ->filter(fn ($file) => $file->getExtension() === 'zip')
                ->sortByDesc(fn ($file) => $file->getMTime())
                ->values();

            $index = (int)$id - 1;
            if ($index < 0 || $index >= $files->count()) {
                return response()->json(['message' => 'ID sao lưu không hợp lệ'], 404);
            }

            $file = $files[$index];
            $filePath = $file->getPathname();
            $fileName = $file->getFilename();

            if (!File::exists($filePath)) {
                Log::warning('File sao lưu không tồn tại: ' . $filePath);
                return response()->json(['message' => 'File sao lưu không tồn tại'], 404);
            }

            return response()->download($filePath, $fileName);
        } catch (\Exception $e) {
            Log::error('Error downloading backup: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi tải file sao lưu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $backupPath = storage_path('app/backup/' . Str::slug(env('APP_NAME', 'Laravel')));
            $files = collect(File::files($backupPath))
                ->filter(fn ($file) => $file->getExtension() === 'zip')
                ->sortByDesc(fn ($file) => $file->getMTime())
                ->values();

            $index = (int)$id - 1;
            if ($index < 0 || $index >= $files->count()) {
                return response()->json(['message' => 'ID sao lưu không hợp lệ'], 404);
            }

            $file = $files[$index];
            $filePath = $file->getPathname();

            if (!File::exists($filePath)) {
                Log::warning('File sao lưu không tồn tại: ' . $filePath);
                return response()->json(['message' => 'File sao lưu không tồn tại'], 404);
            }

            File::delete($filePath);
            return response()->json(['message' => 'Xóa sao lưu thành công']);
        } catch (\Exception $e) {
            Log::error('Error deleting backup: ' . $e->getMessage(), ['exception' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Lỗi khi xóa file sao lưu.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}