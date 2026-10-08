<?php

namespace App\Filament\Resources\SavingsAccounts;

use App\Filament\Resources\SavingsAccounts\Pages\CreateSavingsAccount;
use App\Filament\Resources\SavingsAccounts\Pages\ListSavingsAccounts;
use App\Filament\Resources\SavingsAccounts\Pages\ViewSavingsAccount;
use App\Filament\Resources\SavingsAccounts\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\SavingsAccounts\Schemas\SavingsAccountForm;
use App\Filament\Resources\SavingsAccounts\Schemas\SavingsAccountInfolist;
use App\Filament\Resources\SavingsAccounts\Tables\SavingsAccountsTable;
use App\Models\SavingsAccount;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SavingsAccountResource extends Resource
{
    protected static ?string $model = SavingsAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|UnitEnum|null $navigationGroup = 'Savings';

    protected static ?string $navigationLabel = 'Savings accounts';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'account_no';

    public static function canEdit(Model $record): bool
    {
        // Balances change only through deposits and withdrawals.
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return SavingsAccountForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SavingsAccountInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SavingsAccountsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSavingsAccounts::route('/'),
            'create' => CreateSavingsAccount::route('/create'),
            'view' => ViewSavingsAccount::route('/{record}'),
        ];
    }
}
