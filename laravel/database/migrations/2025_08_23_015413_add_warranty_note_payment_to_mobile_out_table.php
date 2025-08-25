<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mobile_out', function (Blueprint $table) {
            if (!Schema::hasColumn('mobile_out', 'warranty')) {
                $table->string('warranty')->nullable()->after('expense');
            }
            if (!Schema::hasColumn('mobile_out', 'payment')) {
                $table->integer('payment')->nullable()->after('expense');
            }
            if (!Schema::hasColumn('mobile_out', 'note')) {
                $table->text('note')->nullable()->after('expense');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_out', function (Blueprint $table) {
            $table->dropColumn('warranty');
            $table->dropColumn('note');
        });
    }
};
