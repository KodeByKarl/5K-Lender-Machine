<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrower_id')->unique()->constrained()->restrictOnDelete();
            $table->string('account_no')->unique();
            $table->date('opened_at');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('savings_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('savings_account_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date');
            $table->string('type');                         // deposit | withdrawal
            $table->decimal('amount', 15, 2);
            $table->decimal('running_balance', 15, 2);
            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['savings_account_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_transactions');
        Schema::dropIfExists('savings_accounts');
    }
};
