<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $table = 'expenses';

    protected $fillable = [
        'store_id', 'user_id', 'category', 'name', 'amount', 'date', 'note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'date'   => 'date',
    ];

    public function store():    BelongsTo { return $this->belongsTo(Store::class, 'store_id'); }
    public function user():     BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
