<?php

namespace App\Filament\Pages;

use App\Models\Area;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return now()->format('l, F j, Y').' · '.($user->isAdmin() ? 'Administrator' : $user->area?->name);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('area_id')
                    ->label('Area')
                    ->placeholder('All areas (consolidated)')
                    ->options(fn () => Area::orderBy('name')->pluck('name', 'id'))
                    ->visible(fn () => auth()->user()?->isAdmin()),
            ])
            ->columns(3);
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
