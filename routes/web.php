<?php

use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::middleware('auth')->prefix('print')->name('print.')->group(function () {
    Route::get('savings/{account}/statement', [PrintController::class, 'savingsStatement'])->name('savings-statement');
    Route::get('loans/{loan}/statement', [PrintController::class, 'loanStatement'])->name('loan-statement');
    Route::get('loans/{loan}/voucher', [PrintController::class, 'loanVoucher'])->name('loan-voucher');
    Route::get('receipts/payments/{payment}', [PrintController::class, 'paymentReceipt'])->name('receipts.payment');
    Route::get('receipts/savings/{transaction}', [PrintController::class, 'savingsReceipt'])->name('receipts.savings');

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('collection-sheet', [PrintController::class, 'collectionSheet'])->name('collection-sheet');
        Route::get('payments', [PrintController::class, 'payments'])->name('payments');
        Route::get('receipt-register', [PrintController::class, 'receiptRegister'])->name('receipt-register');
        Route::get('outstanding', [PrintController::class, 'outstanding'])->name('outstanding');
        Route::get('overdue', [PrintController::class, 'overdue'])->name('overdue');
        Route::get('savings', [PrintController::class, 'savings'])->name('savings');
        Route::get('borrowers/{borrower}', [PrintController::class, 'borrowerHistory'])->name('borrower');
    });
});

// TEMP-SCREENSHOT: remove after design review
if (app()->isLocal()) {
    Route::get('/__shot/{user}/{path?}', function (\App\Models\User $user, ?string $path = null) {
        auth()->login($user);
        return redirect('/'.str_replace('~', '/', $path ?? 'admin').(request()->getQueryString() ? '?'.request()->getQueryString() : ''));
    })->middleware('web');
}
