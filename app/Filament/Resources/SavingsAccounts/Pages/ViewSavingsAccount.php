<?php

namespace App\Filament\Resources\SavingsAccounts\Pages;

use App\Filament\Resources\SavingsAccounts\Actions\SavingsTransactionAction;
use App\Filament\Resources\SavingsAccounts\SavingsAccountResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewSavingsAccount extends ViewRecord
{
    protected static string $resource = SavingsAccountResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->account_no.' · '.$this->getRecord()->borrower->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            SavingsTransactionAction::deposit(),
            SavingsTransactionAction::withdraw(),
            Action::make('print')
                ->label('Print statement')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->url(fn () => route('print.savings-statement', $this->getRecord()), shouldOpenInNewTab: true),
        ];
    }

    #[On('refresh-savings')]
    public function refreshAccount(): void
    {
        $this->getRecord()->refresh();
    }
}
