@extends('print.layout', [
    'title' => 'Outstanding Loans',
    'period' => 'As of '.now()->format('M d, Y').' · '.$areaName,
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $byArea = $loans->groupBy('area_id');
@endphp

@section('filters')
    @include('print.partials.area-filter')
    @if ($areas->isNotEmpty())<button type="submit">Apply</button>@endif
@endsection

@section('content')
    <dl class="meta">
        <div><dt>Open loans</dt><dd>{{ $loans->count() }}</dd></div>
        <div><dt>Amount released</dt><dd>₱{{ $peso($loans->sum('principal')) }}</dd></div>
        <div><dt>Total paid</dt><dd>₱{{ $peso($loans->sum('total_paid')) }}</dd></div>
        <div><dt>Outstanding balance</dt><dd>₱{{ $peso($loans->sum('balance')) }}</dd></div>
    </dl>

    <table>
        <thead>
            <tr>
                <th style="width: 12%">Loan no.</th>
                <th>Borrower</th>
                <th style="width: 10%">Released</th>
                <th style="width: 10%">Maturity</th>
                <th class="num" style="width: 11%">Amount</th>
                <th class="num" style="width: 11%">Payable</th>
                <th class="num" style="width: 11%">Paid</th>
                <th class="num" style="width: 11%">Balance</th>
                <th style="width: 8%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byArea as $areaLoans)
                <tr class="group"><td colspan="9">{{ $areaLoans->first()->area->name }}</td></tr>
                @foreach ($areaLoans as $loan)
                    <tr>
                        <td class="nowrap">{{ $loan->loan_no }}</td>
                        <td>{{ $loan->borrower->full_name }}</td>
                        <td>{{ $loan->start_date->format('M d, Y') }}</td>
                        <td>{{ $loan->maturity_date->format('M d, Y') }}</td>
                        <td class="num">{{ $peso($loan->principal) }}</td>
                        <td class="num">{{ $peso($loan->total_payable) }}</td>
                        <td class="num">{{ $peso($loan->total_paid) }}</td>
                        <td class="num"><strong>{{ $peso($loan->balance) }}</strong></td>
                        <td><span class="tag {{ $loan->status->value }}">{{ $loan->status->getLabel() }}</span></td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="4">{{ $areaLoans->first()->area->name }} subtotal ({{ $areaLoans->count() }})</td>
                    <td class="num">{{ $peso($areaLoans->sum('principal')) }}</td>
                    <td class="num">{{ $peso($areaLoans->sum('total_payable')) }}</td>
                    <td class="num">{{ $peso($areaLoans->sum('total_paid')) }}</td>
                    <td class="num">{{ $peso($areaLoans->sum('balance')) }}</td>
                    <td></td>
                </tr>
            @empty
                <tr><td colspan="9" class="empty">No outstanding loans.</td></tr>
            @endforelse
            @if ($byArea->count() > 1)
                <tr class="total">
                    <td colspan="4">Grand total</td>
                    <td class="num">{{ $peso($loans->sum('principal')) }}</td>
                    <td class="num">{{ $peso($loans->sum('total_payable')) }}</td>
                    <td class="num">{{ $peso($loans->sum('total_paid')) }}</td>
                    <td class="num">{{ $peso($loans->sum('balance')) }}</td>
                    <td></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
