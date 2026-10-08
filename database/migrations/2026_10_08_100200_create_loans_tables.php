<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_plan_id')->nullable()->constrained()->nullOnDelete(); // null = custom terms set by the owner
            $table->string('loan_no')->unique();
            $table->decimal('principal', 15, 2);
            $table->decimal('interest_rate', 8, 4);       // percent, e.g. 5.0000 = 5%
            $table->string('rate_basis');                 // per_month | per_term | per_year
            $table->string('interest_method');            // flat | diminishing
            $table->string('payment_frequency');          // daily | weekly | semi_monthly | monthly
            $table->unsignedSmallInteger('term');
            $table->string('term_unit')->default('days'); // days | months
            $table->unsignedSmallInteger('installment_count');
            $table->date('start_date');
            $table->date('maturity_date');
            $table->decimal('total_interest', 15, 2);
            $table->decimal('total_payable', 15, 2);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2);
            $table->string('status')->default('active');  // active | paid | overdue
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['area_id', 'status']);
        });

        Schema::create('loan_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('due_date');
            $table->decimal('principal', 15, 2);
            $table->decimal('interest', 15, 2);
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('remaining_balance', 15, 2);   // loan balance after this installment per schedule
            $table->date('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'number']);
            $table->index('due_date');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->string('receipt_no')->nullable();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->text('remarks')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['area_id', 'payment_date']);
            $table->unique(['area_id', 'receipt_no']); // one booklet number per Area (see ReceiptBook)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('loan_installments');
        Schema::dropIfExists('loans');
    }
};
