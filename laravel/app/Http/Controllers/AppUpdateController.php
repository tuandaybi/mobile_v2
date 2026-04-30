<?php

namespace App\Http\Controllers;

use App\Notifications\TelegramNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AppUpdateController extends Controller
{
    private const ROOT_DIR = 'app-updates';
    private const TRASH_DIR = 'app-updates-trash';
    private const DEFAULT_CHANNEL = 'app';
    private const FILES_DIR = 'files';
    private const META_DIR  = 'files-meta';

    public function dashboard(): View
    {
        return $this->downloadPage();
    }

    public function index(): JsonResponse
    {
        $this->purgeExpiredTrash();

        return response()->json([
            'releases' => $this->releaseCollection()->values(),
        ]);
    }

    public function downloadPage(): View
    {
        $all     = $this->fileCollection()->values();
        $perPage = 20;
        $page    = (int) request()->input('page', 1);

        $paginator = new LengthAwarePaginator(
            $all->forPage($page, $perPage),
            $all->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('app-updates.downloads', [
            'files'              => $paginator,
            'uploadUrl'          => route('file.upload', [], false),
            'requestUploadOtpUrl'=> route('file.request-upload-otp', [], false),
            'deleteWithOtpUrl'   => route('file.delete', [], false),
        ]);
    }

    public function fileUpload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp_protected' => ['nullable', 'boolean'],
            'otp'           => ['nullable', 'digits:6'],
            'file'          => ['required', 'file', 'max:512000'],
        ]);

        $otpProtected = (bool) ($validated['otp_protected'] ?? true);

        $cached = Cache::get($this->fileUploadOtpKey($request->ip()));
        if (!is_array($cached) || ($cached['otp'] ?? '') !== ($validated['otp'] ?? '')) {
            return response()->json(['message' => 'OTP upload không đúng hoặc đã hết hạn.'], 422);
        }
        Cache::forget($this->fileUploadOtpKey($request->ip()));

        $file     = $request->file('file');
        $filename = $file->getClientOriginalName() ?: ('upload-' . now()->format('YmdHis'));
        $path     = $file->storeAs(self::FILES_DIR, $filename, 'public');
        $fullPath = Storage::disk('public')->path($path);

        Storage::disk('public')->put(
            self::META_DIR . '/' . $filename . '.json',
            json_encode([
                'filename'      => $filename,
                'otp_protected' => $otpProtected,
                'size'          => $file->getSize(),
                'sha256'        => hash_file('sha256', $fullPath),
                'published_at'  => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return response()->json(['message' => 'Upload thành công.'], 201);
    }

    public function fileRequestUploadOtp(Request $request): JsonResponse
    {
        $cooldownKey = 'cd_upload:' . sha1($request->ip());
        if (Cache::has($cooldownKey)) {
            return response()->json(['message' => 'Vui lòng đợi 1 phút trước khi gửi lại OTP.'], 429);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($this->fileUploadOtpKey($request->ip()), ['otp' => $otp], now()->addMinutes(5));
        Cache::put($cooldownKey, true, now()->addSeconds(60));
        TelegramNotification::send("OTP upload file: {$otp}\nIP: " . $request->ip() . "\nHiệu lực: 5 phút");

        return response()->json(['message' => 'Đã gửi OTP upload qua Telegram. Mã có hiệu lực 5 phút.']);
    }

    public function fileRequestDownloadOtp(Request $request): JsonResponse
    {
        $filename = basename($request->validate(['filename' => ['required', 'string', 'max:255']])['filename']);
        abort_unless(Storage::disk('public')->exists(self::FILES_DIR . '/' . $filename), 404, 'Không tìm thấy file.');

        $meta = $this->fileMeta($filename);
        if (!($meta['otp_protected'] ?? true)) {
            return response()->json(['message' => 'File này không yêu cầu OTP.']);
        }

        $cooldownKey = 'cd_download:' . sha1($request->ip() . '|' . $filename);
        if (Cache::has($cooldownKey)) {
            return response()->json(['message' => 'Vui lòng đợi 1 phút trước khi gửi lại OTP.'], 429);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put($this->fileDownloadOtpKey($request->ip(), $filename), ['otp' => $otp], now()->addMinutes(5));
        Cache::put($cooldownKey, true, now()->addSeconds(60));
        TelegramNotification::send("OTP tải file: {$otp}\nFile: {$filename}\nIP: " . $request->ip() . "\nHiệu lực: 5 phút");

        return response()->json(['message' => 'Đã gửi OTP qua Telegram. Mã có hiệu lực 5 phút.']);
    }

    public function fileVerifyDownloadOtp(Request $request)
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'otp'      => ['nullable', 'digits:6'],
        ]);
        $filename = basename($validated['filename']);
        $path     = self::FILES_DIR . '/' . $filename;
        abort_unless(Storage::disk('public')->exists($path), 404, 'Không tìm thấy file.');

        $meta = $this->fileMeta($filename);
        if ($meta['otp_protected'] ?? true) {
            $key    = $this->fileDownloadOtpKey($request->ip(), $filename);
            $cached = Cache::get($key);
            if (!is_array($cached) || ($cached['otp'] ?? '') !== ($validated['otp'] ?? '')) {
                return back()->withInput()->withErrors(['otp' => 'OTP không đúng hoặc đã hết hạn.']);
            }
            Cache::forget($key);
        }

        $this->appendDownloadLog($request->ip(), $filename, '-', '-');
        return Storage::disk('public')->download($path, $filename, ['Content-Type' => 'application/octet-stream']);
    }

    public function fileDeleteWithOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'otp'      => ['nullable', 'digits:6'],
        ]);
        $filename = basename($validated['filename']);
        $path     = self::FILES_DIR . '/' . $filename;
        abort_unless(Storage::disk('public')->exists($path), 404, 'Không tìm thấy file.');

        $key    = $this->fileDownloadOtpKey($request->ip(), $filename);
        $cached = Cache::get($key);
        if (!is_array($cached) || ($cached['otp'] ?? '') !== ($validated['otp'] ?? '')) {
            return response()->json(['message' => 'OTP không đúng hoặc đã hết hạn.'], 422);
        }
        Cache::forget($key);

        Storage::disk('public')->delete($path);
        Storage::disk('public')->delete(self::META_DIR . '/' . $filename . '.json');

        return response()->json(['message' => 'Đã xóa file.']);
    }

    private function fileCollection(): \Illuminate\Support\Collection
    {
        return collect(Storage::disk('public')->files(self::FILES_DIR))
            ->map(function (string $path) {
                $filename = basename($path);
                $meta     = $this->fileMeta($filename);
                return [
                    'filename'      => $filename,
                    'size'          => Storage::disk('public')->size($path),
                    'otp_protected' => (bool) ($meta['otp_protected'] ?? true),
                    'published_at'  => $meta['published_at'] ?? Carbon::createFromTimestamp(
                        Storage::disk('public')->lastModified($path)
                    )->toIso8601String(),
                    'file_path'     => $path,
                ];
            })
            ->filter()
            ->sortByDesc('published_at');
    }

    private function fileMeta(string $filename): array
    {
        $metaPath = self::META_DIR . '/' . $filename . '.json';
        if (!Storage::disk('public')->exists($metaPath)) return [];
        return json_decode(Storage::disk('public')->get($metaPath), true) ?? [];
    }

    private function fileUploadOtpKey(string $ip): string
    {
        return 'file_upload_otp:' . sha1($ip);
    }

    private function fileDownloadOtpKey(string $ip, string $filename): string
    {
        return 'file_download_otp:' . sha1($ip . '|' . $filename);
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
                'download_url' => $this->downloadEntryUrl($appSlug, $channel, basename($payload['file_path'])),
            ],
        ]);
    }

    public function requestUploadOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_slug' => ['required', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:100'],
            'version' => ['required', 'string', 'max:50'],
        ]);

        $appSlug = $this->sanitizeSegment($validated['app_slug'], 'app_slug');
        $channel = $this->sanitizeSegment($validated['channel'] ?? self::DEFAULT_CHANNEL, 'channel');
        $version = trim($validated['version']);
        $otp = (string) random_int(100000, 999999);
        $key = $this->uploadOtpCacheKey($request->ip(), $appSlug, $channel, $version);

        Cache::put($key, [
            'otp' => $otp,
            'app_slug' => $appSlug,
            'channel' => $channel,
            'version' => $version,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(5));

        TelegramNotification::send("OTP upload file: {$otp}\nApp: {$appSlug}/{$channel}\nVersion: {$version}\nIP: " . $request->ip() . "\nHiệu lực: 5 phút");

        return response()->json([
            'message' => 'Da gui OTP upload qua Telegram. Ma co hieu luc trong 5 phut.',
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
            'otp_protected' => ['nullable', 'boolean'],
            'otp' => ['required_if:otp_protected,1', 'nullable', 'digits:6'],
            // max in kilobytes → 500 MB, cho phép mọi loại tệp
            'file' => ['required', 'file', 'max:512000'],
        ]);

        $appSlug = $this->sanitizeSegment($validated['app_slug'], 'app_slug');
        $channel = $this->sanitizeSegment($validated['channel'] ?? self::DEFAULT_CHANNEL, 'channel');
        $version = trim($validated['version']);
        $otpProtected = (bool) ($validated['otp_protected'] ?? true);

        if ($otpProtected) {
            $otpKey = $this->uploadOtpCacheKey($request->ip(), $appSlug, $channel, $version);
            $cachedOtp = Cache::get($otpKey);

            if (!is_array($cachedOtp) || !isset($cachedOtp['otp']) || $cachedOtp['otp'] !== ($validated['otp'] ?? null)) {
                return response()->json([
                    'message' => 'OTP upload khong dung hoac da het han.',
                ], 422);
            }

            Cache::forget($otpKey);
        }

                $file = $request->file('file');
        $filename = $file->getClientOriginalName() ?: ('upload-' . now()->format('YmdHis'));
        $path = $file->storeAs($this->releasesDir($appSlug, $channel), $filename, 'public');

        $fullPath = Storage::disk('public')->path($path);
        $meta = [
            'app_slug' => $appSlug,
            'channel' => $channel,
            'version' => $version,
            'notes' => $validated['notes'] ?? '',
            'mandatory' => (bool) ($validated['mandatory'] ?? false),
            'otp_protected' => $otpProtected,
            'file_path' => $path,
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $fullPath),
            'published_at' => now()->toIso8601String(),
        ];

        Storage::disk('public')->put($this->metaPath($appSlug, $channel), json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return response()->json([
            'message' => 'Da phat hanh ban cap nhat moi.',
            'release' => $this->normalizeRelease($meta),
            'download_url' => $this->downloadEntryUrl($appSlug, $channel, basename($path)),
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

    public function requestDownloadOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_slug' => ['required', 'string', 'max:100'],
            'channel' => ['required', 'string', 'max:100'],
            'filename' => ['required', 'string', 'max:255'],
        ]);

        $appSlug = $this->sanitizeSegment($validated['app_slug'], 'app_slug');
        $channel = $this->sanitizeSegment($validated['channel'], 'channel');
        $filename = basename($validated['filename']);
        $path = $this->releasesDir($appSlug, $channel) . '/' . $filename;

        abort_unless(Storage::disk('public')->exists($path), 404, 'Khong tim thay file.');

        if (!$this->releaseRequiresOtp($appSlug, $channel, $filename)) {
            return response()->json([
                'message' => 'File nay khong yeu cau OTP.',
            ]);
        }

        $otp = (string) random_int(100000, 999999);
        $key = $this->downloadOtpCacheKey($request->ip(), $appSlug, $channel, $filename);

        Cache::put($key, [
            'otp' => $otp,
            'app_slug' => $appSlug,
            'channel' => $channel,
            'filename' => $filename,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(5));

        TelegramNotification::send("OTP tải file: {$otp}\nFile: {$filename}\nApp: {$appSlug}/{$channel}\nIP: " . $request->ip() . "\nHiệu lực: 5 phút");

        return response()->json([
            'message' => 'Da gui OTP qua Telegram. Ma co hieu luc trong 5 phut.',
        ]);
    }

    public function verifyDownloadOtp(Request $request)
    {
        $validated = $request->validate([
            'app_slug' => ['required', 'string', 'max:100'],
            'channel' => ['required', 'string', 'max:100'],
            'filename' => ['required', 'string', 'max:255'],
            'otp' => ['nullable', 'digits:6'],
        ]);

        $appSlug = $this->sanitizeSegment($validated['app_slug'], 'app_slug');
        $channel = $this->sanitizeSegment($validated['channel'], 'channel');
        $filename = basename($validated['filename']);
        $path = $this->releasesDir($appSlug, $channel) . '/' . $filename;

        abort_unless(Storage::disk('public')->exists($path), 404, 'Khong tim thay file.');

        if ($this->releaseRequiresOtp($appSlug, $channel, $filename)) {
            $isValid = $this->consumeDownloadOtp($request->ip(), $appSlug, $channel, $filename, (string) ($validated['otp'] ?? ''));

            if (!$isValid) {
                return back()->withInput()->withErrors([
                    'otp' => 'OTP khong dung hoac da het han.',
                ]);
            }
        }

        $this->appendDownloadLog($request->ip(), $filename, $appSlug, $channel);

        return Storage::disk('public')->download($path, $filename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    public function deleteWithOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_slug' => ['required', 'string', 'max:100'],
            'channel' => ['required', 'string', 'max:100'],
            'filename' => ['required', 'string', 'max:255'],
            'otp' => ['nullable', 'digits:6'],
        ]);

        $appSlug = $this->sanitizeSegment($validated['app_slug'], 'app_slug');
        $channel = $this->sanitizeSegment($validated['channel'], 'channel');
        $filename = basename($validated['filename']);
        $path = $this->releasesDir($appSlug, $channel) . '/' . $filename;

        abort_unless(Storage::disk('public')->exists($path), 404, 'Khong tim thay file.');

            $isValid = $this->consumeDownloadOtp($request->ip(), $appSlug, $channel, $filename, (string) ($validated['otp'] ?? ''));

        if (!$isValid) {
            return response()->json([
                'message' => 'OTP khong dung hoac da het han.',
            ], 422);
        }


        Storage::disk('public')->delete($path);

        $metaPath = $this->metaPath($appSlug, $channel);
        if (Storage::disk('public')->exists($metaPath)) {
            $payload = json_decode(Storage::disk('public')->get($metaPath), true);
            if (is_array($payload) && basename($payload['file_path'] ?? '') === $filename) {
                Storage::disk('public')->delete($metaPath);
            }
        }

        return response()->json([
            'message' => 'Da xoa file.',
        ]);
    }

    private function releaseCollection(): \Illuminate\Support\Collection
    {
        // Build a map of latest.json metadata keyed by "app_slug|channel"
        $latestMeta = collect(Storage::disk('public')->allFiles(self::ROOT_DIR))
            ->filter(fn (string $p) => Str::endsWith($p, '/latest.json'))
            ->mapWithKeys(function (string $p) {
                $payload = json_decode(Storage::disk('public')->get($p), true);
                if (!is_array($payload) || empty($payload['app_slug'])) {
                    return [];
                }
                $key = $payload['app_slug'] . '|' . ($payload['channel'] ?? self::DEFAULT_CHANNEL);
                return [$key => $payload];
            });

        // Enumerate every actual file inside releases/ directories
        return collect(Storage::disk('public')->allFiles(self::ROOT_DIR))
            ->filter(function (string $path) {
                // app-updates/{slug}/{channel}/releases/{file}
                return str_contains($path, '/releases/') && !Str::endsWith($path, '.json');
            })
            ->map(function (string $path) use ($latestMeta) {
                $segments = explode('/', $path);
                if (count($segments) < 5) return null;

                $appSlug  = $segments[1];
                $channel  = $segments[2];
                $filename = basename($path);
                $key      = $appSlug . '|' . $channel;
                $meta     = $latestMeta->get($key, []);
                $isLatest = is_array($meta) && basename($meta['file_path'] ?? '') === $filename;

                return [
                    'app_slug'     => $appSlug,
                    'channel'      => $channel,
                    'filename'     => $filename,
                    'size'         => Storage::disk('public')->size($path),
                    'otp_protected'=> $isLatest ? (bool) ($meta['otp_protected'] ?? true) : true,
                    'published_at' => $isLatest
                        ? ($meta['published_at'] ?? null)
                        : Carbon::createFromTimestamp(Storage::disk('public')->lastModified($path))->toIso8601String(),
                    'version'      => $isLatest ? ($meta['version'] ?? null) : null,
                    'file_path'    => $path,
                    'download_url' => $this->downloadEntryUrl($appSlug, $channel, $filename),
                ];
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
            'otp_protected' => (bool) ($payload['otp_protected'] ?? true),
            'size' => (int) ($payload['size'] ?? 0),
            'sha256' => $payload['sha256'] ?? null,
            'published_at' => $payload['published_at'] ?? null,
            'file_path' => $payload['file_path'],
            'filename' => $filename,
            'latest_url' => route('app-updates.latest', [
                'appSlug' => $appSlug,
                'channel' => $channel,
            ], false),
            'legacy_latest_url' => route('app-updates.latest.default', ['appSlug' => $appSlug], false),
            'download_url' => $this->downloadEntryUrl($appSlug, $channel, $filename),
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

    private function downloadEntryUrl(string $appSlug, string $channel, string $filename): string
    {
        return route('app-updates.dashboard', [
            'app_slug' => $appSlug,
            'channel' => $channel,
            'filename' => $filename,
        ], false) . '#downloads';
    }

    private function uploadOtpCacheKey(string $ip, string $appSlug, string $channel, string $version): string
    {
        return 'upload_otp:' . sha1($ip . '|' . $appSlug . '|' . $channel . '|' . $version);
    }

    private function downloadOtpCacheKey(string $ip, string $appSlug, string $channel, string $filename): string
    {
        return 'download_otp:' . sha1($ip . '|' . $appSlug . '|' . $channel . '|' . $filename);
    }

    private function releaseRequiresOtp(string $appSlug, string $channel, string $filename): bool
    {
        $metaPath = $this->metaPath($appSlug, $channel);

        if (!Storage::disk('public')->exists($metaPath)) {
            return true;
        }

        $payload = json_decode(Storage::disk('public')->get($metaPath), true);

        if (!is_array($payload) || basename($payload['file_path'] ?? '') !== $filename) {
            return true;
        }

        return (bool) ($payload['otp_protected'] ?? true);
    }

    private function consumeDownloadOtp(string $ip, string $appSlug, string $channel, string $filename, string $otp): bool
    {
        $key = $this->downloadOtpCacheKey($ip, $appSlug, $channel, $filename);
        $cached = Cache::get($key);

        if (!is_array($cached) || !isset($cached['otp']) || $cached['otp'] !== $otp) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    private function appendDownloadLog(string $ip, string $filename, string $appSlug, string $channel): void
    {
        $line = sprintf(
            "[%s] IP: %s | app: %s | channel: %s | file: %s%s",
            now()->format('Y-m-d H:i:s'),
            $ip,
            $appSlug,
            $channel,
            $filename,
            PHP_EOL
        );

        File::append(storage_path('logs/download.log'), $line);
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
