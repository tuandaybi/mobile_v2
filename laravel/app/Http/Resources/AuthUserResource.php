<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    protected ?string $authToken = null;
    protected ?string $storeName = null;

    public function withToken(?string $token): self
    {
        $this->authToken = $token;
        return $this;
    }

    public function withStoreName(?string $name): self
    {
        $this->storeName = $name;
        return $this;
    }

    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'email'              => $this->email,
            'created_at'         => $this->created_at,
            'license_expires_at' => $this->license_expires_at,
            // dùng resource->getRoleNames() cho chắc
            'roles'              => method_exists($this->resource, 'getRoleNames')
                ? $this->resource->getRoleNames()
                : [],
            'permissions'        => method_exists($this->resource, 'getAllPermissions')
                ? $this->resource->getAllPermissions()->pluck('name')->values()
                : [],
            // ➜ luôn có field, kể cả null (đỡ undefined ở FE)
            'auth_token'         => $this->authToken,
            'store_name'         => $this->storeName,
        ];
    }
}
