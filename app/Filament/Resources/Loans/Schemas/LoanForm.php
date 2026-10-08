<?php

namespace App\Filament\Resources\Loans\Schemas;

use App\Enums\InterestMethod;
use App\Enums\PaymentFrequency;
use App\Enums\RateBasis;
use App\Enums\TermUnit;
use App\Models\Borrower;
use App\Models\LoanPlan;
use App\Services\LoanCalculator;
use App\Services\LoanService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class LoanForm
{
    public static function configure(Schema $schema): Schema
    {
        $isAdmin = fn () => (bool) auth()->user()?->isAdmin();

        // Loan terms are locked once created (the schedule and payments depend on them),
        // and are set by the owner through loan plans. Only the owner can enter custom terms.
        $locked = fn (string $operation) => $operation === 'edit';
        $termsLocked = fn (string $operation, Get $get) => $operation === 'edit' || filled($get('loan_plan_id')) || ! $isAdmin();

        $defaults = static::defaultTerms();

        return $schema
            ->components([
                Section::make('Borrower and plan')
                    ->columns(2)
                    ->schema([
                        Select::make('borrower_id')
                            ->label('Borrower')
                            ->relationship('borrower', 'last_name')
                            ->getOptionLabelFromRecordUsing(fn (Borrower $record) => $record->reference_no.' · '.$record->full_name)
                            ->searchable(['reference_no', 'first_name', 'last_name'])
                            ->preload()
                            ->required()
                            ->default(fn () => request()->query('borrower'))
                            ->disabled($locked),
                        Select::make('loan_plan_id')
                            ->label('Loan plan')
                            ->options(fn () => LoanPlan::active()->get()->mapWithKeys(fn (LoanPlan $plan) => [$plan->id => $plan->name.' — '.$plan->summary()]))
                            ->getOptionLabelUsing(fn ($value) => ($plan = LoanPlan::find($value)) ? $plan->name.' — '.$plan->summary() : null)
                            ->placeholder(fn () => $isAdmin() ? 'Custom terms (owner only)' : 'Choose a plan')
                            ->required(fn () => ! $isAdmin())
                            ->default($defaults['loan_plan_id'])
                            ->helperText(fn (string $operation) => $operation === 'edit' ? null : ($isAdmin()
                                ? 'Interest and term come from the plan. Leave empty to enter custom terms.'
                                : 'Interest and term are set by the owner.'))
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($plan = LoanPlan::find($state)) {
                                    foreach ($plan->termValues() as $field => $value) {
                                        $set($field, $value);
                                    }
                                }
                            })
                            ->disabled($locked),
                    ]),

                Section::make('Loan terms')
                    ->description(fn (string $operation) => $operation === 'edit' ? 'These cannot be changed after the loan is created.' : null)
                    ->columns(4)
                    ->schema([
                        TextInput::make('principal')
                            ->label('Loan amount')
                            ->prefix('₱')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true)
                            ->disabled($locked),
                        TextInput::make('interest_rate')
                            ->label('Interest rate')
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->required()
                            ->default($defaults['interest_rate'])
                            ->live(onBlur: true)
                            ->disabled($termsLocked),
                        Select::make('rate_basis')
                            ->options(RateBasis::class)
                            ->required()
                            ->default($defaults['rate_basis'])
                            ->live()
                            ->disabled($termsLocked),
                        Select::make('interest_method')
                            ->options(InterestMethod::class)
                            ->required()
                            ->default($defaults['interest_method'])
                            ->live()
                            ->disabled($termsLocked),
                        Select::make('payment_frequency')
                            ->options(PaymentFrequency::class)
                            ->required()
                            ->default($defaults['payment_frequency'])
                            ->live()
                            ->disabled($termsLocked),
                        TextInput::make('term')
                            ->label('Term')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(fn (Get $get) => static::enum($get('term_unit'), TermUnit::class) === TermUnit::Months ? 60 : 1825)
                            ->required()
                            ->default($defaults['term'])
                            ->live(onBlur: true)
                            ->disabled($termsLocked),
                        Select::make('term_unit')
                            ->label('Term unit')
                            ->options(TermUnit::class)
                            ->required()
                            ->selectablePlaceholder(false)
                            ->default($defaults['term_unit'])
                            ->live()
                            ->disabled($termsLocked),
                        DatePicker::make('start_date')
                            ->label('Release date')
                            ->native(false)
                            ->required()
                            ->default(today())
                            ->live()
                            ->disabled($locked),
                    ]),

                Section::make('Release')
                    ->hiddenOn('edit')
                    ->columns(2)
                    ->schema([
                        ToggleButtons::make('release_method')
                            ->label('Released through')
                            ->options(['cash' => 'Cash', 'bank' => 'Bank'])
                            ->icons(['cash' => 'heroicon-m-banknotes', 'bank' => 'heroicon-m-building-library'])
                            ->default('cash')
                            ->inline()
                            ->required()
                            ->live(),
                        TextInput::make('release_reference')
                            ->label('Bank / check reference')
                            ->maxLength(60)
                            ->visible(fn (Get $get) => $get('release_method') === 'bank'),
                        Repeater::make('charge_rules')
                            ->label('Release charges')
                            ->helperText('Custom terms only. Plans use the charges the owner set on the plan.')
                            ->schema([
                                TextInput::make('name')->required()->maxLength(60),
                                Select::make('type')
                                    ->options(['percent' => '% of loan amount', 'fixed' => 'Fixed amount'])
                                    ->default('fixed')
                                    ->selectablePlaceholder(false)
                                    ->required(),
                                TextInput::make('value')->numeric()->minValue(0)->required()->live(onBlur: true),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add charge')
                            ->live()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $isAdmin() && blank($get('loan_plan_id'))),
                        TextInput::make('required_savings')
                            ->label('Required savings per collection')
                            ->prefix('₱')
                            ->numeric()
                            ->minValue(0)
                            ->visible(fn (Get $get) => $isAdmin() && blank($get('loan_plan_id'))),
                    ]),

                Section::make('Computation preview')
                    ->hiddenOn('edit')
                    ->schema([
                        TextEntry::make('preview')
                            ->hiddenLabel()
                            ->state(fn (Get $get) => static::preview($get)),
                    ]),

                Section::make()
                    ->schema([
                        Textarea::make('remarks')->rows(3),
                    ]),
            ])
            ->columns(1);
    }

    /** Pre-fill with the first active plan, or the configured defaults when there are no plans. */
    private static function defaultTerms(): array
    {
        $plan = LoanPlan::active()->first();

        if ($plan) {
            return ['loan_plan_id' => $plan->id, ...$plan->termValues()];
        }

        return ['loan_plan_id' => null, ...config('lending.defaults')];
    }

    private static function preview(Get $get): HtmlString|string
    {
        $values = [$get('principal'), $get('interest_rate'), $get('rate_basis'), $get('interest_method'), $get('payment_frequency'), $get('term'), $get('term_unit'), $get('start_date')];

        if (in_array(null, $values, true) || in_array('', $values, true) || (float) $get('principal') <= 0 || (int) $get('term') < 1) {
            return 'Enter the loan amount and terms to see the computation.';
        }

        $frequency = static::enum($get('payment_frequency'), PaymentFrequency::class);

        $s = app(LoanCalculator::class)->schedule(
            principal: $get('principal'),
            interestRate: $get('interest_rate'),
            rateBasis: static::enum($get('rate_basis'), RateBasis::class),
            method: static::enum($get('interest_method'), InterestMethod::class),
            frequency: $frequency,
            term: min((int) $get('term'), 1825),
            termUnit: static::enum($get('term_unit'), TermUnit::class),
            startDate: Carbon::parse($get('start_date')),
        );

        $peso = fn ($v) => '₱'.number_format((float) $v, 2);
        $first = $s['installments'][0]['amount_due'];

        $rules = filled($get('loan_plan_id'))
            ? (LoanPlan::find($get('loan_plan_id'))?->charges ?? [])
            : array_values($get('charge_rules') ?? []);
        $charges = array_sum(array_column(LoanService::computeCharges($rules, $get('principal')), 'amount'));

        $stats = [
            'Installment' => $peso($first).' × '.$s['installment_count'].' ('.strtolower($frequency->getLabel()).')',
            'Total interest' => $peso($s['total_interest']),
            'Total payable' => $peso($s['total_payable']),
            'Release charges' => $peso($charges),
            'Net proceeds (cash released)' => $peso((float) $get('principal') - $charges),
            'First due' => $s['installments'][0]['due_date']->format('M d, Y'),
            'Maturity' => $s['maturity_date']->format('M d, Y'),
        ];

        $html = '<dl style="display: grid; grid-template-columns: repeat(auto-fill, minmax(10rem, 1fr)); gap: 1rem;">';
        foreach ($stats as $label => $value) {
            $html .= '<div><dt class="text-xs text-gray-500 dark:text-gray-400">'.e($label).'</dt>'
                .'<dd class="mt-1 font-semibold tabular-nums text-gray-950 dark:text-white">'.e($value).'</dd></div>';
        }

        return new HtmlString($html.'</dl>');
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    private static function enum(mixed $value, string $class): ?\BackedEnum
    {
        if ($value instanceof $class) {
            return $value;
        }

        return blank($value) ? null : $class::tryFrom($value);
    }
}
