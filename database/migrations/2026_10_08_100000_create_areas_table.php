<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // null area_id = administrator with access to all Areas
            $table->foreignId('area_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('role')->default('staff')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
            $table->dropColumn(['role', 'is_active']);
        });

        Schema::dropIfExists('areas');
    }
};
