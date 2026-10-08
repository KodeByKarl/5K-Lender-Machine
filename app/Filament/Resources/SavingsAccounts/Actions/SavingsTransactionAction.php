<?php

namespace App\Filament\Resources\SavingsAccounts\Actions;

use App\Enums\SavingsTransactionType;
use App\Models\SavingsAccount;
use App\Services\SavingsService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/** Deposit and Withdraw modals, usable on the account page header and in the ledger table. */
class SavingsTransactionAction
{
    public static function deposit(): Action
    {
        return static::make(SavingsTransactionType::Deposit)
            ->icon('heroicon-m-arrow-down-tray')
            ->color('primary');
    }

    public static function withdraw(): Action
    {
        return static::make(SavingsTransactionType::Withdrawal)
            ->icon('heroicon-m-arrow-up-tray')
            ->color('gray')
            ->disabled(fn (Component $livewire, ?Model $record = null) => (float) static::account($livewire, $record)->balance <= 0);
    }

    private static function make(SavingsTransactionType $type): Action
    {
        $isWithdrawal = $type === SavingsTransactionType::Withdrawal;

        return Action::make($type->value)
            ->label($isWithdrawal ? 'Withdraw' : 'Deposit')
            ->modalHeading($isWithdrawal ? 'Record withdrawal' : 'Record deposit')
            ->modalWidth('lg')
            ->modalDescription(fn (Component $livewire, ?Model $record = null) => 'Available balance: ₱'.number_format((float) static::account($livewire, $record)->balance, 2))
            ->schema(fn (Component $livewire, ?Model $record = null) => [
                Grid::make(2)->schema([
                    TextInput::make('reference_no')
                        ->label($isWithdrawal ? 'Voucher no.' : 'Receipt no.')
                        ->helperText($isWithdrawal ? 'Optional.' : 'Copy the number printed on the receipt booklet.')
                        ->required(! $isWithdrawal)
                        ->maxLength(30)
                        ->autofocus(),
                    DatePicker::make('transaction_date')
                        ->label('Date')
                        ->native(false)
                        ->default(today())
                        ->minDate(app(SavingsService::class)->latest(static::account($livewire, $record))?->transaction_date ?? static::account($livewire, $record)->opened_at)
                        ->maxDate(today())
                        ->required(),
                    TextInput::make('amount')
                        ->prefix('₱')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue($isWithdrawal ? (float) static::account($livewire, $record)->balance : null)
                        ->required(),
                ]),
                Textarea::make('remarks')->rows(2),
            ])
            ->action(function (array $data, Component $livewire, ?Model $record = null) use ($type, $isWithdrawal) {
                $service = app(SavingsService::class);
                $account = static::account($livewire, $record);

                $transaction = $isWithdrawal ? $service->withdraw($account, $data) : $service->deposit($account, $data);

                Notification::make()
                    ->success()
                    ->title($type->getLabel().' of ₱'.number_format((float) $transaction->amount, 2).' recorded')
                    ->body('New balance: ₱'.number_format((float) $account->refresh()->balance, 2))
                    ->actions([
                        Action::make('printReceipt')
                            ->label($isWithdrawal ? 'Print withdrawal slip' : 'Print receipt')
                            ->icon('heroicon-m-printer')
                            ->button()
                            ->url(route('print.receipts.savings', $transaction), shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();

                $livewire->dispatch('refresh-savings');
            });
    }

    /** The account comes from the record (inside the drawer), the ledger's owner, or the account page. */
    private static function account(Component $livewire, ?Model $record = null): SavingsAccount
    {
        return match (true) {
            $record instanceof SavingsAccount => $record,
            method_exists($livewire, 'getOwnerRecord') => $livewire->getOwnerRecord(),
            default => $livewire->getRecord(),
        };
    }
}
