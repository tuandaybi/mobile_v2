<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Thêm category_id nullable + FK RESTRICT (chỉ khi chưa có)
        if (!Schema::hasColumn('expenses', 'category_id')) {
            Schema::table('expenses', function (Blueprint $t) {
                $t->foreignId('category_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('expense_categories')
                    ->restrictOnDelete();
            });
        }

        // 2. Backfill từ code enum cũ rồi drop cột cũ (nếu còn)
        if (Schema::hasColumn('expenses', 'category')) {
            DB::statement("
                UPDATE expenses e
                JOIN expense_categories c ON c.code = e.category
                SET e.category_id = c.id
            ");
            Schema::table('expenses', function (Blueprint $t) {
                $t->dropColumn('category');
            });
        }

        // 3. Set NOT NULL. Drop FK trước (có thể là SET NULL từ lần migrate fail trước)
        //    rồi re-create với RESTRICT (compatible với NOT NULL)
        try {
            Schema::table('expenses', function (Blueprint $t) {
                $t->dropForeign(['category_id']);
            });
        } catch (\Throwable $e) {
            // FK có thể đã không tồn tại nếu migration trước fail giữa chừng — bỏ qua
        }

        DB::statement("ALTER TABLE expenses MODIFY category_id BIGINT UNSIGNED NOT NULL");

        Schema::table('expenses', function (Blueprint $t) {
            $t->foreign('category_id')
                ->references('id')->on('expense_categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $t) {
            $t->enum('category', ['fixed', 'inventory', 'other'])->default('other')->after('user_id');
        });

        DB::statement("
            UPDATE expenses e
            JOIN expense_categories c ON c.id = e.category_id
            SET e.category = c.code
        ");

        Schema::table('expenses', function (Blueprint $t) {
            $t->dropConstrainedForeignId('category_id');
        });
    }
};
