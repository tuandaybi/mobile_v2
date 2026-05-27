<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique()->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('expense_categories')->insert([
            ['name' => 'Chi phí cố định', 'code' => 'fixed',     'sort_order' => 1,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Nhập hàng',       'code' => 'inventory', 'sort_order' => 2,  'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Khác',            'code' => 'other',     'sort_order' => 99, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
