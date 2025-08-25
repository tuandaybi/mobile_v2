<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsersResource;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        $roles = Role::pluck('name');
        $permissions = Permission::pluck('name');
        return response()->json([
            'users' => UsersResource::collection($users),
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        // 1. Tạo người dùng mới
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => bcrypt('12345678'),
        ]);

        // 2. Gán vai trò cho người dùng
        if (isset($validatedData['role'])) {
            $user->assignRole($validatedData['role']);
        }

        // 3. Gán các quyền trực tiếp (nếu có)
        if (isset($validatedData['permissions'])) {
            $user->syncPermissions($validatedData['permissions']);
        }

        return response()->json([
            'message' => 'Tạo người dùng thành công!',
            'user' => $user
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|string|exists:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user->update([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
        ]);

        $user->syncRoles($validatedData['role']);
        $user->syncPermissions($validatedData['permissions'] ?? []);

        $user->refresh();
        return response()->json([
            'message' => 'Thay đổi thông tin thành công!',
            'user' => $user->load('roles')->setRelation('permissions', $user->getAllPermissions())
        ], 201);
    }

    public function activeUser(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => !$user->is_active]);
        $user->refresh();

        return response()->json([
            'message' => 'Thay đổi trạng thái thành công!',
            'user' => $user->load('roles')->setRelation('permissions', $user->getAllPermissions())
        ], 201);
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Xóa user thành công'
        ], 201);
    }
    public function defaultRoleAndPermiss(Request $request)
    {
        $permissions = [
            'trangchinh',
            'dienthoai.xemmua',
            'dienthoai.xemban',
            'dienthoai.themmua',
            'dienthoai.themban',
            'dienthoai.suamua',
            'dienthoai.suaban',
            'dienthoai.xoamua',
            'dienthoai.xoaban',
            'dichvu.xem',
            'dichvu.them',
            'dichvu.sua',
            'dichvu.xoa',
            'congno.xem',
            'congno.sua',
            'congno.xoa',
            'imei.xemmua',
            'imei.xemban',
            'baocaoloinhuan.xem',
            'baocaosanluong.xem',
            'admin.users',
            'admin.users.phanquyen',
            'admin.sanpham',
            'admin.mausanpham',
            'admin.saoluu',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $roleAdmin = Role::findOrCreate('Admin');
        $roleAdmin -> givePermissionTo(Permission::all());

        $roleQuanly = Role::findOrCreate('Quản lý');
        $roleQuanly -> givePermissionTo([
            'trangchinh',
            'dienthoai.xemmua',
            'dienthoai.xemban',
            'dienthoai.themmua',
            'dienthoai.themban',
            'dienthoai.suamua',
            'dienthoai.suaban',
            'dichvu.xem',
            'dichvu.them',
            'dichvu.sua',
            'congno.xem',
            'imei.xemban',
            'admin.users',
            'admin.sanpham',
            'admin.mausanpham',
        ]);

        $roleNhanVien = Role::findOrCreate('Nhân viên');
        $roleNhanVien -> givePermissionTo([
            'trangchinh',
            'dienthoai.xemmua',
            'dienthoai.xemban',
            'dienthoai.themmua',
            'dienthoai.themban',
            'dienthoai.suamua',
            'dienthoai.suaban',
            'dichvu.xem',
            'dichvu.them',
            'dichvu.sua',
            'congno.xem',
            'imei.xemban',
        ]);

        return response()->json([
            'permissions' => $permissions,
        ]);
    }
}
