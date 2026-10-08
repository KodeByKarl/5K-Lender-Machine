<?php

namespace App\Filament\Resources\Loans\Actions;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

/** "Record payment" modal, usable on the loan page header and in the payments table. */
class RecordPaymentAction
{
    public static function make(): Action
    {
        // The loan comes from the record (inside the loan drawer), the relation manager's owner,
        // or the loan page, depending on where the action is used.
        $loan = fn (Component $livewire, ?Model $record = null): Loan => match (true) {
            $record instanceof Loan => $record,
            method_exists($livewire, 'getOwnerRecord') => $livewire->getOwnerRecord(),
            default => $livewire->getRecord(),
        };

        return Action::make('recordPayment')
            ->label('Record payment')
            ->icon('heroicon-m-banknotes')
            ->color('primary')
            ->modalWidth('lg')
            ->modalDescription(fn (Component $livewire, ?Model $record = null) => 'Remaining balance: ₱'.number_format((float) $loan($livewire, $record)->balance, 2))
            ->hidden(fn (Component $livewire, ?Model $record = null) => $loan($livewire, $record)->status === LoanStatus::Paid)
            ->schema(fn (Component $livewire, ?Model $record = null) => [
                Grid::make(2)->schema([
                    TextInput::make('receipt_no')
                        ->label('Receipt no.')
                        ->helperText('Copy the number printed on the receipt booklet.')
                        ->required()
                        ->maxLength(30)
                        ->autofocus(),
                    DatePicker::make('payment_date')
                        ->label('Payment date')
                        ->native(false)
                        ->default(today())
                        ->minDate(app(LoanService::class)->latestPayment($loan($livewire, $record))?->payment_date ?? $loan($livewire, $record)->start_date)
                        ->maxDate(today())
                        ->required(),
                    TextInput::make('amount')
                        ->prefix('₱')
                        ->numeric()
                        ->minValue(0.01)
                        ->maxValue((float) $loan($livewire, $record)->balance)
                        ->default(fn () => static::nextDue($loan($livewire, $record)))
                        ->required(),
                ]),
                Textarea::make('remarks')->rows(2),
            ])
            ->action(function (array $data, Component $livewire, ?Model $record = null) use ($loan) {
                $payment = app(LoanService::class)->recordPayment($loan($livewire, $record), $data);

                Notification::make()
                    ->success()
                    ->title('Receipt '.$payment->receipt_no.': ₱'.number_format((float) $payment->amount, 2).' recorded')
                    ->body('New balance: ₱'.number_format((float) $payment->balance_after, 2))
                    ->actions([
                        Action::make('printReceipt')
                            ->label('Print receipt')
                            ->icon('heroicon-m-printer')
                            ->button()
                            ->url(route('print.receipts.payment', $payment), shouldOpenInNewTab: true),
                    ])
                    ->persistent()
                    ->send();

                $livewire->dispatch('refresh-loan');
            });
    }

    /** Amount still due on the next unpaid installment, as a convenient default. */
    private static function nextDue(Loan $loan): ?string
    {
        $next = $loan->installments()->whereColumn('amount_paid', '<', 'amount_due')->orderBy('number')->first();

        return $next ? number_format((float) $next->amount_due - (float) $next->amount_paid, 2, '.', '') : null;
    }
}
