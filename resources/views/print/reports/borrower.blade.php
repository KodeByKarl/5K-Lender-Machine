@extends('print.layout', [
    'title' => 'Borrower Loan History',
    'period' => $borrower->reference_no.' · '.$borrower->full_name,
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $loans = $borrower->loans;
@endphp

@section('content')
    <dl class="meta">
        <div><dt>Borrower</dt><dd>{{ $borrower->full_name }}</dd></div>
        <div><dt>Reference no.</dt><dd>{{ $borrower->reference_no }}</dd></div>
        <div><dt>Area</dt><dd>{{ $borrower->area->name }}</dd></div>
        <div><dt>Contact</dt><dd>{{ $borrower->contact_no ?: '—' }}</dd></div>
        <div><dt>Address</dt><dd>{{ $borrower->address ?: '—' }}</dd></div>
        <div><dt>Loans</dt><dd>{{ $loans->count() }}</dd></div>
        <div><dt>Open balance</dt><dd>₱{{ $peso($loans->sum('balance')) }}</dd></div>
        <div><dt>Savings</dt><dd>{{ $borrower->savingsAccount ? '₱'.$peso($borrower->savingsAccount->balance) : 'No account' }}</dd></div>
    </dl>

    <h2>Loans</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 13%">Loan no.</th>
                <th>Plan / terms</th>
                <th style="width: 10%">Released</th>
                <th class="num" style="width: 11%">Amount</th>
                <th class="num" style="width: 11%">Payable</th>
                <th class="num" style="width: 11%">Paid</th>
                <th class="num" style="width: 11%">Balance</th>
                <th style="width: 8%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($loans as $loan)
                <tr>
                    <td class="nowrap">{{ $loan->loan_no }}</td>
                    <td>{{ $loan->plan?->name ?? 'Custom' }} <div class="muted">{{ $loan->installment_count }} × {{ strtolower($loan->payment_frequency->getLabel()) }}</div></td>
                    <td>{{ $loan->start_date->format('M d, Y') }}</td>
                    <td class="num">{{ $peso($loan->principal) }}</td>
                    <td class="num">{{ $peso($loan->total_payable) }}</td>
                    <td class="num">{{ $peso($loan->total_paid) }}</td>
                    <td class="num"><strong>{{ $peso($loan->balance) }}</strong></td>
                    <td><span class="tag {{ $loan->status->value }}">{{ $loan->status->getLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No loans.</td></tr>
            @endforelse
        </tbody>
    </table>

    @foreach ($loans->filter(fn ($l) => $l->payments->isNotEmpty()) as $loan)
        <h2>Payments · {{ $loan->loan_no }} <small>({{ $loan->payments->count() }})</small></h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 18%">Date</th>
                    <th style="width: 18%">Receipt</th>
                    <th>Remarks</th>
                    <th class="num" style="width: 16%">Amount</th>
                    <th class="num" style="width: 16%">Balance after</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($loan->payments as $p)
                    <tr>
                        <td>{{ $p->payment_date->format('M d, Y') }}</td>
                        <td>{{ $p->receipt_no ?: '—' }}</td>
                        <td class="muted">{{ $p->remarks }}</td>
                        <td class="num">{{ $peso($p->amount) }}</td>
                        <td class="num">{{ $peso($p->balance_after) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">Total paid</td>
                    <td class="num">{{ $peso($loan->payments->sum('amount')) }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @endforeach
@endsection
