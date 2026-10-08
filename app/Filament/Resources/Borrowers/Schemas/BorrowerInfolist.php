<?php

namespace App\Filament\Resources\Borrowers\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BorrowerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components())->columns(1);
    }

    /** Shared by the borrower page and the borrower drawer. */
    public static function components(): array
    {
        return [
            Section::make()
                ->columns(['default' => 2, 'md' => 4])
                ->schema([
                    TextEntry::make('reference_no')->label('Reference no.')->weight('bold')->copyable(),
                    TextEntry::make('full_name')->label('Name'),
                    TextEntry::make('area.name')->label('Area')->badge()->color('gray'),
                    TextEntry::make('contact_no')->label('Contact')->placeholder('—'),
                    TextEntry::make('address')->placeholder('—')->columnSpan(2),
                    TextEntry::make('occupation')->placeholder('—'),
                    TextEntry::make('birth_date')->date('M d, Y')->placeholder('—'),
                    TextEntry::make('savingsAccount.balance')->label('Savings')->money('PHP')->placeholder('No account'),
                    TextEntry::make('notes')->placeholder('—')->columnSpanFull(),
                ]),
        ];
    }
}
