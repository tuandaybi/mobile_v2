<?php
namespace App\Http\Resources;

use App\Models\Debt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'            => (int) $this->id,
            'service_name'  => (string) ($this->service_name ?? $this->name ?? ''),
            'service_price' => (float) ($this->service_price ?? $this->price ?? 0),
            'expense'       => (float) ($this->expense ?? 0),
            'service_date'  => (string) ($this->service_date ?? $this->created_at ?? ''),
            'warranty'      => (int) ($this->warranty ?? 0),
            'note'          => (string) ($this->note ?? ''),
            'debt'          => (float) (Debt::where('service_id', $this->id)->sum('debt') ?? 0),
            // Quan hệ
            'customer'      => $this->whenLoaded('customer', function () {
                return [
                    'id'    => (int) ($this->customer->id ?? 0),
                    'name'  => (string) ($this->customer->name ?? ''),
                    'phone' => (string) ($this->customer->phone ?? ''),
                ];
            }),
            'user'          => $this->whenLoaded('user', function () {
                return [
                    'id'   => (int) ($this->user->id ?? 0),
                    'name' => (string) ($this->user->name ?? ''),
                ];
            }),
        ];
    }
}
