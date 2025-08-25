<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'store_id'=>$this->store_id,
            'customer'=> new CustomerResource($this->whenLoaded('customer')),
            'name'=>$this->name,
            'price'=>$this->price,
            'expense'=>$this->expense,
            'warranty'=>$this->warranty,
            'user'=> new UserBasicResource($this->whenLoaded('user')),
            'note'=>$this->note,
            'created_at'=>optional($this->created_at)->toISOString(),
        ];
    }
}
