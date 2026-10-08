<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('lending.business_name') }}</title>
    <style>
        :root {
            --ink: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --soft: #f9fafb;
            --accent: #0f766e;
        }
        * { box-sizing: border-box; }
        html { background: #f3f4f6; }
        body {
            margin: 0 auto;
            max-width: 210mm;
            min-height: 297mm;
            padding: 18mm 16mm;
            background: #fff;
            color: var(--ink);
            font: 12px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-variant-numeric: tabular-nums;
        }
        header.doc {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--ink);
        }
        .brand { font-size: 16px; font-weight: 700; letter-spacing: -0.01em; }
        .brand small { display: block; font-size: 11px; font-weight: 400; color: var(--muted); }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 18px; letter-spacing: -0.01em; }
        .doc-title p { margin: 2px 0 0; color: var(--muted); }

        .meta {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px 24px;
            margin: 18px 0;
        }
        .meta dt { font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .meta dd { margin: 2px 0 0; font-weight: 600; }

        table { width: 100%; border-collapse: collapse; }
        th {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            font-weight: 600;
            text-align: left;
            padding: 8px 8px;
            border-bottom: 1px solid var(--ink);
        }
        td { padding: 7px 8px; border-bottom: 1px solid var(--line); vertical-align: top; }
        tbody tr:nth-child(even) td { background: var(--soft); }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: var(--muted); }
        .nowrap { white-space: nowrap; }
        tr.total td { font-weight: 700; border-top: 1px solid var(--ink); border-bottom: 0; background: #fff; }
        tr.opening td { font-style: italic; color: var(--muted); background: #fff; }

        h2 { font-size: 13px; margin: 24px 0 8px; letter-spacing: -0.01em; }
        h2 small { font-weight: 400; color: var(--muted); }
        tr.group td { background: #fff; font-weight: 700; padding-top: 14px; border-bottom: 1px solid var(--ink); }
        tr.subtotal td { background: #fff; font-weight: 600; border-bottom: 1px solid var(--ink); }
        .tag { display: inline-block; padding: 0 6px; border-radius: 4px; font-size: 10px; font-weight: 600; border: 1px solid currentColor; }
        .tag.overdue { color: #b91c1c; }
        .tag.active { color: #1d4ed8; }
        .tag.paid { color: #15803d; }
        .late { color: #b91c1c; font-weight: 600; }
        .blank { border-bottom: 1px solid var(--ink); min-width: 70px; display: inline-block; height: 14px; }
        .empty { text-align: center; padding: 28px; color: var(--muted); }
        .toolbar select { padding: 5px 8px; border-radius: 6px; border: 0; font: inherit; }

        .summary {
            display: flex;
            justify-content: flex-end;
            gap: 32px;
            margin-top: 16px;
        }
        .summary div { text-align: right; }
        .summary span { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .summary strong { font-size: 15px; }

        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; margin-top: 56px; }
        .signatures div { border-top: 1px solid var(--ink); padding-top: 4px; text-align: center; font-size: 11px; }

        footer.doc { margin-top: 32px; padding-top: 8px; border-top: 1px solid var(--line); font-size: 10px; color: var(--muted); display: flex; justify-content: space-between; }

        .toolbar {
            position: sticky; top: 0;
            display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between;
            margin: -18mm -16mm 18px; padding: 10px 16mm;
            background: var(--ink); color: #fff;
        }
        .toolbar form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .toolbar input { padding: 5px 8px; border-radius: 6px; border: 0; font: inherit; }
        .toolbar button {
            padding: 6px 14px; border-radius: 6px; border: 0; font: inherit; font-weight: 600; cursor: pointer;
            background: #fff; color: var(--ink);
        }
        .toolbar button.primary { background: var(--accent); color: #fff; }

        @media (max-width: 640px) {
            body { padding: 16px; min-height: 0; }
            .toolbar { margin: -16px -16px 16px; padding: 10px 16px; }
            .meta { grid-template-columns: repeat(2, 1fr); }
            table { font-size: 11px; }
        }
        @media print {
            html { background: #fff; }
            body { padding: 0; max-width: none; min-height: 0; }
            .toolbar { display: none; }
            tr { break-inside: avoid; }
            @page { size: A4; margin: 14mm 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>{{ $title }}</div>
        <form method="get">
            @yield('filters')
            <button type="button" class="primary" onclick="window.print()">Print</button>
        </form>
    </div>

    <header class="doc">
        <div class="brand">
            {{ config('lending.business_name') }}
            <small>{{ $subtitle ?? 'Loans & Savings Records' }}</small>
        </div>
        <div class="doc-title">
            <h1>{{ $title }}</h1>
            @isset($period)<p>{{ $period }}</p>@endisset
        </div>
    </header>

    @yield('content')

    <footer class="doc">
        <span>Printed {{ now()->format('M d, Y g:i A') }} by {{ auth()->user()->name }}</span>
        <span>{{ config('lending.business_name') }}</span>
    </footer>
</body>
</html>
