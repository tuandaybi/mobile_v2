<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use SoftDeletes;

    protected $table = 'debts';

    protected $fillable = [
        'mobileout_id',
        'service_id',
        'customer_id',
        'user_id',
        'debt',
        'paid_amount',
        'last_payment_amount',
        'last_payment_at',
        'status',
        'date',
        'due_date',
        'note',
    ];

    protected $casts = [
        'debt'                => 'decimal:2',
        'paid_amount'         => 'decimal:2',
        'last_payment_amount' => 'decimal:2',
        'last_payment_at'     => 'datetime',
        'date'                => 'date',
        'due_date'            => 'date',
    ];

    // Quan hệ (đổi class nếu tên model của bạn khác)
    public function mobileOut() { return $this->belongsTo(\App\Models\MobileOut::class, 'mobileout_id'); }
    public function service()   { return $this->belongsTo(\App\Models\Service::class, 'service_id'); }
    public function customer()  { return $this->belongsTo(\App\Models\Customer::class, 'customer_id'); }
    public function user()      { return $this->belongsTo(\App\Models\User::class, 'user_id'); }
    public function payments()  { return $this->hasMany(\App\Models\DebtPayment::class); }

    // Số còn nợ
    public function getRemainingAttribute(): float
    {
        return max(0, (float)$this->debt - (float)$this->paid_amount);
    }

    // Đồng bộ cache: paid_amount, last_payment_*, status
    public function recalcCache(): void
    {
        $sum  = (float) $this->payments()->sum('amount');
        $last = $this->payments()
            ->orderBy('paid_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $status = $sum >= (float)$this->debt ? 'paid' : ($sum > 0 ? 'partial' : 'pending');

        $this->forceFill([
            'paid_amount'         => $sum,
            'last_payment_amount' => $last?->amount,
            'last_payment_at'     => $last?->paid_at,
            'status'              => $status,
        ])->save();
    }
}
