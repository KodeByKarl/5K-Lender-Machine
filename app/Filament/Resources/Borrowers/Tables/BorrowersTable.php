<?php

namespace App\Filament\Resources\Borrowers\Tables;

use App\Enums\LoanStatus;
use Filament\Actions\EditAction;
use App\Filament\Resources\Borrowers\BorrowerDrawer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BorrowersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('area')
                ->withCount(['loans as open_loans_count' => fn ($q) => $q->where('status', '!=', LoanStatus::Paid)])
                ->withSum(['loans as outstanding' => fn ($q) => $q->where('status', '!=', LoanStatus::Paid)], 'balance'))
            ->defaultSort('id', 'desc')
            ->recordUrl(null)
            ->recordAction('view')
            ->columns([
                TextColumn::make('reference_no')
                    ->label('Ref no.')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('contact_no')
                    ->label('Contact')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->isAdmin()),
                TextColumn::make('open_loans_count')
                    ->label('Open loans')
                    ->alignCenter(),
                TextColumn::make('outstanding')
                    ->label('Outstanding')
                    ->money('PHP')
                    ->placeholder('₱0.00')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('area')
                    ->relationship('area', 'name')
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->recordActions([
                BorrowerDrawer::make(),
                EditAction::make(),
            ]);
    }
}
