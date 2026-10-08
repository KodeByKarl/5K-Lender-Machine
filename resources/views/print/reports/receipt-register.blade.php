@extends('print.layout', [
    'title' => 'Receipt Register',
    'period' => $from->format('M d, Y').' – '.$to->format('M d, Y').' · '.$areaName,
])

@php
    $peso = fn ($v) => number_format((float) $v, 2);
@endphp

@section('filters')
    @include('print.partials.area-filter')
    <label>From <input type="date" name="from" value="{{ $from->toDateString() }}"></label>
    <label>To <input type="date" name="to" value="{{ $to->toDateString() }}"></label>
    <button type="submit">Apply</button>
@endsection

@section('content')
    <dl class="meta">
        <div><dt>Receipts</dt><dd>{{ $groups->sum('count') }}</dd></div>
        <div><dt>Total amount</dt><dd>₱{{ $peso($groups->sum('total')) }}</dd></div>
        <div><dt>Missing numbers</dt><dd class="{{ $groups->sum('missing') ? 'late' : '' }}">{{ $groups->sum('missing') }}</dd></div>
        <div><dt>Check</dt><dd>Every paper receipt should appear here</dd></div>
    </dl>

    @forelse ($groups as $group)
        <h2>{{ $group['area'] }} <small>· {{ $group['count'] }} receipts · ₱{{ $peso($group['total']) }}@if ($group['missing']) · <span class="late">{{ $group['missing'] }} missing</span>@endif</small></h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 13%">Receipt no.</th>
                    <th style="width: 11%">Date</th>
                    <th style="width: 14%">Type</th>
                    <th>Received from</th>
                    <th style="width: 14%">Account</th>
                    <th style="width: 13%">Encoded by</th>
                    <th class="num" style="width: 11%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['rows'] as $row)
                    @if ($row['type'] === 'gap')
                        <tr class="opening">
                            <td colspan="7" class="late" style="font-style: normal;">
                                Missing {{ $row['from'] }}{{ $row['count'] > 1 ? ' to '.$row['to'] : '' }}
                                ({{ $row['count'] }} {{ str('receipt')->plural($row['count']) }}): spoiled, cancelled, or not yet encoded?
                            </td>
                        </tr>
                    @elseif ($row['type'] === 'jump')
                        <tr class="opening"><td colspan="7">New booklet: numbering jumps from {{ $row['from'] }} to {{ $row['to'] }}</td></tr>
                    @elseif ($row['type'] === 'duplicate')
                        <tr class="opening"><td colspan="7" class="late" style="font-style: normal;">Duplicate receipt no. {{ $row['number'] }}</td></tr>
                    @else
                        <tr>
                            <td class="nowrap"><strong>{{ $row['number'] }}</strong></td>
                            <td class="nowrap">{{ $row['date']->format('M d, Y') }}</td>
                            <td>{{ $row['kind'] }}</td>
                            <td>{{ $row['party'] }}</td>
                            <td class="nowrap">{{ $row['account'] }}</td>
                            <td class="muted">{{ $row['by'] ?? '—' }}</td>
                            <td class="num">{{ $peso($row['amount']) }}</td>
                        </tr>
                    @endif
                @endforeach
                <tr class="total">
                    <td colspan="6">{{ $group['area'] }} total</td>
                    <td class="num">{{ $peso($group['total']) }}</td>
                </tr>
            </tbody>
        </table>
    @empty
        <p class="empty">No receipts in this period.</p>
    @endforelse

    <div class="signatures">
        <div>Prepared by</div>
        <div>Checked against receipt booklets by</div>
    </div>
@endsection
