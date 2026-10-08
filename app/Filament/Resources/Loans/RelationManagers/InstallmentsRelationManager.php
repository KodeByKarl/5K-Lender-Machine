<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use App\Models\LoanInstallment;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'Repayment schedule';

    #[On('refresh-loan')]
    public function refreshTable(): void
    {
        // Re-render after a payment changes the loan.
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('number')
            ->defaultSort('number')
            ->paginated([12, 26, 52, 'all'])
            ->defaultPaginationPageOption(12)
            ->columns([
                TextColumn::make('number')->label('#')->alignCenter(),
                TextColumn::make('due_date')->label('Due date')->date('D, M d, Y'),
                TextColumn::make('principal')->money('PHP')->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('')),
                TextColumn::make('interest')->money('PHP')->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('')),
                TextColumn::make('amount_due')->label('Total due')->money('PHP')->alignEnd()->weight('medium')
                    ->summarize(Sum::make()->money('PHP')->label('')),
                TextColumn::make('amount_paid')->label('Paid')->money('PHP')->alignEnd()
                    ->summarize(Sum::make()->money('PHP')->label('')),
                TextColumn::make('remaining_balance')->label('Balance after')->money('PHP')->alignEnd()->color('gray'),
                TextColumn::make('state')
                    ->label('Status')
                    ->badge()
                    ->state(fn (LoanInstallment $record) => match (true) {
                        $record->isPaid() => 'Paid',
                        $record->due_date->lt(today()) => 'Late',
                        (float) $record->amount_paid > 0 => 'Partial',
                        default => 'Upcoming',
                    })
                    ->color(fn (string $state) => match ($state) {
                        'Paid' => 'success',
                        'Late' => 'danger',
                        'Partial' => 'warning',
                        default => 'gray',
                    }),
            ]);
    }
}
