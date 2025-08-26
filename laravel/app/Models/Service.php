<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $fillable = [
        'store_id','customer_id','name','price','expense','user_id','note','warranty',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'expense' => 'decimal:2',
    ];

    public function store():    BelongsTo { return $this->belongsTo(Store::class, 'store_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class, 'customer_id'); }
    public function user():     BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
