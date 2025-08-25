<?php
// database/migrations/2025_08_25_000010_create_debt_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained('debts')->cascadeOnDelete()->cascadeOnUpdate();
            $table->decimal('amount', 14, 2)->unsigned();
            $table->timestamp('paid_at')->index();
            $table->string('note', 500)->nullable();

            // (tuỳ chọn) ai tạo phiếu thu
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_payments');
    }
};
