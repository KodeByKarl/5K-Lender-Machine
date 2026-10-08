<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $isStaff = fn (Get $get) => ($get('role') instanceof UserRole ? $get('role') : UserRole::tryFrom((string) $get('role'))) === UserRole::Staff;
        $isSelf = fn ($record) => $record?->is(auth()->user());

        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(100),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->required(fn (string $operation) => $operation === 'create')
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText('Inactive users cannot sign in. Their past actions stay in the audit log.')
                            ->default(true)
                            ->disabled($isSelf)
                            ->inline(false),
                    ]),

                Section::make('Access')
                    ->columns(2)
                    ->schema([
                        Select::make('role')
                            ->options(UserRole::class)
                            ->default(UserRole::Staff)
                            ->required()
                            ->selectablePlaceholder(false)
                            ->disabled($isSelf)
                            ->live(),
                        Select::make('area_id')
                            ->label('Area')
                            ->relationship('area', 'name')
                            ->preload()
                            ->required($isStaff)
                            ->visible($isStaff)
                            ->helperText('Staff can only see and record data for this Area.'),
                    ]),
            ])
            ->columns(1);
    }
}
