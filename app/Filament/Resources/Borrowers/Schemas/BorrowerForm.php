<?php

namespace App\Filament\Resources\Borrowers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BorrowerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal information')
                    ->columns(3)
                    ->schema([
                        TextInput::make('first_name')->required()->maxLength(100),
                        TextInput::make('middle_name')->maxLength(100),
                        TextInput::make('last_name')->required()->maxLength(100),
                        DatePicker::make('birth_date')->native(false)->maxDate(today()),
                        TextInput::make('contact_no')->label('Contact number')->tel()->maxLength(30),
                        TextInput::make('occupation')->maxLength(100),
                        TextInput::make('address')->columnSpanFull()->maxLength(255),
                    ]),

                Section::make('Assignment')
                    ->columns(2)
                    ->schema([
                        Select::make('area_id')
                            ->label('Area')
                            ->relationship('area', 'name')
                            ->required()
                            ->preload()
                            ->visible(fn () => auth()->user()?->isAdmin()),
                        TextInput::make('reference_no')
                            ->label('Reference no.')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Assigned automatically'),
                        Textarea::make('notes')->columnSpanFull()->rows(3),
                    ]),
            ])
            ->columns(1);
    }
}
