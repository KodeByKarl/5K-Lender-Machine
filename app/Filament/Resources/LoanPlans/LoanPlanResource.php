<?php

namespace App\Filament\Resources\LoanPlans;

use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateBasis;
use App\Enums\TermUnit;
use App\Filament\Resources\LoanPlans\Pages\ManageLoanPlans;
use App\Models\LoanPlan;
use App\Services\LoanCalculator;
use App\Services\LoanService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class LoanPlanResource extends Resource
{
    protected static ?string $model = LoanPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Loan plans';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    /** Sample amount used to illustrate each plan. */
    private const SAMPLE = 10000;

    public static function form(Schema $schema): Schema
    {
        $enum = fn ($value, string $class) => $value instanceof $class ? $value : (blank($value) ? null : $class::tryFrom($value));

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(100)
                    ->placeholder('e.g. Daily 60 days')
                    ->columnSpanFull(),

                Section::make('Interest')
                    ->columns(3)
                    ->schema([
                        TextInput::make('interest_rate')
                            ->label('Rate')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->live(onBlur: true),
                        Select::make('rate_basis')
                            ->label('Rate is')
                            ->options(RateBasis::class)
                            ->default(RateBasis::PerTerm)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live(),
                        Select::make('interest_method')
                            ->label('Method')
                            ->options(InterestMethod::class)
                            ->default(InterestMethod::Flat)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live(),
                    ]),

                Section::make('Payments')
                    ->columns(3)
                    ->schema([
                        Select::make('payment_frequency')
                            ->label('Collection')
                            ->options(PaymentFrequency::class)
                            ->default(PaymentFrequency::Daily)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live(),
                        TextInput::make('term')
                            ->label('Term')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(1825)
                            ->required()
                            ->live(onBlur: true),
                        Select::make('term_unit')
                            ->label('Term unit')
                            ->options(TermUnit::class)
                            ->default(TermUnit::Days)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live(),
                    ]),

                Section::make('Release charges')
                    ->description('Deducted from the loan amount at release. The borrower receives the net proceeds.')
                    ->schema([
                        Repeater::make('charges')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('name')->required()->maxLength(60)->placeholder('e.g. Service fee'),
                                Select::make('type')
                                    ->options(['percent' => '% of loan amount', 'fixed' => 'Fixed amount'])
                                    ->default('percent')
                                    ->selectablePlaceholder(false)
                                    ->required()
                                    ->live(),
                                TextInput::make('value')
                                    ->numeric()
                                    ->minValue(0)
                                    ->required()
                                    ->prefix(fn (Get $get) => $get('type') === 'fixed' ? '₱' : null)
                                    ->suffix(fn (Get $get) => $get('type') === 'percent' ? '%' : null)
                                    ->live(onBlur: true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->addActionLabel('Add charge')
                            ->live(),
                        TextInput::make('required_savings')
                            ->label('Required savings per collection')
                            ->helperText('Shown on the voucher, e.g. ₱28 daily. Collected as a savings deposit.')
                            ->prefix('₱')
                            ->numeric()
                            ->minValue(0),
                    ]),

                Section::make('Limits')
                    ->columns(3)
                    ->schema([
                        TextInput::make('min_amount')->label('Minimum loan')->prefix('₱')->numeric()->minValue(0),
                        TextInput::make('max_amount')->label('Maximum loan')->prefix('₱')->numeric()->minValue(0)->gte('min_amount'),
                        Toggle::make('is_active')->label('Available to staff')->default(true)->inline(false),
                    ]),

                TextEntry::make('example')
                    ->label('Example for ₱'.number_format(self::SAMPLE))
                    ->state(function (Get $get) use ($enum) {
                        $values = [$get('interest_rate'), $get('rate_basis'), $get('interest_method'), $get('payment_frequency'), $get('term'), $get('term_unit')];

                        if (in_array(null, $values, true) || in_array('', $values, true) || (int) $get('term') < 1) {
                            return 'Fill in the rate and term to see an example.';
                        }

                        return static::example(new LoanPlan([
                            'interest_rate' => $get('interest_rate'),
                            'rate_basis' => $enum($get('rate_basis'), RateBasis::class),
                            'interest_method' => $enum($get('interest_method'), InterestMethod::class),
                            'payment_frequency' => $enum($get('payment_frequency'), PaymentFrequency::class),
                            'term' => min((int) $get('term'), 1825),
                            'term_unit' => $enum($get('term_unit'), TermUnit::class),
                            'charges' => array_values($get('charges') ?? []),
                        ]));
                    })
                    ->columnSpanFull(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('loans'))
            ->defaultSort('sort')
            ->reorderable('sort')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (LoanPlan $record) => $record->summary())
                    ->searchable(),
                TextColumn::make('example')
                    ->label('Example for ₱'.number_format(self::SAMPLE))
                    ->state(fn (LoanPlan $record) => static::example($record))
                    ->color('gray'),
                TextColumn::make('limits')
                    ->label('Loan amount')
                    ->state(fn (LoanPlan $record) => match (true) {
                        $record->min_amount && $record->max_amount => '₱'.number_format((float) $record->min_amount).' – ₱'.number_format((float) $record->max_amount),
                        (bool) $record->min_amount => 'From ₱'.number_format((float) $record->min_amount),
                        (bool) $record->max_amount => 'Up to ₱'.number_format((float) $record->max_amount),
                        default => 'Any amount',
                    })
                    ->color('gray'),
                TextColumn::make('loans_count')->label('Loans')->alignCenter(),
                ToggleColumn::make('is_active')->label('Available'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('3xl'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No loan plans yet')
            ->emptyStateDescription('Create the plans your staff can offer, e.g. "Daily 60 days at 20%".');
    }

    /** e.g. "₱200.00 × 60 daily · total ₱12,000.00". */
    public static function example(LoanPlan $plan): string
    {
        $s = app(LoanCalculator::class)->schedule(
            principal: self::SAMPLE,
            interestRate: $plan->interest_rate,
            rateBasis: $plan->rate_basis,
            method: $plan->interest_method,
            frequency: $plan->payment_frequency,
            term: (int) $plan->term,
            termUnit: $plan->term_unit,
            startDate: today(),
        );

        $charges = array_sum(array_column(LoanService::computeCharges($plan->charges ?? [], self::SAMPLE), 'amount'));

        return '₱'.number_format((float) $s['installments'][0]['amount_due'], 2)
            .' × '.$s['installment_count'].' '.strtolower($plan->payment_frequency->getLabel())
            .' · total ₱'.number_format((float) $s['total_payable'], 2)
            .($charges > 0 ? ' · releases ₱'.number_format(self::SAMPLE - $charges, 2).' after ₱'.number_format($charges, 2).' charges' : '');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLoanPlans::route('/'),
        ];
    }
}
