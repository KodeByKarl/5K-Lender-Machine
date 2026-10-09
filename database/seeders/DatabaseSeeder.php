<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Area;
use App\Models\LoanPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Initial setup from Annex A: the three Areas, the administrator, one staff account per Area,
     * and starter loan plans. Safe to run again: existing records are kept, passwords are not reset.
     *
     * Names and emails come from .env (SETUP_*). Locally every password is "password";
     * in production each new account gets a random password that is printed once.
     */
    public function run(): void
    {
        $local = app()->isLocal() || app()->runningUnitTests();
        $created = [];

        $account = function (string $email, array $attributes) use ($local, &$created) {
            $user = User::firstOrNew(['email' => $email]);

            if (! $user->exists) {
                $password = config('lending.setup.default_password')
                    ?: ($local ? 'password' : Str::password(14, symbols: false));
                $user->password = $password;
                $created[] = [$attributes['name'], $email, $password];
            }

            $user->fill($attributes)->save();
        };

        $setup = config('lending.setup');

        $account($setup['admin']['email'], [
            'name' => $setup['admin']['name'],
            'role' => UserRole::Admin,
            'area_id' => null,
        ]);

        foreach ($setup['areas'] as $n => $areaSetup) {
            $area = Area::firstOrCreate(['code' => "A{$n}"], ['name' => $areaSetup['name']]);

            $account($areaSetup['staff_email'], [
                'name' => $areaSetup['staff_name'] ?: "{$area->name} Staff",
                'role' => UserRole::Staff,
                'area_id' => $area->id,
            ]);
        }

        // Starter plans; the owner edits these under Administration → Loan plans.
        if (LoanPlan::doesntExist()) {
            $plans = [
                ['name' => 'Daily 30 days', 'interest_rate' => 10, 'term' => 30, 'term_unit' => 'days'],
                ['name' => 'Daily 60 days', 'interest_rate' => 20, 'term' => 60, 'term_unit' => 'days'],
                ['name' => 'Daily 3 months', 'interest_rate' => 30, 'term' => 3, 'term_unit' => 'months'],
            ];

            foreach ($plans as $sort => $plan) {
                LoanPlan::create([
                    ...$plan,
                    'rate_basis' => 'per_term',
                    'interest_method' => 'flat',
                    'payment_frequency' => 'daily',
                    'sort' => $sort,
                ]);
            }
        }

        if ($created && $this->command) {
            $this->command->warn('New accounts. Copy these now; the passwords are not shown again:');
            $this->command->table(['Name', 'Email', 'Password'], $created);
        }
    }
}
