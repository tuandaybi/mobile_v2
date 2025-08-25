<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;

class CreateUser
{
    /**
     * $attrs: [
     *   name, email, password, role (optional), permissions (optional), is_active (optional)
     * ]
     * $options: ['allow_set_role' => bool, 'allow_set_permissions' => bool]
     */
    public function handle(array $attrs, array $options = []): User
    {
        $data = [
            'name' => $attrs['name'],
            'email' => $attrs['email'],
            'password' => Hash::make($attrs['password'] ?? '12345678'),
            'is_active' => Arr::get($attrs, 'is_active', true),
        ];

        $user = User::create($data);

        // Gán role/permission chỉ khi cho phép (ví dụ từ Admin)
        if (($options['allow_set_role'] ?? false) && !empty($attrs['role'])) {
            $user->assignRole($attrs['role']);
        }

        if (($options['allow_set_permissions'] ?? false) && !empty($attrs['permissions'])) {
            $user->syncPermissions($attrs['permissions']);
        }

        return $user->fresh();
    }
}
