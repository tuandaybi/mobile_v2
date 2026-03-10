<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppUpdateController extends Controller
{
    private const META_PATH = 'app-updates/latest.json';
    private const RELEASES_DIR = 'app-updates/releases';

    public function latest(Request $request): JsonResponse
    {
        $request->validate([
            'current_version' => ['nullable', 'string', 'max:50'],
        ]);

        if (!Storage::disk('public')->exists(self::META_PATH)) {
            return response()->json([
                'message' => 'Chưa có bản cập nhật nào được phát hành.',
            ], 404);
        }

        $payload = json_decode(Storage::disk('public')->get(self::META_PATH), true);

        if (!is_array($payload) || !isset($payload['version'], $payload['file_path'])) {
            return response()->json([
                'message' => 'Metadata bản cập nhật không hợp lệ.',
            ], 500);
        }

        $currentVersion = $request->string('current_version')->toString();
        $hasUpdate = $currentVersion === '' || version_compare($payload['version'], $currentVersion, '>');

        return response()->json([
            'has_update' => $hasUpdate,
            'latest' => [
                'version' => $payload['version'],
                'notes' => $payload['notes'] ?? '',
                'published_at' => $payload['published_at'] ?? null,
                'mandatory' => (bool) ($payload['mandatory'] ?? false),
                'size' => (int) ($payload['size'] ?? 0),
                'sha256' => $payload['sha256'] ?? null,
                'download_url' => route('app-updates.download', [
                    'filename' => basename($payload['file_path']),
                ]),
            ],
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mandatory' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'mimetypes:application/x-msdownload,application/octet-stream', 'max:102400'],
        ]);

        $file = $request->file('file');
        $slugVersion = Str::slug($validated['version']);
        $filename = "app-{$slugVersion}-" . now()->format('YmdHis') . '.exe';
        $path = $file->storeAs(self::RELEASES_DIR, $filename, 'public');

        $fullPath = Storage::disk('public')->path($path);
        $meta = [
            'version' => $validated['version'],
            'notes' => $validated['notes'] ?? '',
            'mandatory' => (bool) ($validated['mandatory'] ?? false),
            'file_path' => $path,
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $fullPath),
            'published_at' => now()->toIso8601String(),
        ];

        Storage::disk('public')->put(self::META_PATH, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'message' => 'Đã phát hành bản cập nhật mới.',
            'release' => $meta,
            'download_url' => route('app-updates.download', ['filename' => basename($path)]),
        ], 201);
    }

    public function download(string $filename)
    {
        $path = self::RELEASES_DIR . '/' . basename($filename);

        abort_unless(Storage::disk('public')->exists($path), 404, 'Không tìm thấy file update.');

        return Storage::disk('public')->download($path, 'app.exe', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }
}
