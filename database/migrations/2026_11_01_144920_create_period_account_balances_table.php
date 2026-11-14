<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_account_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['financial_period_id', 'account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_account_balances');
    }
};
