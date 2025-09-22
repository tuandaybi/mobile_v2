<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationComment;
use App\Http\Controllers\Concerns\ResolvesStore;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InboxController extends Controller
{
    use ResolvesStore;
    
    public function index(Request $r)
    {
        
        $storeId = $this->resolveStoreId($r);
        $userId  = $r->user()->id;

        $this->ensureBackupReminderDaily($r); // Kiểm tra và tạo nhắc nhở sao lưu nếu cần 
        $this->reminderExpiryDate($userId); // Kiểm tra và tạo nhắc nhở gia hạn nếu cần

        $perPage = max(1, min((int)$r->query('perPage', 20), 100));
        $q       = trim((string) $r->query('q', ''));
        $unread  = $r->boolean('unread');
        $type    = trim((string) $r->query('type', ''));

        $base = Notification::query()
            // chỉ lấy notifications mà user này là recipient
            ->join('notification_recipients as nr', function ($j) use ($userId) {
                $j->on('nr.notification_id', '=', 'notifications.id')
                ->where('nr.user_id', '=', $userId);
            })
            // bắt buộc select notifications.* để Eloquent trả về model đúng
            ->select('notifications.*', 'nr.read_at as read_at')
            ->with(['creator:id,name', 'store:id,name'])
            ->where(function ($w) use ($storeId) {
                $w->whereNull('store_id')->orWhere('store_id', $storeId);
            })
            ->when($type !== '', fn($w) => $w->where('type', $type))
            ->when($q !== '', function ($w) use ($q) {
                $like = "%{$q}%";
                $w->where(function ($x) use ($like) {
                    $x->where('title', 'like', $like)
                    ->orWhere('body',  'like', $like);
                });
            })
            // lọc chưa đọc nếu cần
            ->when($unread, fn($w) => $w->whereNull('nr.read_at'))
            // ưu tiên chưa đọc trước (tuỳ chọn)
            ->orderByRaw('CASE WHEN nr.read_at IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc('notifications.id');

        $page = $base->paginate($perPage);

        // map thêm is_read cho FE
        $items = collect($page->items())->map(function ($n) {
            $arr = $n->toArray();
            $arr['is_read'] = !empty($arr['read_at']);
            return $arr;
        })->all();

        // Tổng chưa đọc/đã đọc cho badge tổng
        $unreadCountTotal = NotificationRecipient::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        $readCountTotal = NotificationRecipient::where('user_id', $userId)
            ->whereNotNull('read_at')
            ->count();

        // (tuỳ chọn) số liệu theo bộ lọc hiện tại
        $filteredQuery  = clone $base;
        $filteredTotal  = (clone $filteredQuery)->count();
        $filteredUnread = (clone $filteredQuery)->whereNull('nr.read_at')->count();
        $filteredRead   = $filteredTotal - $filteredUnread;

        return response()->json([
            'items' => $items,
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'unread_count' => $unreadCountTotal, // badge tổng
                'read_count'   => $readCountTotal,
                'filtered'     => [
                    'total'  => $filteredTotal,
                    'unread' => $filteredUnread,
                    'read'   => $filteredRead,
                ],
            ],
        ]);
    }



    public function store(Request $r)
    {
        $r->validate([
            'type'          => 'nullable|string|max:32',
            'title'         => 'nullable|string|max:255',
            'body'          => 'required|string',
            'priority'      => 'nullable|string|in:low,normal,high',
            'store_id'      => 'nullable|integer', // null = tạo cho tất cả store
            'ref_type'      => 'nullable|string|max:32',
            'ref_id'        => 'nullable|integer',
            'recipient_ids' => 'nullable|array',   // [user_id,...] - nếu bỏ trống sẽ gửi “tất cả user trong (các) store”
        ]);

        $creator = $r->user();
        $now     = now();

        // Xác định danh sách store cần tạo thông báo
        $storeIds = $r->filled('store_id')
            ? [(int)$r->input('store_id')]
            : DB::table('stores')->pluck('id')->map(fn($x)=>(int)$x)->all(); // tất cả store

        // Chuẩn hoá danh sách recipients (nếu client truyền)
        $explicitRecipientIds = array_values(array_unique(
            array_map('intval', (array) $r->input('recipient_ids', []))
        ));

        $createdIds = [];

        DB::beginTransaction();
        try {
            foreach ($storeIds as $sid) {
                // 1) Tạo notification cho từng store
                $notiId = DB::table('notifications')->insertGetId([
                    'store_id'   => $sid, // nếu bạn muốn global= null thay vì per-store, đổi logic phía trên
                    'created_by' => $creator->id, // <- kiểm tra: cột của bạn là created_by hay creator_id?
                    'type'       => $r->input('type', 'log'),
                    'title'      => $r->input('title'),
                    'body'       => $r->input('body'),
                    'priority'   => $r->input('priority', 'normal'),
                    'ref_type'   => $r->input('ref_type'),
                    'ref_id'     => $r->input('ref_id'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $createdIds[] = $notiId;

                // 2) Xác định recipients cho notification này
                $targetUserIds = $explicitRecipientIds;

                if (empty($targetUserIds)) {
                    // Không truyền recipient_ids -> lấy tất cả user thuộc store này
                    $targetUserIds = DB::table('user_in_store')
                        ->where('store_id', $sid)
                        ->pluck('user_id')
                        ->map(fn($x)=>(int)$x)
                        ->unique()
                        ->values()
                        ->all();
                }

                if (!empty($targetUserIds)) {
                    // 3) Gán recipients
                    $rows = [];
                    foreach (array_unique($targetUserIds) as $uid) {
                        $rows[] = [
                            'notification_id' => $notiId,
                            'user_id'         => (int) $uid,
                            'created_at'      => $now,
                            'updated_at'      => $now,
                        ];
                    }
                    // chèn theo lô
                    foreach (array_chunk($rows, 1000) as $chunk) {
                        DB::table('notification_recipients')->insert($chunk);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message'           => 'Created',
                'notification_ids'  => $createdIds,
                'count'             => count($createdIds),
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('POST /inbox failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Tạo thông báo thất bại',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $r, $id)
    {
        $userId = $r->user()->id;

        $noti = Notification::with([
                'creator:id,name',
                'store:id,name',
                'comments' => fn($q) => $q->with('user:id,name')->latest()->limit(100),
            ])
            ->whereHas('recipients', fn($w) => $w->where('user_id', $userId))
            ->findOrFail($id);

        return response()->json($noti);
    }

    public function markRead(Request $r, $id)
    {
        NotificationRecipient::where('notification_id', $id)
            ->where('user_id', $r->user()->id)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    // Optional: Đánh dấu đã đọc tất cả
    public function readAll(Request $r)
    {
        NotificationRecipient::where('user_id', $r->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function comment(Request $r, $id)
    {
        $r->validate(['body' => 'required|string']);

        // Chỉ recipient mới được bình luận
        $ok = NotificationRecipient::where('notification_id', $id)
            ->where('user_id', $r->user()->id)
            ->exists();

        abort_unless($ok, 403, 'Bạn không có quyền bình luận thông báo này.');

        $cmt = NotificationComment::create([
            'notification_id' => $id,
            'user_id'         => $r->user()->id,
            'body'            => $r->input('body'),
        ]);

        return response()->json($cmt, 201);
    }

    public function destroyRead(Request $r)
    {
        $user = $r->user('sanctum') ?? $r->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Lấy danh sách notification_id mà user này đã đọc
        $readIds = \DB::table('notification_recipients')
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->pluck('notification_id')
            ->all();

        if (empty($readIds)) {
            return response()->json(['message' => 'Không có thông báo đã đọc để xóa', 'deleted' => 0]);
        }

        // Xóa recipient record của user
        $deleted = \DB::table('notification_recipients')
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->delete();

        // Với mỗi notification_id, nếu không còn recipient nào thì xóa luôn notification + comment
        foreach ($readIds as $nid) {
            $remain = \DB::table('notification_recipients')->where('notification_id', $nid)->count();
            if ($remain === 0) {
                \DB::table('notification_comments')->where('notification_id', $nid)->delete();
                \DB::table('notifications')->where('id', $nid)->delete();
            }
        }

        return response()->json([
            'message' => "Đã xóa {$deleted} thông báo đã đọc",
            'deleted' => $deleted,
        ]);
    }

    private function ensureBackupReminderDaily($r): void
    {
        try {
            $storeId = $this->resolveStoreId($r);
            // 1) Lấy file backup mới nhất
            $backupPath = storage_path('app/backup/' . Str::slug(config('app.name', 'Laravel')));
            $latestFile = null;

            if (File::exists($backupPath)) {
                $latestFile = collect(File::files($backupPath))
                    ->filter(fn($f) => $f->getExtension() === 'zip')
                    ->sortByDesc(fn($f) => $f->getMTime())
                    ->first();
            }

            $lastBackupAt = $latestFile
                ? Carbon::createFromTimestamp($latestFile->getMTime())
                : null;

            // 2) Ngưỡng cảnh báo (ENV > config > default 7)
            $thresholdDays = (int) (config('backup.remind_days') ?? env('BACKUP_REMIND_DAYS', 7));
            if ($thresholdDays <= 0) $thresholdDays = 7;

            $daysSince = $lastBackupAt ? $lastBackupAt->diffInDays(now()) : PHP_INT_MAX;

            // Nếu chưa quá hạn thì thôi
            if ($daysSince < $thresholdDays) return;

            // 3) Chặn spam: nếu HÔM NAY đã nhắc rồi thì thôi
            $check = DB::table('notifications')
                ->where('type', 'reminder')
                ->where('ref_type', 'backup')
                ->whereDate('created_at', today())
                ->exists();
            Log::warning($check);
            if ($check) return;

            // 4) Lấy tất cả user có role 'Admin' (Spatie Permission)
            $guard  = config('auth.defaults.guard', 'web');
            $roleId = Role::where('name', 'Admin')
                ->value('id');

            if (!$roleId) {
                Log::warning('[BackupReminder] Role Admin không tồn tại');
                return;
            }

            $adminIds = DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', User::class)
                ->pluck('model_id')
                ->map(fn($x) => (int)$x)
                ->unique()
                ->values()
                ->all();

            if (empty($adminIds)) {
                Log::warning('[BackupReminder] Không tìm thấy user có role Admin');
                return;
            }

            // 5) Tạo notification + recipients
            $now  = now();
            $body = $lastBackupAt
                ? ("Đã {$daysSince} ngày chưa có bản sao lưu mới. Lần gần nhất: " . $lastBackupAt->format('Y-m-d H:i:s') . ". Vui lòng thực hiện backup.")
                : ("Hệ thống chưa có bất kỳ bản sao lưu nào. Vui lòng thực hiện backup ngay.");

            // NOTE: đổi 'created_by' -> 'creator_id' nếu cột của bạn khác
            $notiId = DB::table('notifications')->insertGetId([
                'store_id'   => $storeId,                 // global
                'created_by' => optional(auth()->user())->id,
                'type'       => 'reminder',
                'title'      => 'Nhắc sao lưu hệ thống',
                'body'       => $body,
                'priority'   => 'high',
                'ref_type'   => 'backup',
                'ref_id'     => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $rows = [];
            foreach ($adminIds as $uid) {
                $rows[] = [
                    'notification_id' => $notiId,
                    'user_id'         => $uid,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('notification_recipients')->insert($chunk);
            }
        } catch (\Throwable $e) {
            Log::error('[BackupReminder] Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function reminderExpiryDate($userId): void
    {
        $storeId = $this->resolveStoreIdByUserId($userId);
        $licenseExpiresAt = DB::table('users')->where('id', $userId)->value('license_expires_at');
        if (empty($licenseExpiresAt)) {
            return;
        }

        // 3) Chặn spam: nếu HÔM NAY đã nhắc rồi thì thôi
        $remindedToday = DB::table('notifications')
            ->where('type', 'reminder')
            ->where('ref_type', 'license_expiry')
            ->whereDate('created_at', now()->toDateString())
            ->pluck('id');
        $check = DB::table('notification_recipients')
            ->whereIn('notification_id', $remindedToday)
            ->where('read_at', '<>', null)
            ->exists();
        if ($check) return;
        // 4) Tính ngày còn lại
        $expiryDate = Carbon::parse($licenseExpiresAt);
        $daysLeft = (int) ceil( now()->floatDiffInDays($expiryDate, false) ); 
        if ($daysLeft > 0 && $daysLeft <= 7) {
            $existingReminder = DB::table('notifications')
                ->where('type', 'reminder')
                ->where('ref_type', 'license_expiry')
                ->where('ref_id', $userId)
                ->whereDate('created_at', now()->toDateString())
                ->exists();
            if ($existingReminder) {
                return;
            }
            $now = now();
            $notiId = DB::table('notifications')->insertGetId([
                'store_id'   => $storeId,                 // global
                'created_by' => optional(auth()->user())->id,
                'type'       => 'reminder',
                'title'      => 'Nhắc gia hạn sử dụng',
                'body'       => "Hạn sử dụng của bạn sẽ hết hạn sau {$daysLeft} ngày vào ngày " . $expiryDate->format('Y-m-d') . ". Vui lòng gia hạn kịp thời.",
                'priority'   => 'high',
                'ref_type'   => 'license_expiry',
                'ref_id'     => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('notification_recipients')->insert([
                'notification_id' => $notiId,
                'user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
