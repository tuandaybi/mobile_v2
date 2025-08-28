<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsersResource extends JsonResource
{
    /**
     * Chuyển đổi resource thành một mảng.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'stores'     => $this->whenLoaded('stores', fn () =>
                $this->stores->map(fn($s) => [
                    'id'   => (int) $s->id,
                    'name' => $s->name,
                ])
            ),
            'role' => $this->getRoleNames()->first(), // Lấy tên vai trò đầu tiên
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'is_active' => $this->is_active ? 1 : 0, // Ánh xạ cột Active
        ];
    }
}