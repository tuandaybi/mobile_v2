<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'      => $this->id,
            'name'    => $this->name,
            'phone'   => $this->phone,
            'email'   => $this->email,
            'address' => $this->address,

            // lấy từ withCount('users') -> users_count
            'users_count' => (int) ($this->users_count ?? 0),

            // chỉ trả khi đã eager-load
            'users' => UserBasicResource::collection($this->whenLoaded('users')),
        ];
    }
}
