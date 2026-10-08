<?php

namespace App\Filament\Resources\Loans\RelationManagers;

use App\Models\Payment;
use App\Services\LoanService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payment history';

    #[On('refresh-loan')]
    public function refreshTable(): void
    {
        // Re-render after a payment changes the loan.
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $latestId = fn () => app(LoanService::class)->latestPayment($this->getOwnerRecord())?->id;

        return $table
            ->recordTitleAttribute('receipt_no')
            ->defaultSort(fn ($query) => $query->orderByDesc('payment_date')->orderByDesc('id'))
            ->modifyQueryUsing(fn ($query) => $query->with('receiver'))
            ->columns([
                TextColumn::make('payment_date')->label('Date')->date('M d, Y'),
                TextColumn::make('receipt_no')->label('Receipt no.')->weight('semibold')->searchable()->placeholder('—'),
                TextColumn::make('amount')->money('PHP')->alignEnd()->weight('medium')
                    ->summarize(Sum::make()->money('PHP')->label('Total')),
                TextColumn::make('balance_after')->label('Balance after')->money('PHP')->alignEnd()->color('gray'),
                TextColumn::make('receiver.name')->label('Received by')->placeholder('—'),
                TextColumn::make('remarks')->placeholder('—')->limit(40)->toggleable(),
            ])
            // "Record payment" lives in the page header and the drawer footer, not here.
            ->recordActions([
                Action::make('receipt')
                    ->label('Print receipt')
                    ->hiddenLabel()
                    ->tooltip('Print receipt')
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (Payment $record) => route('print.receipts.payment', $record), shouldOpenInNewTab: true),
                Action::make('editDetails')
                    ->label('Fix receipt no.')
                    ->hiddenLabel()
                    ->tooltip('Fix receipt no.')
                    ->icon('heroicon-m-pencil-square')
                    ->color('gray')
                    ->modalWidth('md')
                    ->modalDescription('Correct a mistyped receipt number or remarks. The amount and balances do not change.')
                    ->fillForm(fn (Payment $record) => $record->only(['receipt_no', 'remarks']))
                    ->schema([
                        TextInput::make('receipt_no')->label('Receipt no.')->required()->maxLength(30),
                        Textarea::make('remarks')->rows(2),
                    ])
                    ->action(fn (Payment $record, array $data) => app(LoanService::class)->updatePaymentDetails($record, $data))
                    ->successNotificationTitle('Receipt details updated'),
                DeleteAction::make()
                    ->hiddenLabel()
                    ->tooltip('Delete (latest payment only)')
                    ->visible(fn (Payment $record) => $record->id === $latestId())
                    ->modalDescription('Only the latest payment can be deleted. The loan balance and schedule will be recalculated, and this is recorded in the audit log.')
                    ->using(fn (Payment $record) => app(LoanService::class)->deletePayment($record))
                    ->after(fn () => $this->dispatch('refresh-loan')),
            ]);
    }
}
