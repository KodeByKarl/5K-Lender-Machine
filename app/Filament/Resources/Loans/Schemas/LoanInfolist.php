<?php

namespace App\Filament\Resources\Loans\Schemas;

use App\Filament\Resources\Borrowers\BorrowerResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LoanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(static::components())->columns(1);
    }

    /** Shared by the loan page and the loan drawer. */
    public static function components(bool $inDrawer = false): array
    {
        $columns = ['default' => 2, 'md' => 4];

        return [
            Section::make()
                ->columns($columns)
                ->schema([
                    TextEntry::make('balance')
                        ->label('Remaining balance')
                        ->money('PHP')
                        ->size('lg')
                        ->weight('bold'),
                    TextEntry::make('total_paid')->label('Total paid')->money('PHP')->size('lg'),
                    TextEntry::make('total_payable')->label('Total payable')->money('PHP')->size('lg'),
                    TextEntry::make('status')->badge(),
                ]),

            Section::make('Loan details')
                ->columns($columns)
                ->collapsible()
                ->collapsed($inDrawer)
                ->schema([
                    TextEntry::make('borrower.full_name')
                        ->label('Borrower')
                        ->url(fn ($record) => BorrowerResource::getUrl('view', ['record' => $record->borrower_id]))
                        ->color('primary'),
                    TextEntry::make('borrower.reference_no')->label('Ref no.'),
                    TextEntry::make('area.name')->label('Area')->badge()->color('gray'),
                    TextEntry::make('loan_no')->label('Loan no.')->copyable(),
                    TextEntry::make('plan.name')->label('Loan plan')->placeholder('Custom terms'),
                    TextEntry::make('principal')->label('Loan amount')->money('PHP'),
                    TextEntry::make('interest_rate')
                        ->label('Interest')
                        ->formatStateUsing(fn ($state, $record) => rtrim(rtrim((string) $state, '0'), '.').'% '.strtolower($record->rate_basis->getLabel())),
                    TextEntry::make('interest_method')->label('Method'),
                    TextEntry::make('total_interest')->label('Total interest')->money('PHP'),
                    TextEntry::make('payment_frequency')
                        ->label('Payments')
                        ->formatStateUsing(fn ($state, $record) => $record->installment_count.' × '.strtolower($state->getLabel())),
                    TextEntry::make('term')
                        ->label('Term')
                        ->formatStateUsing(fn ($state, $record) => $state.' '.strtolower($record->term_unit->getLabel())),
                    TextEntry::make('start_date')->label('Release date')->date('M d, Y'),
                    TextEntry::make('maturity_date')->label('Maturity')->date('M d, Y'),
                    TextEntry::make('remarks')->placeholder('—')->columnSpanFull(),
                ]),

            Section::make('Release')
                ->columns($columns)
                ->collapsible()
                ->collapsed($inDrawer)
                ->schema([
                    TextEntry::make('voucher_no')->label('Voucher no.')->weight('bold')->placeholder('—'),
                    TextEntry::make('release_method')
                        ->label('Released through')
                        ->formatStateUsing(fn ($state, $record) => ucfirst((string) $state).($record->release_reference ? ' · '.$record->release_reference : '')),
                    TextEntry::make('total_charges')->label('Release charges')->money('PHP'),
                    TextEntry::make('net_proceeds')->label('Net proceeds')->money('PHP')->weight('bold'),
                    TextEntry::make('charges')
                        ->label('Charges')
                        ->state(fn ($record) => collect($record->charges ?? [])->map(fn ($c) => $c['name'].': ₱'.number_format((float) $c['amount'], 2))->all())
                        ->listWithLineBreaks()
                        ->placeholder('None')
                        ->columnSpan(2),
                    TextEntry::make('required_savings')->label('Required savings')->money('PHP')->placeholder('—'),
                    TextEntry::make('releaser.name')->label('Prepared by')->placeholder('—'),
                ]),
        ];
    }
}
