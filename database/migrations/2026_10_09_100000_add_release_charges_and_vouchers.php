<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Charges deducted from the loan at release, set by the owner per plan:
        // [{"name": "Service fee", "type": "percent"|"fixed", "value": 11}]
        Schema::table('loan_plans', function (Blueprint $table) {
            $table->json('charges')->nullable()->after('term_unit');
            $table->decimal('required_savings', 15, 2)->nullable()->after('charges'); // per collection
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->string('voucher_no')->nullable()->unique()->after('loan_no');
            $table->string('release_method')->default('cash')->after('voucher_no'); // cash | bank
            $table->string('release_reference')->nullable()->after('release_method'); // bank / check reference
            $table->json('charges')->nullable()->after('total_payable'); // snapshot: [{"name", "amount"}]
            $table->decimal('total_charges', 15, 2)->default(0)->after('charges');
            $table->decimal('net_proceeds', 15, 2)->nullable()->after('total_charges'); // cash actually released
            $table->decimal('required_savings', 15, 2)->nullable()->after('net_proceeds');
            $table->foreignId('released_by')->nullable()->after('remarks')->constrained('users')->nullOnDelete();
        });

        // Existing loans: no charges, full amount released, vouchers numbered in creation order.
        $n = 0;
        foreach (DB::table('loans')->orderBy('id')->pluck('id') as $id) {
            DB::table('loans')->where('id', $id)->update([
                'voucher_no' => str_pad((string) ++$n, 6, '0', STR_PAD_LEFT),
                'net_proceeds' => DB::raw('principal'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('released_by');
            $table->dropUnique(['voucher_no']);
            $table->dropColumn(['voucher_no', 'release_method', 'release_reference', 'charges', 'total_charges', 'net_proceeds', 'required_savings']);
        });

        Schema::table('loan_plans', function (Blueprint $table) {
            $table->dropColumn(['charges', 'required_savings']);
        });
    }
};
