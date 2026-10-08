<?php

namespace App\Filament\Resources\Borrowers\RelationManagers;

use App\Filament\Resources\Loans\LoanDrawer;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    protected static ?string $title = 'Loan history';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('loan_no')
            ->defaultSort('start_date', 'desc')
            ->recordUrl(null)
            ->recordAction('view')
            ->columns([
                TextColumn::make('loan_no')->label('Loan no.')->weight('medium'),
                TextColumn::make('start_date')->date('M d, Y')->sortable(),
                TextColumn::make('principal')->money('PHP')->alignEnd(),
                TextColumn::make('total_payable')->label('Total payable')->money('PHP')->alignEnd(),
                TextColumn::make('balance')->money('PHP')->alignEnd(),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                LoanDrawer::make(),
            ]);
    }
}
