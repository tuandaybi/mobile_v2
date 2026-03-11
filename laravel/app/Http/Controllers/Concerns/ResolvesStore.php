<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ResolvesStore
{
    /**
     * Lấy user hiện tại và danh sách store_id từ pivot user_in_store.
     * @return array{0:\Illuminate\Contracts\Auth\Authenticatable|null,1:array<int,int>}
     */
    protected function resolveUserAndStores(Request $request): array
    {
        $user = $request->user('sanctum') ?? $request->user();
        if (!$user) {
            return [null, []];
        }

        $storeIds = DB::table('user_in_store')
            ->where('user_id', $user->id)
            ->pluck('store_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        return [$user, $storeIds];
    }

    /**
     * Chọn store_id đang dùng cho request.
     * - Cho phép ?store_id=... nếu nằm trong whitelist $storeIds.
     * - Nếu không có, lấy phần tử đầu tiên.
     */
    protected function resolveStoreId(Request $request, ?array $storeIds = null): ?int
    {
        if ($storeIds === null) {
            [, $storeIds] = $this->resolveUserAndStores($request);
        }
        if (empty($storeIds)) {
            return null;
        }

        $candidate = (int) $request->input('store_id', 0);
        if ($candidate && in_array($candidate, $storeIds, true)) {
            return $candidate;
        }

        return $storeIds[0];
    }

    protected function resolveStoreName($storeId)
    {
        if (empty($storeId)) {
            return null;
        }
        $store = DB::table('stores')->select('name')->where('id', $storeId)->first();
        return $store->name ?? null;
    }

    private function resolveStoreIdByUserId(int $userId): ?int
    {
        if (\Schema::hasColumn('user_in_store', 'user_id') && \Schema::hasColumn('user_in_store', 'store_id')) {
            return \DB::table('user_in_store')
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->value('store_id');
        }
        return null;
    }
}
