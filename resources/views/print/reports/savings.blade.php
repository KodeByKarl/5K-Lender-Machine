@extends('print.layout', [
    'title' => 'Savings Summary',
    'period' => $from->format('M d, Y').' – '.$to->format('M d, Y').' · '.$areaName,
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $byArea = $accounts->groupBy('area_id');
@endphp

@section('filters')
    @include('print.partials.area-filter')
    <label>From <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
    <label>To <input type="date" name="to" value="{{ $to->toDateString() }}"></label>
    <button type="submit">Apply</button>
@endsection

@section('content')
    <dl class="meta">
        <div><dt>Accounts</dt><dd>{{ $accounts->count() }}</dd></div>
        <div><dt>Deposits in period</dt><dd>₱{{ $peso($accounts->sum('deposits')) }}</dd></div>
        <div><dt>Withdrawals in period</dt><dd>₱{{ $peso($accounts->sum('withdrawals')) }}</dd></div>
        <div><dt>Total savings now</dt><dd>₱{{ $peso($accounts->sum('balance')) }}</dd></div>
    </dl>

    @if ($byArea->count() > 1)
        <h2>Per area</h2>
        <table>
            <thead>
                <tr>
                    <th>Area</th>
                    <th class="num" style="width: 12%">Accounts</th>
                    <th class="num" style="width: 17%">Deposits</th>
                    <th class="num" style="width: 17%">Withdrawals</th>
                    <th class="num" style="width: 17%">Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($byArea as $areaAccounts)
                    <tr>
                        <td>{{ $areaAccounts->first()->area->name }}</td>
                        <td class="num">{{ $areaAccounts->count() }}</td>
                        <td class="num">{{ $peso($areaAccounts->sum('deposits')) }}</td>
                        <td class="num">{{ $peso($areaAccounts->sum('withdrawals')) }}</td>
                        <td class="num">{{ $peso($areaAccounts->sum('balance')) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>All areas</td>
                    <td class="num">{{ $accounts->count() }}</td>
                    <td class="num">{{ $peso($accounts->sum('deposits')) }}</td>
                    <td class="num">{{ $peso($accounts->sum('withdrawals')) }}</td>
                    <td class="num">{{ $peso($accounts->sum('balance')) }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    <h2>Per borrower</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 13%">Account no.</th>
                <th>Account holder</th>
                <th style="width: 12%">Opened</th>
                <th class="num" style="width: 15%">Deposits</th>
                <th class="num" style="width: 15%">Withdrawals</th>
                <th class="num" style="width: 15%">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($byArea as $areaAccounts)
                <tr class="group"><td colspan="6">{{ $areaAccounts->first()->area->name }}</td></tr>
                @foreach ($areaAccounts as $account)
                    <tr>
                        <td>{{ $account->account_no }}</td>
                        <td>{{ $account->borrower->full_name }}</td>
                        <td>{{ $account->opened_at->format('M d, Y') }}</td>
                        <td class="num">{{ $peso($account->deposits) }}</td>
                        <td class="num">{{ $peso($account->withdrawals) }}</td>
                        <td class="num"><strong>{{ $peso($account->balance) }}</strong></td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="6" class="empty">No savings accounts.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
