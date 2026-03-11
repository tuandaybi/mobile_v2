<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppUpdateController extends Controller
{
    private const ROOT_DIR = 'app-updates';
    private const TRASH_DIR = 'app-updates-trash';
    private const DEFAULT_CHANNEL = 'app';

    public function dashboard(): View
    {
        $this->purgeExpiredTrash();

        return view('app-updates.dashboard', [
            'listUrl' => '/api/admin/app-updates',
            'publishUrl' => '/api/admin/app-updates/publish',
            'trashUrl' => '/api/admin/app-updates/trash',
        ]);
    }

    public function index(): JsonResponse
    {
        $this->purgeExpiredTrash();

        return response()->json([
            'releases' => $this->releaseCollection()->values(),
        ]);
    }

    public function trash(): JsonResponse
    {
        $this->purgeExpiredTrash();

        $trash = collect(Storage::disk('public')->allFiles(self::TRASH_DIR))
            ->filter(fn (string $path) => Str::endsWith($path, '/deleted.json'))
            ->map(function (string $path) {
                $payload = json_decode(Storage::disk('public')->get($path), true);

                if (!is_array($payload) || empty($payload['app_slug']) || empty($payload['channel'])) {
                    return null;
                }

                $appSlug = $payload['app_slug'];
                $channel = $payload['channel'];

                return [
                    'app_slug' => $appSlug,
                    'channel' => $channel,
                    'label' => $appSlug . '/' . $channel,
                    'deleted_at' => $payload['deleted_at'] ?? null,
                    'purge_after' => $payload['purge_after'] ?? null,
                    'restore_url' => route('admin.app-updates.restore', [
                        'appSlug' => $appSlug,
                        'channel' => $channel,
                    ], false),
                ];
            })
            ->filter()
            ->sortByDesc('deleted_at')
            ->values();

        return response()->json([
            'trash' => $trash,
        ]);
    }

    public function restore(string $appSlug, string $channel): JsonResponse
    {
        $this->purgeExpiredTrash();

        $appSlug = $this->sanitizeSegment($appSlug, 'app_slug');
        $channel = $this->sanitizeSegment($channel, 'channel');

        $trashDir = $this->trashDir($appSlug, $channel);
        $targetDir = self::ROOT_DIR . '/' . $appSlug . '/' . $channel;

        abort_unless(Storage::disk('public')->exists($trashDir), 404, 'Khong tim thay release trong thung rac.');
        abort_if(Storage::disk('public')->exists($targetDir), 409, 'Da ton tai ban phat hanh, khong the khoi phuc.');

        File::ensureDirectoryExists(dirname(Storage::disk('public')->path($targetDir)));
        File::moveDirectory(Storage::disk('public')->path($trashDir), Storage::disk('public')->path($targetDir));

        return response()->json([
            'message' => 'Da khoi phuc ban phat hanh.',
        ]);
    }

    public function latest(Request $request, ?string $appSlug = null, ?string $channel = null): JsonResponse
    {
        $request->validate([
            'app_slug' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:100'],
            'current_version' => ['nullable', 'string', 'max:50'],
        ]);

        $appSlug = $this->resolveAppSlug($request, $appSlug);
        $channel = $this->resolveChannel($request, $channel);
        $metaPath = $this->metaPath($appSlug, $channel);

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
            'channel' => $channel,
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
                    'channel' => $channel,
                    'filename' => basename($payload['file_path']),
                ], false),
            ],
        ]);
    }

    public function publish(Request $request): JsonResponse
    {
        $this->purgeExpiredTrash();

        $validated = $request->validate([
            'app_slug' => ['required', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:100'],
            'version' => ['required', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'mandatory' => ['nullable', 'boolean'],
            'file' => ['required', 'file', 'mimes:exe', 'max:204800'],
        ]);

        $appSlug = $this->sanitizeSegment($validated['app_slug'], 'app_slug');
        $channel = $this->sanitizeSegment($validated['channel'] ?? self::DEFAULT_CHANNEL, 'channel');
        $file = $request->file('file');
        $slugVersion = Str::slug($validated['version']);
        $filename = "{$appSlug}-{$channel}-{$slugVersion}-" . now()->format('YmdHis') . '.exe';
        $path = $file->storeAs($this->releasesDir($appSlug, $channel), $filename, 'public');

        $fullPath = Storage::disk('public')->path($path);
        $meta = [
            'app_slug' => $appSlug,
            'channel' => $channel,
            'version' => $validated['version'],
            'notes' => $validated['notes'] ?? '',
            'mandatory' => (bool) ($validated['mandatory'] ?? false),
            'file_path' => $path,
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $fullPath),
            'published_at' => now()->toIso8601String(),
        ];

        Storage::disk('public')->put($this->metaPath($appSlug, $channel), json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'message' => 'Da phat hanh ban cap nhat moi.',
            'release' => $this->normalizeRelease($meta),
            'download_url' => route('app-updates.download', [
                'appSlug' => $appSlug,
                'channel' => $channel,
                'filename' => basename($path),
            ], false),
        ], 201);
    }

    public function destroy(string $appSlug, string $channel): JsonResponse
    {
        $this->purgeExpiredTrash();

        $appSlug = $this->sanitizeSegment($appSlug, 'app_slug');
        $channel = $this->sanitizeSegment($channel, 'channel');
        $channelDir = self::ROOT_DIR . '/' . $appSlug . '/' . $channel;

        abort_unless(Storage::disk('public')->exists($channelDir), 404, 'Khong tim thay release de xoa.');

        $trashDir = $this->trashDir($appSlug, $channel);
        $sourcePath = Storage::disk('public')->path($channelDir);
        $trashPath = Storage::disk('public')->path($trashDir);

        File::ensureDirectoryExists(dirname($trashPath));

        if (File::exists($trashPath)) {
            File::deleteDirectory($trashPath);
        }

        File::moveDirectory($sourcePath, $trashPath);
        Storage::disk('public')->put($trashDir . '/deleted.json', json_encode([
            'app_slug' => $appSlug,
            'channel' => $channel,
            'deleted_at' => now()->toIso8601String(),
            'purge_after' => now()->addDays(30)->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'message' => 'Da dua release vao trash. File se bi xoa vinh vien sau 30 ngay.',
        ]);
    }

    public function download(string $appSlug, string $channel, string $filename)
    {
        $appSlug = $this->sanitizeSegment($appSlug, 'app_slug');
        $channel = $this->sanitizeSegment($channel, 'channel');
        $path = $this->releasesDir($appSlug, $channel) . '/' . basename($filename);

        abort_unless(Storage::disk('public')->exists($path), 404, 'Khong tim thay file update.');

        return Storage::disk('public')->download($path, basename($filename), [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    private function releaseCollection()
    {
        return collect(Storage::disk('public')->allFiles(self::ROOT_DIR))
            ->filter(fn (string $path) => Str::endsWith($path, '/latest.json'))
            ->map(function (string $path) {
                $payload = json_decode(Storage::disk('public')->get($path), true);

                if (!is_array($payload) || !isset($payload['app_slug'], $payload['version'], $payload['file_path'])) {
                    return null;
                }

                return $this->normalizeRelease($payload);
            })
            ->filter()
            ->sortByDesc('published_at');
    }

    private function normalizeRelease(array $payload): array
    {
        $appSlug = $payload['app_slug'];
        $channel = $payload['channel'] ?? self::DEFAULT_CHANNEL;
        $filename = basename($payload['file_path']);

        return [
            'app_slug' => $appSlug,
            'channel' => $channel,
            'version' => $payload['version'],
            'notes' => $payload['notes'] ?? '',
            'mandatory' => (bool) ($payload['mandatory'] ?? false),
            'size' => (int) ($payload['size'] ?? 0),
            'sha256' => $payload['sha256'] ?? null,
            'published_at' => $payload['published_at'] ?? null,
            'file_path' => $payload['file_path'],
            'filename' => $filename,
            'latest_url' => $channel === self::DEFAULT_CHANNEL
                ? route('app-updates.latest.default', ['appSlug' => $appSlug], false)
                : route('app-updates.latest', ['appSlug' => $appSlug, 'channel' => $channel], false),
            'download_url' => route('app-updates.download', [
                'appSlug' => $appSlug,
                'channel' => $channel,
                'filename' => $filename,
            ], false),
            'delete_url' => route('admin.app-updates.destroy', [
                'appSlug' => $appSlug,
                'channel' => $channel,
            ], false),
        ];
    }

    private function resolveAppSlug(Request $request, ?string $appSlug): string
    {
        return $this->sanitizeSegment($appSlug ?: $request->string('app_slug')->toString(), 'app_slug');
    }

    private function resolveChannel(Request $request, ?string $channel): string
    {
        return $this->sanitizeSegment($channel ?: $request->string('channel')->toString(self::DEFAULT_CHANNEL), 'channel');
    }

    private function sanitizeSegment(string $value, string $field): string
    {
        $sanitized = Str::slug($value);

        abort_if($sanitized === '', 422, $field . ' khong hop le.');

        return $sanitized;
    }

    private function metaPath(string $appSlug, string $channel): string
    {
        return self::ROOT_DIR . '/' . $appSlug . '/' . $channel . '/latest.json';
    }

    private function releasesDir(string $appSlug, string $channel): string
    {
        return self::ROOT_DIR . '/' . $appSlug . '/' . $channel . '/releases';
    }

    private function trashDir(string $appSlug, string $channel): string
    {
        return self::TRASH_DIR . '/' . $appSlug . '/' . $channel;
    }

    private function purgeExpiredTrash(): void
    {
        collect(Storage::disk('public')->allFiles(self::TRASH_DIR))
            ->filter(fn (string $path) => Str::endsWith($path, '/deleted.json'))
            ->each(function (string $path) {
                $payload = json_decode(Storage::disk('public')->get($path), true);

                if (!is_array($payload) || empty($payload['purge_after'])) {
                    return;
                }

                if (now()->lt($payload['purge_after'])) {
                    return;
                }

                Storage::disk('public')->deleteDirectory(dirname($path));
            });
    }
}
