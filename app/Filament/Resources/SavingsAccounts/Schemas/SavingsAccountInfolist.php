<?php

namespace App\Filament\Resources\SavingsAccounts\Schemas;

use App\Filament\Resources\Borrowers\BorrowerResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SavingsAccountInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components())->columns(1);
    }

    /** Shared by the savings account page and the savings drawer. */
    public static function components(): array
    {
        return [
            Section::make()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('balance')
                        ->label('Available balance')
                        ->money('PHP')
                        ->size('lg')
                        ->weight('bold'),
                    TextEntry::make('borrower.full_name')
                        ->label('Account holder')
                        ->url(fn ($record) => BorrowerResource::getUrl('view', ['record' => $record->borrower_id]))
                        ->color('primary'),
                    TextEntry::make('area.name')->label('Area')->badge()->color('gray'),
                    TextEntry::make('opened_at')->label('Opened')->date('M d, Y'),
                ]),
        ];
    }
}
