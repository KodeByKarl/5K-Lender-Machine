<?php

namespace App\Filament\Resources\Loans\Tables;

use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Filament\Resources\Loans\LoanDrawer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['borrower', 'area']))
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->recordAction('view')
            ->columns([
                TextColumn::make('loan_no')
                    ->label('Loan no.')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('borrower.full_name')
                    ->label('Borrower')
                    ->description(fn ($record) => $record->borrower->reference_no)
                    ->searchable(['first_name', 'last_name', 'reference_no']),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin()),
                TextColumn::make('principal')
                    ->label('Amount')
                    ->money('PHP')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('payment_frequency')
                    ->label('Terms')
                    ->formatStateUsing(fn ($state, $record) => $record->installment_count.' × '.strtolower($state->getLabel()))
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('balance')
                    ->money('PHP')
                    ->alignEnd()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('maturity_date')
                    ->label('Maturity')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(LoanStatus::class),
                SelectFilter::make('payment_frequency')->label('Frequency')->options(PaymentFrequency::class),
                SelectFilter::make('area')
                    ->relationship('area', 'name')
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->recordActions([
                LoanDrawer::make(),
            ]);
    }
}
