<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Borrowers\BorrowerResource;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\SavingsAccounts\SavingsAccountResource;
use App\Services\ReportService;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LendingStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $reports = app(ReportService::class);
        $s = $reports->summary($reports->resolveArea($this->pageFilters['area_id'] ?? null));

        $peso = fn (float $v) => '₱'.number_format($v, 2);
        $loansUrl = LoanResource::getUrl('index');

        return [
            Stat::make('Collected today', $peso($s['collected_today']))
                ->description($s['due_today'] > 0 ? $peso($s['due_today']).' still to collect today' : 'All of today\'s dues collected')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),
            Stat::make('Outstanding balance', $peso($s['outstanding']))
                ->description($s['active_loans'].' open '.str('loan')->plural($s['active_loans']))
                ->descriptionIcon('heroicon-m-banknotes')
                ->url($loansUrl),
            Stat::make('Past due', $peso($s['past_due']))
                ->description($s['overdue_loans'].' overdue '.str('loan')->plural($s['overdue_loans']))
                ->descriptionIcon($s['overdue_loans'] ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($s['overdue_loans'] ? 'danger' : 'success')
                ->url($loansUrl.'?'.http_build_query(['filters' => ['status' => ['value' => 'overdue']]])),
            Stat::make('Total savings', $peso($s['savings']))
                ->descriptionIcon('heroicon-m-wallet')
                ->description('Savings balance')
                ->url(SavingsAccountResource::getUrl('index')),

            Stat::make('Borrowers', number_format($s['borrowers']))
                ->url(BorrowerResource::getUrl('index')),
            Stat::make('Active loans', number_format($s['active_loans'])),
            Stat::make('Total collected', $peso($s['collected']))
                ->description('of '.$peso($s['released']).' released'),
            Stat::make('Overdue loans', number_format($s['overdue_loans']))
                ->color($s['overdue_loans'] ? 'danger' : null),
        ];
    }
}
