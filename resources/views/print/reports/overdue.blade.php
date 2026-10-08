@extends('print.layout', [
    'title' => 'Overdue Loans',
    'period' => 'As of '.now()->format('M d, Y').' · '.$areaName,
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
@endphp

@section('filters')
    @include('print.partials.area-filter')
    @if ($areas->isNotEmpty())<button type="submit">Apply</button>@endif
@endsection

@section('content')
    <dl class="meta">
        <div><dt>Overdue loans</dt><dd>{{ $loans->count() }}</dd></div>
        <div><dt>Past-due amount</dt><dd>₱{{ $peso($loans->sum('past_due')) }}</dd></div>
        <div><dt>Balance of these loans</dt><dd>₱{{ $peso($loans->sum('balance')) }}</dd></div>
        <div><dt>Most days late</dt><dd>{{ $loans->max('days_late') ?? 0 }}</dd></div>
    </dl>

    <table>
        <thead>
            <tr>
                <th style="width: 12%">Loan no.</th>
                <th>Borrower</th>
                @if (! $areaId)<th style="width: 10%">Area</th>@endif
                <th style="width: 13%">Contact</th>
                <th class="num" style="width: 9%">Days late</th>
                <th class="num" style="width: 9%">Missed</th>
                <th class="num" style="width: 12%">Past due</th>
                <th class="num" style="width: 12%">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($loans as $loan)
                <tr>
                    <td class="nowrap">{{ $loan->loan_no }}</td>
                    <td>
                        {{ $loan->borrower->full_name }}
                        <div class="muted">{{ $loan->borrower->address }}</div>
                    </td>
                    @if (! $areaId)<td>{{ $loan->area->name }}</td>@endif
                    <td>{{ $loan->borrower->contact_no ?: '—' }}</td>
                    <td class="num late">{{ $loan->days_late }}</td>
                    <td class="num">{{ $loan->missed_installments }}</td>
                    <td class="num"><strong>{{ $peso($loan->past_due) }}</strong></td>
                    <td class="num">{{ $peso($loan->balance) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No overdue loans.</td></tr>
            @endforelse
            @if ($loans->isNotEmpty())
                <tr class="total">
                    <td colspan="{{ $areaId ? 5 : 6 }}">Total</td>
                    <td class="num">{{ $peso($loans->sum('past_due')) }}</td>
                    <td class="num">{{ $peso($loans->sum('balance')) }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
