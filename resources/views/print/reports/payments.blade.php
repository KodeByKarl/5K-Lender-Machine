@extends('print.layout', [
    'title' => 'Payment History',
    'period' => $from->format('M d, Y').' – '.$to->format('M d, Y').' · '.$areaName,
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $byDay = $payments->groupBy(fn ($p) => $p->payment_date->toDateString());
@endphp

@section('filters')
    @include('print.partials.area-filter')
    <label>From <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
    <label>To <input type="date" name="to" value="{{ $to->toDateString() }}"></label>
    <button type="submit">Apply</button>
@endsection

@section('content')
    <dl class="meta">
        <div><dt>Payments</dt><dd>{{ $payments->count() }}</dd></div>
        <div><dt>Total collected</dt><dd>₱{{ $peso($payments->sum('amount')) }}</dd></div>
        <div><dt>Days with collections</dt><dd>{{ $byDay->count() }}</dd></div>
        <div><dt>Average per day</dt><dd>₱{{ $peso($byDay->count() ? $payments->sum('amount') / $byDay->count() : 0) }}</dd></div>
    </dl>

    <table>
        <thead>
            <tr>
                <th style="width: 12%">Date</th>
                <th style="width: 11%">Receipt</th>
                <th>Borrower</th>
                <th style="width: 13%">Loan no.</th>
                @if (! $areaId)<th style="width: 10%">Area</th>@endif
                <th style="width: 13%">Received by</th>
                <th class="num" style="width: 11%">Amount</th>
                <th class="num" style="width: 11%">Balance after</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byDay as $day => $dayPayments)
                @foreach ($dayPayments as $p)
                    <tr>
                        <td>{{ $loop->first ? $p->payment_date->format('M d, Y') : '' }}</td>
                        <td>{{ $p->receipt_no ?: '—' }}</td>
                        <td>{{ $p->loan->borrower->full_name }}</td>
                        <td class="nowrap">{{ $p->loan->loan_no }}</td>
                        @if (! $areaId)<td>{{ $p->area->name }}</td>@endif
                        <td class="muted">{{ $p->receiver?->name ?? '—' }}</td>
                        <td class="num">{{ $peso($p->amount) }}</td>
                        <td class="num muted">{{ $peso($p->balance_after) }}</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="{{ $areaId ? 5 : 6 }}">Subtotal · {{ \Carbon\Carbon::parse($day)->format('M d, Y') }} ({{ $dayPayments->count() }})</td>
                    <td class="num">{{ $peso($dayPayments->sum('amount')) }}</td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No payments in this period.</td></tr>
            @endforelse
            @if ($payments->isNotEmpty())
                <tr class="total">
                    <td colspan="{{ $areaId ? 5 : 6 }}">Total for the period</td>
                    <td class="num">{{ $peso($payments->sum('amount')) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
