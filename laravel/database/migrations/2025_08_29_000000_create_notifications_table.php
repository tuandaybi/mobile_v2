<?php
// 2025_08_29_000000_create_notifications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

Schema::create('notifications', function (Blueprint $t) {
    $t->id();
    $t->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete(); // null = global
    $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $t->string('type')->default('log'); // log | announcement | task ...
    $t->string('title')->nullable();
    $t->text('body'); // nội dung chính
    // tham chiếu nghiệp vụ (tùy): vd liên kết mobile_out/service
    $t->string('ref_type')->nullable();  // 'mobile_out' | 'service' ...
    $t->unsignedBigInteger('ref_id')->nullable();
    $t->string('priority')->default('normal'); // low | normal | high
    $t->timestamps();
});

// 2025_08_29_000001_create_notification_recipients_table.php
Schema::create('notification_recipients', function (Blueprint $t) {
    $t->id();
    $t->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
    $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $t->timestamp('read_at')->nullable();
    $t->timestamps();
    $t->unique(['notification_id','user_id']);
});

// 2025_08_29_000002_create_notification_comments_table.php
Schema::create('notification_comments', function (Blueprint $t) {
    $t->id();
    $t->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
    $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $t->text('body');
    $t->timestamps();
});
