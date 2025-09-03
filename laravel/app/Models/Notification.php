<?php
// App/Models/Notification.php
class Notification extends Model {
    protected $fillable = ['store_id','created_by','type','title','body','ref_type','ref_id','priority'];
    public function recipients(){ return $this->hasMany(NotificationRecipient::class); }
    public function comments(){ return $this->hasMany(NotificationComment::class)->latest(); }
    public function store(){ return $this->belongsTo(Store::class); }
    public function creator(){ return $this->belongsTo(User::class, 'created_by'); }
}

// App/Models/NotificationRecipient.php
class NotificationRecipient extends Model {
    protected $fillable = ['notification_id','user_id','read_at'];
    public function notification(){ return $this->belongsTo(Notification::class); }
    public function user(){ return $this->belongsTo(User::class); }
}

// App/Models/NotificationComment.php
class NotificationComment extends Model {
    protected $fillable = ['notification_id','user_id','body'];
    public function notification(){ return $this->belongsTo(Notification::class); }
    public function user(){ return $this->belongsTo(User::class); }
}
