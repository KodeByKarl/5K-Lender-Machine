<?php

namespace App\Filament\Resources\SavingsAccounts\Tables;

use App\Filament\Resources\SavingsAccounts\SavingsDrawer;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SavingsAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['borrower', 'area'])->withMax('transactions', 'transaction_date'))
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->recordAction('view')
            ->columns([
                TextColumn::make('account_no')
                    ->label('Account no.')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('borrower.full_name')
                    ->label('Account holder')
                    ->description(fn ($record) => $record->borrower->reference_no)
                    ->searchable(['first_name', 'last_name', 'reference_no']),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin()),
                TextColumn::make('opened_at')->label('Opened')->date('M d, Y')->sortable()->toggleable(),
                TextColumn::make('transactions_max_transaction_date')
                    ->label('Last activity')
                    ->date('M d, Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('balance')
                    ->money('PHP')
                    ->alignEnd()
                    ->sortable()
                    ->weight('semibold')
                    ->summarize(Sum::make()->money('PHP')->label('Total savings')),
            ])
            ->filters([
                SelectFilter::make('area')
                    ->relationship('area', 'name')
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->recordActions([
                SavingsDrawer::make(),
            ]);
    }
}
