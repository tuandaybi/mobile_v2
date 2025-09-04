<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'store_id','created_by','type','title','body',
        'ref_type','ref_id','priority',
    ];

    public function store()   { return $this->belongsTo(Store::class, 'store_id'); }
    public function creator() { return $this->belongsTo(User::class,  'created_by'); }

    public function recipients()
    {
        return $this->hasMany(NotificationRecipient::class, 'notification_id');
    }

    public function comments()
    {
        return $this->hasMany(NotificationComment::class, 'notification_id');
    }
}