@use('App\Support\AmountInWords')
@php
    $peso = fn ($v) => '₱'.number_format((float) $v, 2);
    $copies = $copies ?? ["Borrower's copy", 'Office copy'];
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $number }} · {{ config('lending.business_name') }}</title>
    <style>
        :root { --ink: #111827; --muted: #6b7280; --line: #d1d5db; --accent: #0f766e; }
        * { box-sizing: border-box; }
        html { background: #f3f4f6; }
        body {
            margin: 0 auto; max-width: 210mm; padding: 12mm 14mm; background: #fff; color: var(--ink);
            font: 12px/1.45 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-variant-numeric: tabular-nums;
        }
        .toolbar {
            position: sticky; top: 0; display: flex; justify-content: space-between; align-items: center; gap: 8px;
            margin: -12mm -14mm 12mm; padding: 10px 14mm; background: var(--ink); color: #fff;
        }
        .toolbar button { padding: 6px 14px; border-radius: 6px; border: 0; font: inherit; font-weight: 600; cursor: pointer; background: var(--accent); color: #fff; }

        .receipt { border: 1.5px solid var(--ink); border-radius: 6px; padding: 14px 18px; break-inside: avoid; }
        .cut { margin: 10mm 0; border-top: 1px dashed var(--muted); text-align: center; height: 0; }
        .cut span { position: relative; top: -0.7em; background: #fff; padding: 0 8px; font-size: 10px; color: var(--muted); }

        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; border-bottom: 1px solid var(--ink); padding-bottom: 10px; }
        .biz { font-size: 15px; font-weight: 700; letter-spacing: -0.01em; }
        .biz small { display: block; font-size: 11px; font-weight: 400; color: var(--muted); }
        .no { text-align: right; }
        .no .label { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); }
        .no .value { font-size: 20px; font-weight: 800; letter-spacing: 0.02em; color: #b91c1c; }
        .title { margin: 10px 0 4px; display: flex; justify-content: space-between; align-items: baseline; }
        .title h1 { margin: 0; font-size: 14px; text-transform: uppercase; letter-spacing: 0.1em; }
        .title .copy { font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); border: 1px solid var(--line); border-radius: 4px; padding: 1px 6px; }

        .row { display: grid; grid-template-columns: 120px 1fr; gap: 8px; padding: 5px 0; border-bottom: 1px solid #eef0f3; }
        .row dt { color: var(--muted); }
        .row dd { margin: 0; font-weight: 600; }
        .amount { display: grid; grid-template-columns: 1fr auto; align-items: center; gap: 12px; margin: 10px 0; padding: 10px 12px; background: #f9fafb; border-radius: 6px; }
        .amount .words { font-style: italic; }
        .amount .figure { font-size: 22px; font-weight: 800; letter-spacing: -0.01em; }

        .balances { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 6px; }
        .balances div { border: 1px solid var(--line); border-radius: 6px; padding: 6px 10px; }
        .balances span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .balances strong { font-size: 14px; }
        .balances .after { border-color: var(--ink); }

        .sign { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 30px; }
        .sign div { border-top: 1px solid var(--ink); padding-top: 3px; text-align: center; font-size: 11px; }
        .sign div b { display: block; font-size: 12px; }
        .foot { margin-top: 10px; font-size: 10px; color: var(--muted); display: flex; justify-content: space-between; gap: 12px; }

        @media (max-width: 640px) {
            body { padding: 16px; }
            .toolbar { margin: -16px -16px 16px; padding: 10px 16px; }
            .row { grid-template-columns: 100px 1fr; }
            .balances { grid-template-columns: 1fr; }
        }
        @media print {
            html { background: #fff; }
            body { padding: 0; max-width: none; }
            .toolbar { display: none; }
            @page { size: A4; margin: 10mm 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>{{ $title }} · {{ $number }}</div>
        <button type="button" onclick="window.print()">Print</button>
    </div>

    @foreach ($copies as $copy)
        @if (! $loop->first)
            <div class="cut"><span>✂ cut here</span></div>
        @endif

        <section class="receipt">
            <div class="head">
                <div class="biz">
                    {{ config('lending.business_name') }}
                    <small>{{ $areaName }}</small>
                </div>
                <div class="no">
                    <div class="label">Receipt no.</div>
                    <div class="value">{{ $number }}</div>
                </div>
            </div>

            <div class="title">
                <h1>{{ $title }}</h1>
                <span class="copy">{{ $copy }}</span>
            </div>

            <dl style="margin: 0;">
                <div class="row"><dt>Date</dt><dd>{{ $date->format('F j, Y') }}</dd></div>
                <div class="row"><dt>{{ $partyLabel }}</dt><dd>{{ $party }} <span style="font-weight: 400; color: var(--muted);">· {{ $partyRef }}</span></dd></div>
                <div class="row"><dt>For</dt><dd>{{ $purpose }}</dd></div>
                @if ($remarks)
                    <div class="row"><dt>Remarks</dt><dd style="font-weight: 400;">{{ $remarks }}</dd></div>
                @endif
            </dl>

            <div class="amount">
                <div class="words">{{ AmountInWords::pesos($amount) }}</div>
                <div class="figure">{{ $peso($amount) }}</div>
            </div>

            <div class="balances">
                <div><span>{{ $balanceLabel }} before</span><strong>{{ $peso($before) }}</strong></div>
                <div><span>{{ $movementLabel }}</span><strong>{{ $sign }} {{ $peso($amount) }}</strong></div>
                <div class="after"><span>{{ $balanceLabel }} after</span><strong>{{ $peso($after) }}</strong></div>
            </div>

            <div class="sign">
                <div><b>{{ $staffName }}</b>{{ $isOut ? 'Released by' : 'Received by' }}</div>
                <div><b>{{ $party }}</b>{{ $isOut ? 'Received by (account holder)' : 'Paid by' }}</div>
            </div>

            <div class="foot">
                <span>{{ config('lending.receipts.footer') }}</span>
                <span style="white-space: nowrap;">Encoded {{ $encodedAt->format('M d, Y g:i A') }}</span>
            </div>
        </section>
    @endforeach
</body>
</html>
