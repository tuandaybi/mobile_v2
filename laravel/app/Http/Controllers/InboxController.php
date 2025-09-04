<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationComment;
use App\Http\Controllers\Concerns\ResolvesStore;

class InboxController extends Controller
{
    use ResolvesStore;
    
    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);
        $userId  = $r->user()->id;

        $perPage = max(1, min((int)$r->query('perPage', 20), 100));
        $q       = trim((string) $r->query('q', ''));
        $unread  = $r->boolean('unread');
        $type    = trim((string) $r->query('type', '')); // 'log' | 'reminder' | 'call'...

        $base = Notification::query()
            ->with(['creator:id,name', 'store:id,name'])
            ->where(function ($w) use ($storeId) {
                $w->whereNull('store_id')->orWhere('store_id', $storeId);
            })
            ->whereHas('recipients', fn($w) => $w->where('user_id', $userId))
            ->when($type !== '', fn($w) => $w->where('type', $type))
            ->when($q !== '', function ($w) use ($q) {
                $like = "%{$q}%";
                $w->where(function ($x) use ($like) {
                    $x->where('title', 'like', $like)
                      ->orWhere('body',  'like', $like);
                });
            })
            ->when($unread, fn($w) =>
                $w->whereHas('recipients', fn($x) =>
                    $x->where('user_id', $userId)->whereNull('read_at')
                )
            )
            ->orderByDesc('id');

        $page = $base->paginate($perPage);

        // Đếm chưa đọc cho user
        $unreadCount = NotificationRecipient::where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'items' => $page->items(),
            'meta'  => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function store(Request $r)
    {
        $r->validate([
            'type'       => 'nullable|string|max:32',
            'title'      => 'nullable|string|max:255',
            'body'       => 'required|string',
            'priority'   => 'nullable|string|in:low,normal,high',
            'store_id'   => 'nullable|integer', // admin có thể tạo global (null) hoặc chỉ định store
            'ref_type'   => 'nullable|string|max:32',
            'ref_id'     => 'nullable|integer',
            'recipients' => 'nullable|array',   // [user_id,...]
        ]);

        $creator = $r->user();
        $storeId = $this->resolveStoreId($r) ?? $r->input('store_id');

        $noti = Notification::create([
            'store_id'   => $storeId,
            'created_by' => $creator->id,
            'type'       => $r->input('type', 'log'),
            'title'      => $r->input('title'),
            'body'       => $r->input('body'),
            'priority'   => $r->input('priority', 'normal'),
            'ref_type'   => $r->input('ref_type'),
            'ref_id'     => $r->input('ref_id'),
        ]);

        // Xác định người nhận
        $targetUserIds = $r->input('recipients');
        if (!$targetUserIds) {
            // gửi cho tất cả user trong store (bảng pivot của mày: user_in_store với cột id_user, id_store)
            $targetUserIds = DB::table('user_in_store')
                ->where('id_store', $storeId)
                ->pluck('id_user')
                ->all();
        }

        $rows = array_map(fn ($uid) => [
            'notification_id' => $noti->id,
            'user_id'         => (int) $uid,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], array_unique($targetUserIds));

        if (!empty($rows)) {
            DB::table('notification_recipients')->insert($rows);
        }

        return response()->json($noti, 201);
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
}
