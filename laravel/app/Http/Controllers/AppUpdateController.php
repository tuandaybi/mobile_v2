<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppUpdateController extends Controller
{
    private const ROOT_DIR = 'app-updates';

    public function latest(Request $request, ?string $appSlug = null): JsonResponse
    {
        $request->validate([
            'app_slug' => ['nullable', 'string', 'max:100'],
            'current_version' => ['nullable', 'string', 'max:50'],
        ]);

        $appSlug = $this->resolveAppSlug($request, $appSlug);
        $metaPath = $this->metaPath($appSlug);

        if (!Storage::disk('public')->exists($metaPath)) {
            return response()->json([
                'message' => 'Chua co ban cap nhat nao duoc phat hanh.',
            ], 404);
        }

        $payload = json_decode(Storage::disk('public')->get($metaPath), true);

        if (!is_array($payload) || !isset($payload['version'], $payload['file_path'])) {
            return response()->json([
                'message' => 'Metadata ban cap nhat khong hop le.',
            ], 500);
        }

        $currentVersion = $request->string('current_version')->toString();
        $hasUpdate = $currentVersion === '' || version_compare($payload['version'], $currentVersion, '>');

        return response()->json([
            'app_slug' => $appSlug,
            'has_update' => $hasUpdate,
            'latest' => [
                'version' => $payload['version'],
                'notes' => $payload['notes'] ?? '',
                'published_at' => $payload['published_at'] ?? null,
                'mandatory' => (bool) ($payload['mandatory'] ?? false),
                'size' => (int) ($payload['size'] ?? 0),
                'sha256' => $payload['sha256'] ?? null,
                'download_url' => route('app-updates.download', [
                    'appSlug' => $appSlug,
                    'filename' => basename($payload['file_path']),
                ]),
            ],
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_slug' => ['required', 'string', 'max:100'],
            'version' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mandatory' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'mimetypes:application/x-msdownload,application/octet-stream', 'max:204800'],
        ]);

        $appSlug = $this->sanitizeAppSlug($validated['app_slug']);
        $file = $request->file('file');
        $slugVersion = Str::slug($validated['version']);
        $filename = "{$appSlug}-{$slugVersion}-" . now()->format('YmdHis') . '.exe';
        $path = $file->storeAs($this->releasesDir($appSlug), $filename, 'public');

        $fullPath = Storage::disk('public')->path($path);
        $meta = [
            'app_slug' => $appSlug,
            'version' => $validated['version'],
            'notes' => $validated['notes'] ?? '',
            'mandatory' => (bool) ($validated['mandatory'] ?? false),
            'file_path' => $path,
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $fullPath),
            'published_at' => now()->toIso8601String(),
        ];

        Storage::disk('public')->put($this->metaPath($appSlug), json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'message' => 'Da phat hanh ban cap nhat moi.',
            'release' => $meta,
            'download_url' => route('app-updates.download', [
                'appSlug' => $appSlug,
                'filename' => basename($path),
            ]),
        ], 201);
    }

    public function download(string $appSlug, string $filename)
    {
        $sanitizedAppSlug = $this->sanitizeAppSlug($appSlug);
        $path = $this->releasesDir($sanitizedAppSlug) . '/' . basename($filename);

        abort_unless(Storage::disk('public')->exists($path), 404, 'Khong tim thay file update.');

        return Storage::disk('public')->download($path, 'app.exe', [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function resolveAppSlug(Request $request, ?string $appSlug): string
    {
        return $this->sanitizeAppSlug($appSlug ?: $request->string('app_slug')->toString());
    }

    private function sanitizeAppSlug(string $appSlug): string
    {
        $sanitized = Str::slug($appSlug);

        abort_if($sanitized === '', 422, 'app_slug khong hop le.');

        return $sanitized;
    }

    private function metaPath(string $appSlug): string
    {
        return self::ROOT_DIR . '/' . $appSlug . '/latest.json';
    }

    private function releasesDir(string $appSlug): string
    {
        return self::ROOT_DIR . '/' . $appSlug . '/releases';
    }
}
