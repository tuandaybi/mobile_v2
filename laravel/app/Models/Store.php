<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Store extends Model
{
    use HasFactory;

    protected $table = 'stores';

    protected $fillable = ['name','email','phone','address'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_in_store', 'store_id', 'user_id')
                    ->withTimestamps();
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function mobileIns(): HasMany
    {
        return $this->hasMany(MobileIn::class);
    }

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
