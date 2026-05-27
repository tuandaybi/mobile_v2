<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $t) {
            $t->foreignId('category_id')
                ->nullable()
                ->after('user_id')
                ->constrained('expense_categories')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE expenses e
            JOIN expense_categories c ON c.code = e.category
            SET e.category_id = c.id
        ");

        Schema::table('expenses', function (Blueprint $t) {
            $t->dropColumn('category');
        });

        Schema::table('expenses', function (Blueprint $t) {
            $t->foreignId('category_id')->nullable(false)->change();
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
