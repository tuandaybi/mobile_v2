<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tên model tránh trùng Facade Storage. Vẫn trỏ về bảng `storage`.
 */
class DeviceStorage extends Model
{
    use HasFactory;

    protected $table = 'storages';

    protected $fillable = ['name','size_gb','is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function mobileIns(): HasMany
    {
        return $this->hasMany(MobileIn::class, 'storage_id');
    }
}
