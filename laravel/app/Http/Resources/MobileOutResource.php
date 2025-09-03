<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MobileOutResource extends JsonResource
{

    public function toArray($request)
    {
        $mi = $this->whenLoaded('mobileIn');
        return [
            'id'             => (int) $this->id,
            'code'           => $this->code,
            'device_name'    => (string) optional(optional($mi)->device)->name,
            'country_code'   => (string) ($mi->country_code ?? ''),
            'storage_gb'     => (int)    (optional(optional($mi)->storage)->size_gb ?? 0),
            'color_name'     => (string) (optional(optional($mi)->color)->vi_name ?? optional(optional($mi)->color)->en_name ?? ''),
            'imei'           => (string) ($mi->imei ?? ''),
            'customer_name'  => (string) optional($this->customer)->name,
            'customer_phone' => (string) optional($this->customer)->phone,
            'export_date'    => (string) ($this->export_date ?? optional($this->created_at)?->toDateString()),
            'price'          => (int)    ($this->export_price ?? 0),
            'debt_amount'    => (int)    ($this->debt_amount ?? 0),
            'note'           => (string) ($this->note ?? ''),
            'warranty'       => (int)    ($this->warranty ?? 0),
        ];
    }
}
