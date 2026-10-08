<?php

namespace App\Filament\Resources\Borrowers;

use App\Filament\Resources\Borrowers\Pages\CreateBorrower;
use App\Filament\Resources\Borrowers\Pages\EditBorrower;
use App\Filament\Resources\Borrowers\Pages\ListBorrowers;
use App\Filament\Resources\Borrowers\Pages\ViewBorrower;
use App\Filament\Resources\Borrowers\RelationManagers\LoansRelationManager;
use App\Filament\Resources\Borrowers\Schemas\BorrowerForm;
use App\Filament\Resources\Borrowers\Schemas\BorrowerInfolist;
use App\Filament\Resources\Borrowers\Tables\BorrowersTable;
use App\Models\Borrower;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BorrowerResource extends Resource
{
    protected static ?string $model = Borrower::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Lending';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference_no', 'first_name', 'last_name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->reference_no.' · '.$record->full_name;
    }

    public static function form(Schema $schema): Schema
    {
        return BorrowerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BorrowerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BorrowersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LoansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBorrowers::route('/'),
            'create' => CreateBorrower::route('/create'),
            'view' => ViewBorrower::route('/{record}'),
            'edit' => EditBorrower::route('/{record}/edit'),
        ];
    }
}
