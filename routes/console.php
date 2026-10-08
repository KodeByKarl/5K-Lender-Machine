<?php

use App\Services\LoanService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('loans:refresh-status', function (LoanService $loans) {
    $this->info($loans->refreshStatuses().' loan status(es) updated.');
})->purpose('Mark loans as Overdue, Active, or Paid based on their installments');

Schedule::command('loans:refresh-status')->dailyAt('00:05');
