<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtPayment extends Model
{
    protected $table = 'debt_payments';

    protected $fillable = [
        'debt_id',
        'amount',
        'paid_at',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function debt() { return $this->belongsTo(\App\Models\Debt::class); }

    protected static function booted()
    {
        // Tự đồng bộ cache ở Debt sau mọi thay đổi payment
        $recalc = function (self $p) {
            if ($p->relationLoaded('debt')) {
                $p->debt?->recalcCache();
            } else {
                $p->debt()->first()?->recalcCache();
            }
        };

        static::created($recalc);
        static::updated($recalc);
        static::deleted($recalc);
    }
    public function createdUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
