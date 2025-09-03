<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'customers';

    protected $fillable = ['store_id','name','phone','social_link','note'];

    protected $casts = ['debt' => 'decimal:2'];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function mobileOuts(): HasMany
    {
        return $this->hasMany(MobileOut::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function debts() {
        return $this->hasMany(Debt::class, 'customer_id');
    }

    public function debtsMobileOut() {
        return $this->hasMany(Debt::class, 'customer_id')
                    ->whereNotNull('mobileout_id');
    }

    public function debtsService() {
        return $this->hasMany(Debt::class, 'customer_id')
                    ->whereNotNull('service_id');
    }

    public function paidsMobileOut() {
        return $this->hasMany(Debt::class, 'customer_id')
                    ->whereNotNull('mobileout_id');
    }

    public function paidsService() {
        return $this->hasMany(Debt::class, 'customer_id')
                    ->whereNotNull('service_id');
    }

    /** Nếu nhận máy cũ (trade-in) */
    // public function tradeInMobileIns(): HasMany
    // {
    //     return $this->hasMany(MobileIn::class);
    // }
}
