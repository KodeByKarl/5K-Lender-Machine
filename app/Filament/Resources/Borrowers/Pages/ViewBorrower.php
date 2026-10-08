<?php

namespace App\Filament\Resources\Borrowers\Pages;

use App\Filament\Resources\Borrowers\BorrowerResource;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\SavingsAccounts\SavingsAccountResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBorrower extends ViewRecord
{
    protected static string $resource = BorrowerResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->full_name;
    }

    protected function getHeaderActions(): array
    {
        $savings = $this->getRecord()->savingsAccount;

        return [
            Action::make('newLoan')
                ->label('New loan')
                ->icon('heroicon-m-plus')
                ->url(LoanResource::getUrl('create', ['borrower' => $this->getRecord()->id])),
            $savings
                ? Action::make('savings')
                    ->label('Savings · ₱'.number_format((float) $savings->balance, 2))
                    ->icon('heroicon-m-wallet')
                    ->color('gray')
                    ->url(SavingsAccountResource::getUrl('view', ['record' => $savings]))
                : Action::make('openSavings')
                    ->label('Open savings')
                    ->icon('heroicon-m-wallet')
                    ->color('gray')
                    ->url(SavingsAccountResource::getUrl('create', ['borrower' => $this->getRecord()->id])),
            EditAction::make()->color('gray'),
        ];
    }
}
