<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DebtPaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'        => $this->id,
            'amount'    => (float) $this->amount,
            'paid_at'   => optional($this->paid_at)->toIso8601String(),
            'note'      => $this->note,
            'created_by'=> $this->created_by,
            'created_user' => $this->whenLoaded('createdUser', fn () => [
                'id'   => $this->createdUser->id,
                'name' => $this->createdUser->name,
            ]),
            'created_at'=> optional($this->created_at)->toIso8601String(),
            'updated_at'=> optional($this->updated_at)->toIso8601String(),
        ];
    }
}
