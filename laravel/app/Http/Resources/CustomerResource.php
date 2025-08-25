<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'store_id'=>$this->store_id,
            'name'=>$this->name,
            'phone'=>$this->phone,
            'social_link'=>$this->social_link,
            'debt'=>$this->debt,
            'note'=>$this->note,
            'created_at'=>optional($this->created_at)->toISOString(),
        ];
    }
}
