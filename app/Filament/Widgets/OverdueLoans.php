<?php

namespace App\Filament\Widgets;

use App\Enums\LoanStatus;
use App\Filament\Resources\Loans\LoanDrawer;
use App\Models\Loan;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueLoans extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Overdue loans';

    public function table(Table $table): Table
    {
        $areaId = $this->pageFilters['area_id'] ?? null;

        return $table
            ->query(fn (): Builder => Loan::query()
                ->where('status', LoanStatus::Overdue)
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
                ->with(['borrower', 'area'])
                ->withMin(['installments as oldest_unpaid' => fn ($q) => $q->whereColumn('amount_paid', '<', 'amount_due')], 'due_date'))
            ->defaultSort('oldest_unpaid')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->recordUrl(null)
            ->recordAction('view')
            ->recordActions([
                LoanDrawer::make()->hiddenLabel()->tooltip('View'),
            ])
            ->emptyStateHeading('No overdue loans')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('borrower.full_name')
                    ->label('Borrower')
                    ->description(fn (Loan $record) => $record->loan_no),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin() && ! $areaId),
                TextColumn::make('oldest_unpaid')
                    ->label('Late')
                    ->formatStateUsing(fn ($state) => ($days = (int) \Carbon\Carbon::parse($state)->diffInDays(today())).' '.str('day')->plural($days))
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('balance')->money('PHP')->alignEnd(),
            ]);
    }
}
