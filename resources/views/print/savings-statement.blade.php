@extends('print.layout', [
    'title' => 'Savings Ledger Statement',
    'period' => $from->format('M d, Y').' – '.$to->format('M d, Y'),
])

@use('App\Enums\SavingsTransactionType')

@php
    $peso = fn ($v) => '₱'.number_format((float) $v, 2);
    $deposits = $transactions->where('type', SavingsTransactionType::Deposit)->sum('amount');
    $withdrawals = $transactions->where('type', SavingsTransactionType::Withdrawal)->sum('amount');
    $closing = $transactions->last()?->running_balance ?? $opening;
@endphp

@section('filters')
    <label>From <input type="date" name="from" value="{{ request('from') }}"></label>
    <label>To <input type="date" name="to" value="{{ request('to', $to->toDateString()) }}"></label>
    <button type="submit">Apply</button>
@endsection

@section('content')
    <dl class="meta">
        <div><dt>Account holder</dt><dd>{{ $account->borrower->full_name }}</dd></div>
        <div><dt>Borrower ref.</dt><dd>{{ $account->borrower->reference_no }}</dd></div>
        <div><dt>Account no.</dt><dd>{{ $account->account_no }}</dd></div>
        <div><dt>Area</dt><dd>{{ $account->area->name }}</dd></div>
        <div><dt>Address</dt><dd>{{ $account->borrower->address ?: '—' }}</dd></div>
        <div><dt>Contact</dt><dd>{{ $account->borrower->contact_no ?: '—' }}</dd></div>
        <div><dt>Opened</dt><dd>{{ $account->opened_at->format('M d, Y') }}</dd></div>
        <div><dt>Current balance</dt><dd>{{ $peso($account->balance) }}</dd></div>
    </dl>

    <table>
        <thead>
            <tr>
                <th style="width: 13%">Date</th>
                <th style="width: 14%">Reference</th>
                <th>Particulars</th>
                <th class="num" style="width: 15%">Withdrawal (Dr)</th>
                <th class="num" style="width: 15%">Deposit (Cr)</th>
                <th class="num" style="width: 15%">Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening">
                <td>{{ $from->format('M d, Y') }}</td>
                <td></td>
                <td>Opening balance</td>
                <td></td>
                <td></td>
                <td class="num">{{ $peso($opening) }}</td>
            </tr>
            @forelse ($transactions as $t)
                <tr>
                    <td>{{ $t->transaction_date->format('M d, Y') }}</td>
                    <td>{{ $t->reference_no ?: '—' }}</td>
                    <td>{{ $t->remarks ?: $t->type->getLabel() }}</td>
                    <td class="num">{{ $t->type === SavingsTransactionType::Withdrawal ? $peso($t->amount) : '' }}</td>
                    <td class="num">{{ $t->type === SavingsTransactionType::Deposit ? $peso($t->amount) : '' }}</td>
                    <td class="num">{{ $peso($t->running_balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="text-align:center; padding:24px">No transactions in this period.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="3">Totals for the period</td>
                <td class="num">{{ $peso($withdrawals) }}</td>
                <td class="num">{{ $peso($deposits) }}</td>
                <td class="num">{{ $peso($closing) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="summary">
        <div><span>Opening</span><strong>{{ $peso($opening) }}</strong></div>
        <div><span>Deposits</span><strong>{{ $peso($deposits) }}</strong></div>
        <div><span>Withdrawals</span><strong>{{ $peso($withdrawals) }}</strong></div>
        <div><span>Closing balance</span><strong>{{ $peso($closing) }}</strong></div>
    </div>

    <div class="signatures">
        <div>Prepared by</div>
        <div>Received by (Account holder)</div>
    </div>
@endsection
