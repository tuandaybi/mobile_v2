<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'       => (int) $this->id,
            'category' => (string) ($this->category ?? 'other'),
            'name'     => (string) ($this->name ?? ''),
            'amount'   => (float) ($this->amount ?? 0),
            'date'     => $this->date ? (string) $this->date->format('Y-m-d') : null,
            'note'     => (string) ($this->note ?? ''),
            'user'     => $this->whenLoaded('user', function () {
                return [
                    'id'   => (int) ($this->user->id ?? 0),
                    'name' => (string) ($this->user->name ?? ''),
                ];
            }),
        ];
    }
}
