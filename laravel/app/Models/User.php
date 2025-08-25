<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, HasApiTokens;
    protected string $guard_name = 'web';

    protected $fillable = [
        'name','email','password','is_active','remember_token',
    ];

    protected $hidden = ['password','remember_token','token_key'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'user_in_store', 'user_id', 'store_id')
                    ->withTimestamps();
    }

    public function mobileIns(): HasMany
    {
        return $this->hasMany(MobileIn::class);
    }

    public function mobileOuts(): HasMany
    {
        return $this->hasMany(MobileOut::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
