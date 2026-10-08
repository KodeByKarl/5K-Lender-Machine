<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Loans\LoanResource;
use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentPayments extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent payments';

    public function table(Table $table): Table
    {
        $areaId = $this->pageFilters['area_id'] ?? null;

        return $table
            ->query(fn (): Builder => Payment::query()
                ->when($areaId, fn ($q) => $q->where('area_id', $areaId))
                ->with(['loan.borrower', 'area', 'receiver'])
                ->orderByDesc('payment_date')
                ->orderByDesc('id'))
            ->paginated([10, 25])
            ->recordUrl(fn (Payment $record) => LoanResource::getUrl('view', ['record' => $record->loan_id]))
            ->emptyStateHeading('No payments recorded yet')
            ->columns([
                TextColumn::make('payment_date')->label('Date')->date('M d, Y'),
                TextColumn::make('loan.borrower.full_name')
                    ->label('Borrower')
                    ->description(fn (Payment $record) => $record->loan->loan_no),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin() && ! $areaId),
                TextColumn::make('receiver.name')->label('Received by')->placeholder('—'),
                TextColumn::make('amount')->money('PHP')->alignEnd()->weight('medium'),
                TextColumn::make('balance_after')->label('Balance after')->money('PHP')->alignEnd()->color('gray'),
            ]);
    }
}
