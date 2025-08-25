<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MobileIn extends Model
{
    use HasFactory;

    protected $table = 'mobile_in';

    protected $fillable = [
        'user_id','store_id','device_id','color_id','storage_id',
        'imei','country_code','battery_capacity','import_price','import_date',
        'import_note','supplier', // supplier là VARCHAR ghi chú nguồn nhập
        'is_sold',
    ];

    protected $casts = [
        'import_date'       => 'date',        // hoặc 'date:Y-m-d' nếu muốn format cố định khi serialize
        'is_sold'           => 'boolean',
        'battery_capacity'  => 'integer',
        'import_price'      => 'decimal:2',
    ];

    /* ================== Relationships ================== */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    // Lưu ý: Model cho bảng 'storages' nên đặt tên tránh đụng Facade Storage
    public function storage(): BelongsTo
    {
        return $this->belongsTo(DeviceStorage::class, 'storage_id');
    }

    public function mobileOut(): HasOne
    {
        return $this->hasOne(MobileOut::class, 'mobile_in_id');
    }

    /* ================== Scopes ================== */

    // Tìm kiếm nhanh theo nhiều trường
    public function scopeQ($q, ?string $term)
    {
        if (!$term = trim((string) $term)) return $q;

        return $q->where(function ($w) use ($term) {
            $w->where('imei', 'like', "%{$term}%")
              ->orWhere('country_code', 'like', "%{$term}%")
              ->orWhere('supplier', 'like', "%{$term}%")
              ->orWhere('import_note', 'like', "%{$term}%");
        });
    }

    // Lọc theo các field cho phép (KHÔNG nhận store_id từ FE)
    public function scopeFilters($q, array $f)
    {
        foreach (['device_id','color_id','storage_id'] as $col) {
            if (!empty($f[$col])) {
                $q->where($col, $f[$col]);
            }
        }

        if (!empty($f['supplier'])) {
            $q->where('supplier', 'like', '%'.$f['supplier'].'%');
        }

        if (array_key_exists('sold', $f)) {
            $q->where('is_sold', (int) $f['sold'] ? 1 : 0);
        }

        if (!empty($f['date_from'])) {
            $q->whereDate('import_date', '>=', $f['date_from']);
        }
        if (!empty($f['date_to'])) {
            $q->whereDate('import_date', '<=', $f['date_to']);
        }

        return $q;
    }

    // Áp quyền theo danh sách store_id của user (lấy từ pivot user_in_store)
    public function scopeInStores($q, array $storeIds)
    {
        return $q->whereIn('store_id', $storeIds);
    }
}
