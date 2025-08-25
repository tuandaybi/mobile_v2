<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileOutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'mobile_in_id'=>$this->mobile_in_id,
            'user'=> new UserBasicResource($this->whenLoaded('user')),
            'customer'=> new CustomerResource($this->whenLoaded('customer')),
            'export_date'=>$this->export_date? $this->export_date->toDateString(): null,
            'export_price'=>$this->export_price,
            'expense'=>$this->expense,
            'warranty'=>$this->warranty,
            'created_at'=>optional($this->created_at)->toISOString(),
        ];
    }
}
