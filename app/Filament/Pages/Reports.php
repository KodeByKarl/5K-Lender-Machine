<?php

namespace App\Filament\Pages;

use App\Models\Area;
use App\Models\Borrower;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.reports';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from' => today()->startOfMonth()->toDateString(),
            'to' => today()->toDateString(),
            'date' => today()->toDateString(),
        ]);
    }

    public function getSubheading(): ?string
    {
        return 'Each report opens in a new tab, ready to view on screen or print.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Filters')
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                    ->schema([
                        Select::make('area_id')
                            ->label('Area')
                            ->placeholder('All areas (consolidated)')
                            ->options(fn () => Area::orderBy('name')->pluck('name', 'id'))
                            ->visible(fn () => auth()->user()?->isAdmin())
                            ->live(),
                        DatePicker::make('date')->label('Collection date')->native(false)->live(),
                        DatePicker::make('from')->label('Period from')->native(false)->live(),
                        DatePicker::make('to')->label('Period to')->native(false)->live(),
                    ]),
            ]);
    }

    /** @return list<array{title: string, description: string, icon: string, url: ?string, uses: string, extra?: string}> */
    public function getReports(): array
    {
        $area = ['area' => $this->data['area_id'] ?? null];
        $period = [...$area, 'from' => $this->data['from'] ?? null, 'to' => $this->data['to'] ?? null];
        $url = fn (string $name, array $params) => route('print.reports.'.$name, array_filter($params));

        return [
            [
                'title' => 'Daily collection sheet',
                'description' => 'Every open loan with the amount due on the collection date plus arrears, with a blank column for collectors.',
                'icon' => 'heroicon-o-clipboard-document-list',
                'uses' => 'Area · collection date',
                'url' => $url('collection-sheet', [...$area, 'date' => $this->data['date'] ?? null]),
            ],
            [
                'title' => 'Payment history',
                'description' => 'All payments received in the period, with daily subtotals and who received them.',
                'icon' => 'heroicon-o-banknotes',
                'uses' => 'Area · period',
                'url' => $url('payments', $period),
            ],
            [
                'title' => 'Receipt register',
                'description' => 'Every receipt number in order, with missing numbers flagged. Check it against the paper booklets.',
                'icon' => 'heroicon-o-receipt-percent',
                'uses' => 'Area · period',
                'url' => $url('receipt-register', $period),
            ],
            [
                'title' => 'Outstanding loans',
                'description' => 'Every loan that is not yet fully paid, with amounts paid and remaining balance.',
                'icon' => 'heroicon-o-scale',
                'uses' => 'Area',
                'url' => $url('outstanding', $area),
            ],
            [
                'title' => 'Overdue loans',
                'description' => 'Loans with missed installments, sorted by how many days late, with the past-due amount.',
                'icon' => 'heroicon-o-exclamation-triangle',
                'uses' => 'Area',
                'url' => $url('overdue', $area),
            ],
            [
                'title' => 'Savings summary',
                'description' => 'Savings per borrower with deposits and withdrawals in the period, and totals per Area.',
                'icon' => 'heroicon-o-wallet',
                'uses' => 'Area · period',
                'url' => $url('savings', $period),
            ],
        ];
    }

    public function borrowerHistoryForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('borrower_id')
                    ->hiddenLabel()
                    ->placeholder('Search borrower…')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => Borrower::query()
                        ->where(fn ($q) => $q->where('reference_no', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"))
                        ->limit(20)->get()
                        ->mapWithKeys(fn (Borrower $b) => [$b->id => $b->reference_no.' · '.$b->full_name]))
                    ->getOptionLabelUsing(fn ($value) => ($b = Borrower::find($value)) ? $b->reference_no.' · '.$b->full_name : null)
                    ->live(),
            ]);
    }

    public function getBorrowerHistoryUrl(): ?string
    {
        $id = $this->data['borrower_id'] ?? null;

        return $id ? route('print.reports.borrower', $id) : null;
    }
}
