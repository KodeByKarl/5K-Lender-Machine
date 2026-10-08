@use('App\Support\AmountInWords')
@php
    $peso = fn ($v) => number_format((float) $v, 2);
    $copies = ["Borrower's copy", 'Office copy'];
    $logo = config('lending.logo') && file_exists(public_path(config('lending.logo'))) ? asset(config('lending.logo')) : null;
    $cashAccount = $loan->release_method === 'bank' ? 'Cash in bank' : 'Cash on hand';

    // Balanced entry: the receivable equals the cash released plus unearned interest plus every charge.
    $credits = [
        [$cashAccount, $loan->net_proceeds],
        ['Unearned interest income', $loan->total_interest],
        ...collect($loan->charges ?? [])->map(fn ($c) => [$c['name'], $c['amount']])->all(),
    ];
    $totalCredit = collect($credits)->sum(fn ($c) => (float) $c[1]);
    $first = $loan->installments->first();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voucher {{ $loan->voucher_no }} · {{ config('lending.business_name') }}</title>
    <style>
        :root { --ink: #111827; --muted: #6b7280; --line: #d1d5db; --accent: #0f766e; }
        * { box-sizing: border-box; }
        html { background: #f3f4f6; }
        body {
            margin: 0 auto; max-width: 210mm; padding: 10mm 12mm; background: #fff; color: var(--ink);
            font: 11px/1.4 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-variant-numeric: tabular-nums;
        }
        .toolbar {
            position: sticky; top: 0; display: flex; justify-content: space-between; align-items: center; gap: 8px;
            margin: -10mm -12mm 10mm; padding: 10px 12mm; background: var(--ink); color: #fff; font-size: 12px;
        }
        .toolbar button { padding: 6px 14px; border-radius: 6px; border: 0; font: inherit; font-weight: 600; cursor: pointer; background: var(--accent); color: #fff; }

        .voucher { border: 1.5px solid var(--ink); padding: 10px 14px; break-inside: avoid; }
        .cut { margin: 7mm 0; border-top: 1px dashed var(--muted); text-align: center; height: 0; }
        .cut span { position: relative; top: -0.7em; background: #fff; padding: 0 8px; font-size: 9px; color: var(--muted); }

        .head { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 12px; padding-bottom: 6px; border-bottom: 2px solid var(--ink); }
        .head img { height: 40px; }
        .head .biz { font-size: 15px; font-weight: 800; letter-spacing: 0.02em; text-transform: uppercase; }
        .head .addr { color: var(--muted); font-style: italic; }
        .head .copy { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); border: 1px solid var(--line); border-radius: 4px; padding: 1px 6px; }

        .title { display: flex; justify-content: space-between; align-items: flex-end; margin: 8px 0 6px; }
        .title h1 { margin: 0; font-size: 13px; letter-spacing: 0.12em; text-transform: uppercase; }
        .title .date { text-align: right; }
        .title .date b { display: block; font-size: 12px; }
        .title .date span { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }

        .who { display: grid; grid-template-columns: 90px 1fr 90px 120px; gap: 2px 8px; margin-bottom: 6px; }
        .who dt { font-weight: 700; text-transform: uppercase; font-size: 10px; }
        .who dd { margin: 0; border-bottom: 1px solid var(--line); }
        .no { color: #b91c1c; font-weight: 800; font-size: 12px; }

        .grid { display: grid; grid-template-columns: 42% 58%; border: 1px solid var(--ink); }
        .grid > div { padding: 6px 8px; }
        .grid > div + div { border-left: 1px solid var(--ink); }
        .kv { display: grid; grid-template-columns: 105px 1fr; gap: 2px 6px; }
        .kv dt { font-weight: 700; text-transform: uppercase; font-size: 9.5px; padding-top: 1px; }
        .kv dd { margin: 0; }
        .modes { display: flex; gap: 14px; margin-bottom: 6px; }
        .box { display: inline-block; width: 11px; height: 11px; border: 1.2px solid var(--ink); margin-right: 4px; vertical-align: -1px; text-align: center; line-height: 9px; font-size: 10px; font-weight: 800; }

        table { width: 100%; border-collapse: collapse; }
        th { font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.06em; text-align: left; padding: 2px 4px; border-bottom: 1px solid var(--ink); }
        td { padding: 2px 4px; }
        .num { text-align: right; white-space: nowrap; width: 90px; }
        tr.total td { border-top: 1px solid var(--ink); font-weight: 800; }

        .ack { text-align: center; margin: 8px 0 4px; }
        .ack i { color: var(--muted); }
        .sign { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-top: 18px; }
        .sign div { text-align: center; font-size: 10px; }
        .sign b { display: block; border-bottom: 1px solid var(--ink); min-height: 15px; font-size: 11px; text-transform: uppercase; }
        .sign span { font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }

        @media (max-width: 640px) {
            body { padding: 12px; }
            .toolbar { margin: -12px -12px 12px; padding: 10px 12px; }
            .grid { grid-template-columns: 1fr; }
            .grid > div + div { border-left: 0; border-top: 1px solid var(--ink); }
            .who, .sign { grid-template-columns: 1fr 1fr; }
        }
        @media print {
            html { background: #fff; }
            body { padding: 0; max-width: none; }
            .toolbar { display: none; }
            @page { size: A4; margin: 8mm 10mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>Loan Release Voucher · {{ $loan->voucher_no }}</div>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    @foreach ($copies as $copy)
        @if (! $loop->first)
            <div class="cut"><span>✂ cut here</span></div>
        @endif

        <section class="voucher">
            <div class="head">
                @if ($logo)<img src="{{ $logo }}" alt="">@else<span></span>@endif
                <div>
                    <div class="biz">{{ config('lending.business_name') }}</div>
                    <div class="addr">{{ config('lending.business_address') ?: $loan->area->name }}</div>
                </div>
                <span class="copy">{{ $copy }}</span>
            </div>

            <div class="title">
                <h1>Loan Release Voucher</h1>
                <div class="date"><b>{{ $loan->start_date->format('F j, Y') }}</b><span>Date</span></div>
            </div>

            <dl class="who">
                <dt>Voucher no.</dt><dd class="no">{{ $loan->voucher_no }}</dd>
                <dt>Area</dt><dd>{{ $loan->area->name }}</dd>
                <dt>Pay to</dt><dd><b>{{ strtoupper($loan->borrower->full_name) }}</b> · {{ $loan->borrower->reference_no }}</dd>
                <dt>Contact</dt><dd>{{ $loan->borrower->contact_no ?: '—' }}</dd>
                <dt>Address</dt><dd style="grid-column: span 3;">{{ $loan->borrower->address ?: '—' }}</dd>
            </dl>

            <div class="grid">
                <div>
                    <div class="modes">
                        <span><span class="box">{{ $loan->release_method !== 'bank' ? '✓' : '' }}</span>Cash</span>
                        <span><span class="box">{{ $loan->release_method === 'bank' ? '✓' : '' }}</span>Bank {{ $loan->release_reference ? '· '.$loan->release_reference : '' }}</span>
                    </div>
                    <dl class="kv">
                        <dt>Loan acct no.</dt><dd>{{ $loan->loan_no }}</dd>
                        <dt>Loan amount</dt><dd>₱{{ $peso($loan->principal) }}</dd>
                        <dt>Net proceeds</dt><dd><b>₱{{ $peso($loan->net_proceeds) }}</b></dd>
                        <dt>PN dated</dt><dd>{{ $loan->start_date->format('F j, Y') }}</dd>
                        <dt>Maturity on</dt><dd>{{ $loan->maturity_date->format('F j, Y') }}</dd>
                        <dt>Amortization</dt><dd>₱{{ $peso($first?->amount_due) }} · {{ strtolower($loan->payment_frequency->getLabel()) }} / {{ $loan->term }} {{ strtolower($loan->term_unit->getLabel()) }}</dd>
                        @if ($loan->required_savings)
                            <dt>Savings</dt><dd>₱{{ $peso($loan->required_savings) }} per collection</dd>
                        @endif
                        <dt>Plan</dt><dd>{{ $loan->plan?->name ?? 'Custom terms' }}</dd>
                    </dl>
                </div>
                <div>
                    <table>
                        <thead><tr><th>Accounting titles</th><th class="num">DR</th><th class="num">CR</th></tr></thead>
                        <tbody>
                            <tr><td>Loan receivable</td><td class="num">₱{{ $peso($loan->total_payable) }}</td><td></td></tr>
                            @foreach ($credits as [$title, $amount])
                                <tr><td>{{ $title }}</td><td></td><td class="num">{{ $loop->first ? '₱' : '' }}{{ $peso($amount) }}</td></tr>
                            @endforeach
                            <tr class="total"><td>Total</td><td class="num">₱{{ $peso($loan->total_payable) }}</td><td class="num">₱{{ $peso($totalCredit) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <p class="ack">
                I hereby acknowledge the receipt of the net loan proceeds of <b>₱{{ $peso($loan->net_proceeds) }}</b>.<br>
                <i>({{ AmountInWords::pesos($loan->net_proceeds) }})</i>
            </p>

            <div class="sign">
                <div><b>{{ $loan->releaser?->name }}</b><span>Prepared by</span></div>
                <div><b>{{ config('lending.voucher.audited_by') }}</b><span>Audited by</span></div>
                <div><b>{{ config('lending.voucher.approved_by') }}</b><span>Approved by</span></div>
                <div><b>{{ $loan->borrower->full_name }}</b><span>Received by</span></div>
            </div>
        </section>
    @endforeach
</body>
</html>
