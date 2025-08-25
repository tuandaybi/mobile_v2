<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DebtResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'customer_id'  => $this->customer_id,
            'mobileout_id' => $this->mobileout_id,
            'service_id'   => $this->service_id,

            'debt'          => (float) $this->debt,
            'paid_amount'   => (float) $this->paid_amount,
            'remaining'     => $this->when(true, (float) $this->remaining),

            'last_payment_amount' => $this->last_payment_amount !== null ? (float) $this->last_payment_amount : null,
            'last_payment_at'     => optional($this->last_payment_at)->toIso8601String(),
            'status'              => $this->status,

            'date'      => optional($this->date)->toDateString(),
            'due_date'  => optional($this->due_date)->toDateString(),
            'note'      => $this->note,

            'customer'  => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id, 'name' => $this->customer->name ?? null,
            ]),
            'service'   => $this->whenLoaded('service', fn () => [
                'id' => $this->service->id, 'name' => $this->service->name ?? null,
            ]),
            'mobile_out'=> $this->whenLoaded('mobileOut', fn () => [
                'id' => $this->mobileOut->id,
            ]),

            'created_at'=> optional($this->created_at)->toIso8601String(),
            'updated_at'=> optional($this->updated_at)->toIso8601String(),
        ];
    }
}
