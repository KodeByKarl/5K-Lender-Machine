<?php

namespace App\Filament\Resources\SavingsAccounts\Schemas;

use App\Models\Borrower;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SavingsAccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Open a savings account')
                    ->description('Each borrower can have one savings account. The account number is assigned automatically.')
                    ->columns(2)
                    ->schema([
                        Select::make('borrower_id')
                            ->label('Borrower')
                            ->relationship(
                                'borrower',
                                'last_name',
                                modifyQueryUsing: fn (Builder $query) => $query->whereDoesntHave('savingsAccount'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Borrower $record) => $record->reference_no.' · '.$record->full_name)
                            ->searchable(['reference_no', 'first_name', 'last_name'])
                            ->preload()
                            ->required()
                            ->default(fn () => request()->query('borrower')),
                        DatePicker::make('opened_at')
                            ->label('Opening date')
                            ->native(false)
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                    ]),
            ])
            ->columns(1);
    }
}
