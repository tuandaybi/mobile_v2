<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'          => (int) $this->id,
            'category_id' => (int) $this->category_id,
            'category'    => $this->whenLoaded('category', function () {
                return [
                    'id'   => (int) ($this->category->id ?? 0),
                    'name' => (string) ($this->category->name ?? ''),
                    'code' => (string) ($this->category->code ?? ''),
                ];
            }),
            'name'        => (string) ($this->name ?? ''),
            'amount'      => (float) ($this->amount ?? 0),
            'date'        => $this->date ? (string) $this->date->format('Y-m-d') : null,
            'note'        => (string) ($this->note ?? ''),
            'user'        => $this->whenLoaded('user', function () {
                return [
                    'id'   => (int) ($this->user->id ?? 0),
                    'name' => (string) ($this->user->name ?? ''),
                ];
            }),
        ];
    }
}
