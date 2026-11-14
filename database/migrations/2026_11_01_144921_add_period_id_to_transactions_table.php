<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('financial_period_id')->nullable()->after('user_id')->constrained('financial_periods')->nullOnDelete();
            $table->index(['user_id', 'financial_period_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['financial_period_id']);
            $table->dropColumn('financial_period_id');
        });
    }
};
