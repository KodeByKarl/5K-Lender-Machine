<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->restrictOnDelete();
            $table->string('reference_no')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('contact_no')->nullable();
            $table->string('address')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('occupation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['area_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
