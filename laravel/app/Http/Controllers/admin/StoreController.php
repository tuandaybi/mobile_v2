<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Concerns\ResolvesStore;

class StoreController extends Controller
{
    /**
     * GET /admin/stores
     * Trả: { stores: [...], users: [...] }
     */
    public function index()
    {
        try {
            $stores = Store::with(['users:id,name,email'])
                ->withCount('users')
                ->orderBy('id','asc')
                ->get();

            $allUsers = User::select('id','name','email')
                ->orderBy('name')
                ->get();

            return response()->json([
                'stores' => $stores,
                'users'  => $allUsers,
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('GET /admin/stores failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách cửa hàng',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /admin/stores
     * Body: name,email,phone,address,(optional) user_ids:[]
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'email'   => 'required|string|email|max:255',
            'phone'   => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'user_ids'=> 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $store = Store::create($request->only(['name','email','phone','address']));

            // Nếu có gửi user_ids kèm theo thì sync pivot luôn
            if ($request->filled('user_ids')) {
                $store->users()->sync($request->user_ids);
            }

            $store->load(['users:id,name,email'])->loadCount('users');

            return response()->json($store, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi tạo cửa hàng: '.$e->getMessage()], 500);
        }
    }

    /**
     * PUT /admin/stores/{id}
     * Body: name,email,phone,address,(optional) user_ids:[]
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string|max:255',
            'email'   => 'required|string|email|max:255',
            'phone'   => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'user_ids'=> 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $store = Store::findOrFail($id);
            $store->update($request->only(['name','email','phone','address']));

            if ($request->has('user_ids')) {
                // gửi user_ids để đồng bộ lại nhân viên của cửa hàng
                $store->users()->sync($request->user_ids ?? []);
            }

            $store->load(['users:id,name,email'])->loadCount('users');

            return response()->json($store, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi cập nhật cửa hàng: '.$e->getMessage()], 500);
        }
    }

    /**
     * GET /admin/stores/{id}
     */
    public function show($id)
    {
        try {
            $store = Store::with(['users:id,name,email'])
                ->withCount('users')
                ->findOrFail($id);

            return response()->json($store, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Không tìm thấy cửa hàng: '.$e->getMessage()], 404);
        }
    }

    /**
     * DELETE /admin/stores/{id}
     */
    public function destroy($id)
    {
        try {
            $store = Store::findOrFail($id);
            $store->delete();
            return response()->json(['message' => 'Xóa cửa hàng thành công'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi xóa cửa hàng: '.$e->getMessage()], 500);
        }
    }

    /**
     * (Tùy chọn) PUT /admin/stores/{id}/sync-users
     * Body: { user_ids: [] }
     * Dùng khi muốn đồng bộ nhân viên từ UI mà không đổi thông tin store.
     */
    public function syncUsers(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_ids'=> 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors'=>$validator->errors()], 422);
        }

        try {
            $store = Store::findOrFail($id);
            $store->users()->sync($request->user_ids ?? []);
            $store->load(['users:id,name,email'])->loadCount('users');
            return response()->json($store, 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi đồng bộ nhân viên: '.$e->getMessage()], 500);
        }
    }
}
