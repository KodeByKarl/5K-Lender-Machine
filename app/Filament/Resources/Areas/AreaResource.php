<?php

namespace App\Filament\Resources\Areas;

use App\Enums\LoanStatus;
use App\Filament\Resources\Areas\Pages\ManageAreas;
use App\Models\Area;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AreaResource extends Resource
{
    protected static ?string $model = Area::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),
                TextInput::make('code')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->helperText('Short code, e.g. A1'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $open = fn ($q) => $q->where('status', '!=', LoanStatus::Paid);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount(['borrowers', 'users', 'loans as open_loans_count' => $open])
                ->withSum(['loans as outstanding' => $open], 'balance'))
            ->paginated(false)
            ->columns([
                TextColumn::make('code')->badge()->color('gray'),
                TextColumn::make('name')->weight('medium')->searchable(),
                TextColumn::make('users_count')->label('Users')->alignCenter(),
                TextColumn::make('borrowers_count')->label('Borrowers')->alignCenter(),
                TextColumn::make('open_loans_count')->label('Open loans')->alignCenter(),
                TextColumn::make('outstanding')->money('PHP')->placeholder('₱0.00')->alignEnd(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAreas::route('/'),
        ];
    }
}
