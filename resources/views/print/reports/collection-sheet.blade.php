@extends('print.layout', [
    'title' => 'Daily Collection Sheet',
    'period' => $date->format('l, M d, Y').' · '.$areaName,
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $byArea = $loans->groupBy('area_id');
@endphp

@section('filters')
    @include('print.partials.area-filter')
    <label>Date <input type="date" name="date" value="{{ $date->toDateString() }}"></label>
    <button type="submit">Apply</button>
@endsection

@section('content')
    <dl class="meta">
        <div><dt>Accounts to visit</dt><dd>{{ $loans->count() }}</dd></div>
        <div><dt>Due today</dt><dd>₱{{ $peso($loans->sum('due_today')) }}</dd></div>
        <div><dt>Arrears</dt><dd>₱{{ $peso($loans->sum('arrears')) }}</dd></div>
        <div><dt>Still to collect</dt><dd>₱{{ $peso($loans->sum('to_collect')) }}</dd></div>
        <div><dt>Already collected</dt><dd>₱{{ $peso($loans->sum('paid_today')) }}</dd></div>
    </dl>

    @forelse ($byArea as $areaLoans)
        <h2>{{ $areaLoans->first()->area->name }} <small>· {{ $areaLoans->count() }} accounts</small></h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 4%">#</th>
                    <th>Borrower</th>
                    <th style="width: 13%">Loan no.</th>
                    <th class="num" style="width: 11%">Due today</th>
                    <th class="num" style="width: 11%">Arrears</th>
                    <th class="num" style="width: 12%">To collect</th>
                    <th class="num" style="width: 11%">Balance</th>
                    <th style="width: 12%">Collected</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($areaLoans as $loan)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
                        <td>
                            {{ $loan->borrower->full_name }}
                            <div class="muted">{{ $loan->borrower->address ?: $loan->borrower->contact_no }}</div>
                        </td>
                        <td class="nowrap">{{ $loan->loan_no }}</td>
                        <td class="num">{{ $peso($loan->due_today) }}</td>
                        <td class="num {{ $loan->arrears > 0 ? 'late' : 'muted' }}">{{ $loan->arrears > 0 ? $peso($loan->arrears) : '—' }}</td>
                        <td class="num">@if ($loan->to_collect > 0)<strong>{{ $peso($loan->to_collect) }}</strong>@else<span class="muted">—</span>@endif</td>
                        <td class="num muted">{{ $peso($loan->balance) }}</td>
                        <td>
                            @if ($loan->paid_today > 0)
                                <span class="tag paid">Paid {{ $peso($loan->paid_today) }}</span>
                            @endif
                            @if ($loan->to_collect > 0)
                                <span class="blank"></span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="3">Area total</td>
                    <td class="num">{{ $peso($areaLoans->sum('due_today')) }}</td>
                    <td class="num">{{ $peso($areaLoans->sum('arrears')) }}</td>
                    <td class="num">{{ $peso($areaLoans->sum('to_collect')) }}</td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    @empty
        <p class="empty">Nothing to collect on this date.</p>
    @endforelse

    <div class="signatures">
        <div>Collector</div>
        <div>Checked by</div>
    </div>
@endsection
