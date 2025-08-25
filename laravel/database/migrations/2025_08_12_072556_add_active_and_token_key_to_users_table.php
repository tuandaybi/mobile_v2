<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // thêm is_active nếu chưa có
            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(false)->after('password');
            }

            // thêm token_key nếu chưa có
            if (!Schema::hasColumn('users', 'token_key')) {
                // Đặt sau is_active (đúng tên cột)
                if (Schema::hasColumn('users', 'is_active')) {
                    $table->string('token_key')->nullable()->after('is_active');
                } else {
                    $table->string('token_key')->nullable()->after('password');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'token_key')) {
                $table->dropColumn('token_key');
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
