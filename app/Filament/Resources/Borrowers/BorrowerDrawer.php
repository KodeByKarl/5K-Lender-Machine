<?php

namespace App\Filament\Resources\Borrowers;

use App\Filament\Resources\Borrowers\Pages\ViewBorrower;
use App\Filament\Resources\Borrowers\RelationManagers\LoansRelationManager;
use App\Filament\Resources\Borrowers\Schemas\BorrowerInfolist;
use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Resources\SavingsAccounts\SavingsAccountResource;
use App\Models\Borrower;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;

/** Opens a borrower and their loan history in a drawer on the right instead of a new page. */
class BorrowerDrawer
{
    public static function make(): ViewAction
    {
        return ViewAction::make()
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(fn (Borrower $record) => $record->full_name)
            ->modalDescription(fn (Borrower $record) => $record->reference_no.' · '.$record->area->name)
            ->schema(fn (Schema $schema) => $schema->components([
                ...BorrowerInfolist::components(),
                Livewire::make(LoansRelationManager::class, fn (Borrower $record) => [
                    'ownerRecord' => $record,
                    'pageClass' => ViewBorrower::class,
                ])->key(fn (Borrower $record) => 'borrower-drawer-loans-'.$record->getKey()),
            ]))
            ->extraModalFooterActions([
                Action::make('newLoan')
                    ->label('New loan')
                    ->icon('heroicon-m-plus')
                    ->url(fn (Borrower $record) => LoanResource::getUrl('create', ['borrower' => $record->getKey()])),
                Action::make('savings')
                    ->label(fn (Borrower $record) => $record->savingsAccount ? 'Savings' : 'Open savings')
                    ->icon('heroicon-m-wallet')
                    ->color('gray')
                    ->url(fn (Borrower $record) => $record->savingsAccount
                        ? SavingsAccountResource::getUrl('view', ['record' => $record->savingsAccount])
                        : SavingsAccountResource::getUrl('create', ['borrower' => $record->getKey()])),
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->color('gray')
                    ->url(fn (Borrower $record) => BorrowerResource::getUrl('edit', ['record' => $record])),
                Action::make('openPage')
                    ->label('Open full page')
                    ->icon('heroicon-m-arrows-pointing-out')
                    ->color('gray')
                    ->url(fn (Borrower $record) => BorrowerResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
