<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    protected static ?string $pluralModelLabel = 'audit log';

    protected static ?int $navigationSort = 3;

    public const EVENTS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'login' => 'Login',
        'logout' => 'Logout',
        'login_failed' => 'Failed login',
    ];

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Date & time')->dateTime('M d, Y g:i:s A'),
                        TextEntry::make('causer.name')->label('User')->placeholder('System / unknown'),
                        TextEntry::make('area.name')->label('Area')->placeholder('All areas'),
                        TextEntry::make('event')
                            ->label('Action')
                            ->badge()
                            ->formatStateUsing(fn (?string $state) => static::EVENTS[$state] ?? $state)
                            ->color(fn (?string $state) => static::eventColor($state)),
                        TextEntry::make('subject')
                            ->label('Record')
                            ->state(fn (Activity $record) => $record->subjectLabel())
                            ->columnSpanFull(),
                        TextEntry::make('changes_table')
                            ->label('Changes')
                            ->state(fn (Activity $record) => static::changesTable($record))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'area', 'subject']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('M d, Y g:i A')
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('User')
                    ->placeholder('—'),
                TextColumn::make('area.name')
                    ->label('Area')
                    ->badge()
                    ->color('gray')
                    ->placeholder('All'),
                TextColumn::make('event')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => static::EVENTS[$state] ?? $state)
                    ->color(fn (?string $state) => static::eventColor($state)),
                TextColumn::make('subject_label')
                    ->label('Record')
                    ->state(fn (Activity $record) => $record->subjectLabel())
                    ->wrap(),
                TextColumn::make('summary')
                    ->label('Changed')
                    ->state(fn (Activity $record) => static::changedFields($record))
                    ->color('gray')
                    ->limit(50)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('area')->relationship('area', 'name'),
                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'],
                        fn ($q, $id) => $q->where('causer_type', User::class)->where('causer_id', $id),
                    )),
                SelectFilter::make('event')->label('Action')->options(static::EVENTS),
                SelectFilter::make('subject_type')
                    ->label('Record type')
                    ->options(Activity::SUBJECT_LABELS),
                Filter::make('date')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        $data['from'] ? 'From '.$data['from'] : null,
                        $data['until'] ? 'Until '.$data['until'] : null,
                    ])),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()->modalWidth('2xl'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }

    private static function eventColor(?string $event): string
    {
        return match ($event) {
            'created' => 'success',
            'updated' => 'info',
            'deleted', 'login_failed' => 'danger',
            default => 'gray',
        };
    }

    private static function changedFields(Activity $record): ?string
    {
        $attributes = array_keys($record->properties['attributes'] ?? $record->properties['old'] ?? []);

        return $attributes ? implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), $attributes)) : null;
    }

    private static function changesTable(Activity $record): HtmlString|string
    {
        $new = $record->properties['attributes'] ?? [];
        $old = $record->properties['old'] ?? [];
        $keys = array_unique([...array_keys($old), ...array_keys($new)]);

        if (! $keys) {
            $extra = collect($record->properties)->except(['attributes', 'old'])->map(fn ($v, $k) => "$k: $v")->implode(' · ');

            return $extra ?: '—';
        }

        $format = fn ($v) => match (true) {
            $v === null || $v === '' => '<span class="text-gray-400">—</span>',
            is_array($v) => e(json_encode($v)),
            default => e((string) $v),
        };

        $rows = '';
        foreach ($keys as $key) {
            $rows .= '<tr class="border-t border-gray-200 dark:border-white/10">'
                .'<td class="py-1.5 pe-4 text-gray-500 dark:text-gray-400">'.e(str_replace('_', ' ', $key)).'</td>'
                .'<td class="py-1.5 pe-4 text-danger-600 dark:text-danger-400">'.(array_key_exists($key, $old) ? $format($old[$key]) : '').'</td>'
                .'<td class="py-1.5 text-success-700 dark:text-success-400">'.(array_key_exists($key, $new) ? $format($new[$key]) : '').'</td>'
                .'</tr>';
        }

        return new HtmlString(
            '<table class="w-full text-sm"><thead><tr class="text-start text-xs uppercase tracking-wide text-gray-500">'
            .'<th class="pb-1.5 text-start">Field</th><th class="pb-1.5 text-start">Before</th><th class="pb-1.5 text-start">After</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
        );
    }
}
