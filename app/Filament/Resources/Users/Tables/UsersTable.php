<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('area')->addSelect([
                'last_login_at' => Activity::select('created_at')
                    ->whereColumn('causer_id', 'users.id')
                    ->where('causer_type', User::class)
                    ->where('event', 'login')
                    ->latest('id')
                    ->limit(1),
            ]))
            ->defaultSort('role')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (User $record) => $record->email)
                    ->searchable(['name', 'email']),
                TextColumn::make('role')->badge(),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->placeholder('All areas'),
                IconColumn::make('is_active')->label('Active')->boolean()->alignCenter(),
                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime('M d, Y g:i A')
                    ->since()
                    ->placeholder('Never'),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
