<?php

namespace App\Filament\Resources\SavingsAccounts\Pages;

use App\Filament\Resources\SavingsAccounts\SavingsAccountResource;
use App\Models\Borrower;
use App\Services\SavingsService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSavingsAccount extends CreateRecord
{
    protected static string $resource = SavingsAccountResource::class;

    protected static ?string $title = 'Open savings account';

    protected function handleRecordCreation(array $data): Model
    {
        return app(SavingsService::class)->open(Borrower::findOrFail($data['borrower_id']), $data['opened_at']);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
