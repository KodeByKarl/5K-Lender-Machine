@extends('print.layout', [
    'title' => 'Loan Statement',
    'period' => $loan->loan_no.' · as of '.now()->format('M d, Y'),
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $rate = rtrim(rtrim((string) $loan->interest_rate, '0'), '.');
@endphp

@section('content')
    <dl class="meta">
        <div><dt>Borrower</dt><dd>{{ $loan->borrower->full_name }}</dd></div>
        <div><dt>Reference no.</dt><dd>{{ $loan->borrower->reference_no }}</dd></div>
        <div><dt>Area</dt><dd>{{ $loan->area->name }}</dd></div>
        <div><dt>Status</dt><dd><span class="tag {{ $loan->status->value }}">{{ $loan->status->getLabel() }}</span></dd></div>
        <div><dt>Loan amount</dt><dd>₱{{ $peso($loan->principal) }}</dd></div>
        <div><dt>Interest</dt><dd>{{ $rate }}% {{ strtolower($loan->rate_basis->getLabel()) }} · {{ $loan->interest_method->getLabel() }}</dd></div>
        <div><dt>Terms</dt><dd>{{ $loan->installment_count }} × {{ strtolower($loan->payment_frequency->getLabel()) }} ({{ $loan->term }} {{ strtolower($loan->term_unit->getLabel()) }})</dd></div>
        <div><dt>Plan</dt><dd>{{ $loan->plan?->name ?? 'Custom' }}</dd></div>
        <div><dt>Released</dt><dd>{{ $loan->start_date->format('M d, Y') }}</dd></div>
        <div><dt>Maturity</dt><dd>{{ $loan->maturity_date->format('M d, Y') }}</dd></div>
        <div><dt>Total payable</dt><dd>₱{{ $peso($loan->total_payable) }}</dd></div>
        <div><dt>Balance</dt><dd>₱{{ $peso($loan->balance) }}</dd></div>
    </dl>

    <h2>Repayment schedule</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 6%">#</th>
                <th>Due date</th>
                <th class="num">Principal</th>
                <th class="num">Interest</th>
                <th class="num">Amount due</th>
                <th class="num">Paid</th>
                <th class="num">Balance after</th>
                <th style="width: 9%">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($loan->installments as $i)
                @php
                    $state = match (true) {
                        $i->isPaid() => ['paid', 'Paid'],
                        $i->due_date->lt(today()) => ['overdue', 'Late'],
                        (float) $i->amount_paid > 0 => ['active', 'Partial'],
                        default => ['', ''],
                    };
                @endphp
                <tr>
                    <td class="muted">{{ $i->number }}</td>
                    <td>{{ $i->due_date->format('D, M d, Y') }}</td>
                    <td class="num">{{ $peso($i->principal) }}</td>
                    <td class="num">{{ $peso($i->interest) }}</td>
                    <td class="num">{{ $peso($i->amount_due) }}</td>
                    <td class="num">{{ (float) $i->amount_paid ? $peso($i->amount_paid) : '' }}</td>
                    <td class="num muted">{{ $peso($i->remaining_balance) }}</td>
                    <td>@if ($state[1])<span class="tag {{ $state[0] }}">{{ $state[1] }}</span>@endif</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="2">Totals</td>
                <td class="num">{{ $peso($loan->installments->sum('principal')) }}</td>
                <td class="num">{{ $peso($loan->installments->sum('interest')) }}</td>
                <td class="num">{{ $peso($loan->installments->sum('amount_due')) }}</td>
                <td class="num">{{ $peso($loan->installments->sum('amount_paid')) }}</td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>

    @if ($loan->payments->isNotEmpty())
        <h2>Payment ledger <small>· each line matches the receipt with the same number</small></h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Receipt no.</th>
                    <th class="num">Amount</th>
                    <th class="num">Balance after</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($loan->payments as $p)
                    <tr>
                        <td>{{ $p->payment_date->format('M d, Y') }}</td>
                        <td>{{ $p->receipt_no ?: '—' }}</td>
                        <td class="num">{{ $peso($p->amount) }}</td>
                        <td class="num">{{ $peso($p->balance_after) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="signatures">
        <div>Borrower's signature</div>
        <div>Authorized representative</div>
    </div>
@endsection
