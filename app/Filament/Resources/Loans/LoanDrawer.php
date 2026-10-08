<?php

namespace App\Filament\Resources\Loans;

use App\Filament\Resources\Loans\Actions\RecordPaymentAction;
use App\Filament\Resources\Loans\Pages\ViewLoan;
use App\Filament\Resources\Loans\RelationManagers\InstallmentsRelationManager;
use App\Filament\Resources\Loans\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Loans\Schemas\LoanInfolist;
use App\Models\Loan;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/** Opens a loan in a drawer on the right instead of a new page. */
class LoanDrawer
{
    public static function make(): ViewAction
    {
        $relation = fn (string $manager) => Livewire::make($manager, fn (Loan $record) => [
            'ownerRecord' => $record,
            'pageClass' => ViewLoan::class,
        ])->key(fn (Loan $record) => 'loan-drawer-'.class_basename($manager).'-'.$record->getKey());

        return ViewAction::make()
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (Loan $record) => $record->loan_no.' · '.$record->borrower->full_name)
            ->modalDescription(fn (Loan $record) => $record->area->name.' · '.($record->plan?->name ?? 'Custom terms'))
            ->schema(fn (Schema $schema) => $schema->components([
                ...LoanInfolist::components(inDrawer: true),
                Tabs::make('loan')
                    ->contained(false)
                    ->tabs([
                        Tab::make('Payment history')->icon('heroicon-m-banknotes')->schema([$relation(PaymentsRelationManager::class)]),
                        Tab::make('Repayment schedule')->icon('heroicon-m-calendar-days')->schema([$relation(InstallmentsRelationManager::class)]),
                    ]),
            ]))
            ->extraModalFooterActions([
                RecordPaymentAction::make(),
                Action::make('printVoucher')
                    ->label('Print voucher')
                    ->icon('heroicon-m-document-text')
                    ->color('gray')
                    ->url(fn (Loan $record) => route('print.loan-voucher', $record), shouldOpenInNewTab: true),
                Action::make('printStatement')
                    ->label('Print statement')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (Loan $record) => route('print.loan-statement', $record), shouldOpenInNewTab: true),
                Action::make('openPage')
                    ->label('Open full page')
                    ->icon('heroicon-m-arrows-pointing-out')
                    ->color('gray')
                    ->url(fn (Loan $record) => LoanResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
