<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(LoanService::class)->create($data);
    }

    protected function getCreatedNotification(): ?Notification
    {
        $loan = $this->getRecord();

        return Notification::make()
            ->success()
            ->title('Loan '.$loan->loan_no.' created · Voucher '.$loan->voucher_no)
            ->body('Release ₱'.number_format((float) $loan->net_proceeds, 2).' to the borrower.')
            ->actions([
                Action::make('printVoucher')
                    ->label('Print voucher')
                    ->icon('heroicon-m-printer')
                    ->button()
                    ->url(route('print.loan-voucher', $loan), shouldOpenInNewTab: true),
            ])
            ->persistent();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
