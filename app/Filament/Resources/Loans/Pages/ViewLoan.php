<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\Actions\RecordPaymentAction;
use App\Filament\Resources\Loans\LoanResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->loan_no.' · '.$this->getRecord()->borrower->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            RecordPaymentAction::make(),
            Action::make('printVoucher')
                ->label('Print voucher')
                ->icon('heroicon-m-document-text')
                ->color('gray')
                ->url(fn () => route('print.loan-voucher', $this->getRecord()), shouldOpenInNewTab: true),
            Action::make('print')
                ->label('Print statement')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->url(fn () => route('print.loan-statement', $this->getRecord()), shouldOpenInNewTab: true),
            EditAction::make()->label('Edit remarks')->color('gray'),
        ];
    }

    #[On('refresh-loan')]
    public function refreshLoan(): void
    {
        $this->getRecord()->refresh();
    }
}
