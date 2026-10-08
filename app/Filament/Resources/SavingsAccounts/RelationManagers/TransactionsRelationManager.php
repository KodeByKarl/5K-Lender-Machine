<?php

namespace App\Filament\Resources\SavingsAccounts\RelationManagers;

use App\Enums\SavingsTransactionType;
use App\Models\SavingsTransaction;
use App\Services\SavingsService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Savings ledger';

    #[On('refresh-savings')]
    public function refreshTable(): void
    {
        // Re-render after a deposit or withdrawal.
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $peso = fn ($state) => '₱'.number_format((float) $state, 2);
        $latestId = fn () => app(SavingsService::class)->latest($this->getOwnerRecord())?->id;

        return $table
            ->recordTitleAttribute('reference_no')
            ->modifyQueryUsing(fn ($query) => $query->with('recorder')->orderBy('transaction_date')->orderBy('id'))
            ->paginated([25, 50, 100, 'all'])
            ->columns([
                TextColumn::make('transaction_date')->label('Date')->date('M d, Y'),
                TextColumn::make('reference_no')->label('Receipt / voucher')->weight('semibold')->searchable()->placeholder('—'),
                TextColumn::make('remarks')->placeholder('—')->limit(40)->wrap(),
                TextColumn::make('withdrawal')
                    ->label('Withdrawal (Dr)')
                    ->state(fn (SavingsTransaction $r) => $r->type === SavingsTransactionType::Withdrawal ? $r->amount : null)
                    ->formatStateUsing($peso)
                    ->placeholder('')
                    ->alignEnd()
                    ->color('warning'),
                TextColumn::make('deposit')
                    ->label('Deposit (Cr)')
                    ->state(fn (SavingsTransaction $r) => $r->type === SavingsTransactionType::Deposit ? $r->amount : null)
                    ->formatStateUsing($peso)
                    ->placeholder('')
                    ->alignEnd()
                    ->color('success'),
                TextColumn::make('running_balance')
                    ->label('Balance')
                    ->money('PHP')
                    ->alignEnd()
                    ->weight('semibold'),
                TextColumn::make('recorder.name')->label('Recorded by')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(SavingsTransactionType::class),
            ])
            // Deposit, Withdraw, and Print statement live in the page header and the drawer footer.
            ->recordActions([
                Action::make('receipt')
                    ->label(fn (SavingsTransaction $record) => $record->type === SavingsTransactionType::Deposit ? 'Print receipt' : 'Print withdrawal slip')
                    ->hiddenLabel()
                    ->tooltip(fn (SavingsTransaction $record) => $record->type === SavingsTransactionType::Deposit ? 'Print receipt' : 'Print withdrawal slip')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (SavingsTransaction $record) => route('print.receipts.savings', $record), shouldOpenInNewTab: true),
                Action::make('editDetails')
                    ->label('Fix receipt no.')
                    ->hiddenLabel()
                    ->tooltip('Fix receipt no.')
                    ->icon('heroicon-m-pencil-square')
                    ->color('gray')
                    ->modalWidth('md')
                    ->modalDescription('Correct a mistyped receipt / voucher number or remarks. The amount and balances do not change.')
                    ->fillForm(fn (SavingsTransaction $record) => $record->only(['reference_no', 'remarks']))
                    ->schema(fn (SavingsTransaction $record) => [
                        TextInput::make('reference_no')
                            ->label($record->type === SavingsTransactionType::Deposit ? 'Receipt no.' : 'Voucher no.')
                            ->required($record->type === SavingsTransactionType::Deposit)
                            ->maxLength(30),
                        Textarea::make('remarks')->rows(2),
                    ])
                    ->action(fn (SavingsTransaction $record, array $data) => app(SavingsService::class)->updateDetails($record, $data))
                    ->successNotificationTitle('Receipt details updated'),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete (latest only)')
                    ->visible(fn (SavingsTransaction $record) => $record->id === $latestId())
                    ->modalDescription('Only the latest transaction can be deleted. This is recorded in the audit log.')
                    ->using(fn (SavingsTransaction $record) => app(SavingsService::class)->delete($record))
                    ->after(fn () => $this->dispatch('refresh-savings')),
            ]);
    }
}
