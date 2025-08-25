<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $battery = $this->battery_capacity;

        return [
            'id'           => $this->id,
            'device_id'    => $this->device_id,
            'color_id'     => $this->color_id,
            'storage_id'   => $this->storage_id,
            'user'         => new UserBasicResource($this->whenLoaded('user')),
            'store'        => new StoreResource($this->whenLoaded('store')),
            'device'       => new DeviceResource($this->whenLoaded('device')),
            'color'        => new ColorResource($this->whenLoaded('color')),
            'storage'      => new DeviceStorageResource($this->whenLoaded('storage')),
            'mobile_out'   => new MobileOutResource($this->whenLoaded('mobileOut')),
            'imei'             => $this->imei,
            'country_code'     => $this->country_code,
            'battery_capacity' => $battery,
            'battery_label'    => isset($battery) ? ($battery.'%') : null,
            'supplier'         => $this->supplier,
            'import_price'     => (float) $this->import_price,
            'import_date'      => optional($this->import_date)->toDateString(),
            'import_note'      => $this->import_note,
            'is_sold'          => (bool) $this->is_sold,
            'created_at'       => optional($this->created_at)->toISOString(),
        ];
    }
}
