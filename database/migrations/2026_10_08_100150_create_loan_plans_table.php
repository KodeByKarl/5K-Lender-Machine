<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Loan plans are set by the owner. Staff pick a plan; they cannot change its interest or term.
        Schema::create('loan_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('interest_rate', 8, 4);
            $table->string('rate_basis');
            $table->string('interest_method');
            $table->string('payment_frequency');
            $table->unsignedSmallInteger('term');
            $table->string('term_unit');
            $table->decimal('min_amount', 15, 2)->nullable();
            $table->decimal('max_amount', 15, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_plans');
    }
};
