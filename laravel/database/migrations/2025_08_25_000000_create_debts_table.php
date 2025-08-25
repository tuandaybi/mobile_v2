<?php
// database/migrations/2025_08_25_000000_create_debts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();

            // Nếu bảng của bạn có tên khác, đổi trong constrained('...') cho khớp
            $table->foreignId('mobileout_id')
                  ->nullable()
                  ->constrained('mobile_out')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->foreignId('service_id')
                  ->nullable()
                  ->constrained('services')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->foreignId('customer_id')
                  ->nullable()
                  ->constrained('customers')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            // Tổng nợ gốc
            $table->decimal('debt', 14, 2)->unsigned();

            // Tổng đã trả (cache để FE/filters nhanh)
            $table->decimal('paid_amount', 14, 2)->unsigned()->default(0);

            // Lần trả gần đây (cache)
            $table->decimal('last_payment_amount', 14, 2)->unsigned()->nullable();
            $table->timestamp('last_payment_at')->nullable();

            // Trạng thái
            $table->enum('status', ['pending','partial','paid'])->default('pending')->index();

            // Ngày phát sinh & (tuỳ chọn) hạn trả
            $table->date('date')->index();
            $table->date('due_date')->nullable()->index();

            $table->string('note', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

        });

        // Index tổng hợp hay dùng
        Schema::table('debts', function (Blueprint $table) {
            $table->index(['customer_id','status','date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
