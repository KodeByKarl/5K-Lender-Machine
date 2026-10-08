<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Borrower;
use App\Models\LoanPlan;
use App\Models\User;
use App\Services\LoanService;
use App\Services\SavingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Realistic sample data for demos and training: borrowers in every Area, daily loans
 * at different stages, payment habits from reliable to late, and savings.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Never run this on the Client's live database.
 */
class DemoSeeder extends Seeder
{
    private const FIRST = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Mark', 'Liza', 'Ramon', 'Grace', 'Carlo', 'Joy', 'Paolo', 'Nena', 'Rico', 'Mila', 'Dante', 'Cora', 'Arnel', 'Lorna', 'Jun', 'Weng', 'Boyet', 'Tess'];

    private const LAST = ['Dela Cruz', 'Santos', 'Reyes', 'Garcia', 'Mendoza', 'Bautista', 'Villanueva', 'Ramos', 'Aquino', 'Castillo', 'Navarro', 'Torres', 'Flores', 'Gonzales', 'Rivera', 'Domingo', 'Pascual', 'Salazar', 'Mercado', 'Aguilar', 'Manalo', 'Soriano', 'Tolentino', 'Ocampo'];

    private const STREETS = ['Rizal St.', 'Mabini St.', 'Bonifacio Ave.', 'Luna St.', 'Del Pilar St.', 'Burgos St.', 'Quezon Blvd.', 'Market Rd.'];

    public function run(): void
    {
        mt_srand(2026);

        $this->call(DatabaseSeeder::class);

        $loans = app(LoanService::class);
        $savings = app(SavingsService::class);
        $plans = LoanPlan::active()->get();
        $i = 0;

        foreach (Area::orderBy('id')->get() as $area) {
            $staff = User::where('area_id', $area->id)->first();
            Auth::login($staff);

            // One pre-printed booklet per Area; now and then a receipt is spoiled and skipped.
            $receipt = 1000 * $area->id;
            $nextReceipt = function () use (&$receipt) {
                $receipt += mt_rand(1, 120) === 1 ? 2 : 1;

                return str_pad((string) $receipt, 5, '0', STR_PAD_LEFT);
            };

            for ($n = 0; $n < 8; $n++, $i++) {
                $borrower = Borrower::create([
                    'first_name' => self::FIRST[$i % count(self::FIRST)],
                    'last_name' => self::LAST[($i * 7) % count(self::LAST)],
                    'contact_no' => '09'.mt_rand(10, 99).mt_rand(1000000, 9999999),
                    'address' => mt_rand(1, 250).' '.self::STREETS[mt_rand(0, count(self::STREETS) - 1)].', '.$area->name,
                    'occupation' => ['Sari-sari store', 'Vendor', 'Tricycle driver', 'Carinderia', 'Laundry', 'Fish vendor'][mt_rand(0, 5)],
                ]);

                // Payment habit: most pay daily and catch up after a missed day; a few fall behind.
                $habit = [0.97, 0.97, 0.95, 0.9, 0.9, 0.85, 0.6, 0.35][$n];
                $catchesUp = $n < 6;
                $missed = 0.0;
                $plan = $plans[$n % $plans->count()];
                $start = today()->subDays(mt_rand(8, 75));
                $principal = [5000, 8000, 10000, 15000, 20000][mt_rand(0, 4)];

                $loan = $loans->create([
                    'borrower_id' => $borrower->id,
                    'loan_plan_id' => $plan->id,
                    'principal' => $principal,
                    'start_date' => $start->toDateString(),
                ]);

                foreach ($loan->installments()->where('due_date', '<=', today())->get() as $installment) {
                    if (mt_rand(1, 100) / 100 > $habit) {
                        $missed += (float) $installment->amount_due;

                        continue;
                    }

                    $balance = (float) $loan->refresh()->balance;
                    if ($balance <= 0) {
                        break;
                    }

                    $amount = (float) $installment->amount_due + ($catchesUp ? $missed : 0);
                    $missed = $catchesUp ? 0.0 : $missed;

                    $loans->recordPayment($loan, [
                        'amount' => min($amount, $balance),
                        'payment_date' => $installment->due_date->toDateString(),
                        'receipt_no' => $nextReceipt(),
                    ]);
                }

                if ($n % 2 === 0) {
                    $account = $savings->open($borrower, $start->toDateString());
                    for ($d = $start->copy()->addDays(3); $d->lte(today()); $d->addDays(mt_rand(5, 10))) {
                        $savings->deposit($account, [
                            'amount' => [50, 100, 200, 500][mt_rand(0, 3)],
                            'transaction_date' => $d->toDateString(),
                            'reference_no' => $nextReceipt(),
                        ]);
                    }
                }
            }
        }

        Auth::logout();
        $loans->refreshStatuses();
    }
}
