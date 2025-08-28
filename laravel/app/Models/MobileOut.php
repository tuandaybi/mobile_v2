<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileOut extends Model
{
    use HasFactory;

    protected $table = 'mobile_out';

    protected $fillable = [
        'mobile_in_id','user_id','customer_id',
        'export_date','export_price','expense','warranty','payment','note'
    ];

    protected $casts = [
        'export_date' => 'date',
        'export_price' => 'decimal:2',
        'expense' => 'decimal:2',
    ];

    public function mobileIn(): BelongsTo
    {
        return $this->belongsTo(MobileIn::class, 'mobile_in_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
