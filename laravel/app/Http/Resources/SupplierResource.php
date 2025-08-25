<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'=>$this->id,
            'store_id'=>$this->store_id,
            'name'=>$this->name,
            'tax_code'=>$this->tax_code,
            'phone'=>$this->phone,
            'email'=>$this->email,
            'address'=>$this->address,
            'note'=>$this->note,
            'is_active'=> (bool)$this->is_active,
        ];
    }
}
