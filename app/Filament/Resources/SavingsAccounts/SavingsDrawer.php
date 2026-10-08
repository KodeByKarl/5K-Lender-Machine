<?php

namespace App\Filament\Resources\SavingsAccounts;

use App\Filament\Resources\SavingsAccounts\Actions\SavingsTransactionAction;
use App\Filament\Resources\SavingsAccounts\Pages\ViewSavingsAccount;
use App\Filament\Resources\SavingsAccounts\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\SavingsAccounts\Schemas\SavingsAccountInfolist;
use App\Models\SavingsAccount;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/** Opens a savings account and its ledger in a drawer on the right instead of a new page. */
class SavingsDrawer
{
    public static function make(): ViewAction
    {
        return ViewAction::make()
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (SavingsAccount $record) => $record->account_no.' · '.$record->borrower->full_name)
            ->modalDescription(fn (SavingsAccount $record) => $record->area->name)
            ->schema(fn (Schema $schema) => $schema->components([
                ...SavingsAccountInfolist::components(),
                Livewire::make(TransactionsRelationManager::class, fn (SavingsAccount $record) => [
                    'ownerRecord' => $record,
                    'pageClass' => ViewSavingsAccount::class,
                ])->key(fn (SavingsAccount $record) => 'savings-drawer-ledger-'.$record->getKey()),
            ]))
            ->extraModalFooterActions([
                SavingsTransactionAction::deposit(),
                SavingsTransactionAction::withdraw(),
                Action::make('printStatement')
                    ->label('Print statement')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (SavingsAccount $record) => route('print.savings-statement', $record), shouldOpenInNewTab: true),
                Action::make('openPage')
                    ->label('Open full page')
                    ->icon('heroicon-m-arrows-pointing-out')
                    ->color('gray')
                    ->url(fn (SavingsAccount $record) => SavingsAccountResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
