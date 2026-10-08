<x-filament-panels::page>
    @php
        $peso = fn ($v) => number_format((float) $v, 2);
        $totalRows = count($rows);
        $collectingRows = collect($rows)->filter(fn ($r) => (float)($r['amount'] ?? 0) > 0 && !($r['is_already_paid'] ?? false));
        $collectingCount = $collectingRows->count();
        $collectingTotal = $collectingRows->sum(fn ($r) => (float)($r['amount'] ?? 0));
        $alreadyPaidCount = collect($rows)->filter(fn ($r) => $r['is_already_paid'] ?? false)->count();
        $totalExpected = collect($rows)->sum(fn ($r) => (float)$r['to_collect']);
        $filtered = $this->filtered_rows;
    @endphp

    <style>
        .dc-container {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            padding-bottom: 5rem;
        }

        .dc-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        :is(.dark) .dc-card {
            background: #0f172a;
            border-color: #1e293b;
        }

        .dc-controls-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        .dc-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            margin-bottom: 0.375rem;
        }
        :is(.dark) .dc-label { color: #94a3b8; }

        .dc-input, .dc-select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            outline: none;
            box-sizing: border-box;
            line-height: 1.4;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        :is(.dark) .dc-input, :is(.dark) .dc-select {
            background: #1e293b;
            border-color: #334155;
            color: #f8fafc;
        }
        .dc-input:focus, .dc-select:focus {
            border-color: #0d9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.15);
        }

        .dc-input-sm {
            padding: 0.35rem 0.5rem;
            font-size: 0.75rem;
        }

        .dc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            padding: 0.5rem 0.875rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            line-height: 1.25;
            white-space: nowrap;
            transition: all 0.15s ease-in-out;
        }
        .dc-btn-primary {
            background: #0d9488;
            color: #ffffff;
        }
        .dc-btn-primary:hover {
            background: #0f766e;
        }
        .dc-btn-secondary {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #334155;
        }
        :is(.dark) .dc-btn-secondary {
            background: #1e293b;
            border-color: #334155;
            color: #cbd5e1;
        }
        .dc-btn-secondary:hover {
            background: #e2e8f0;
        }
        :is(.dark) .dc-btn-secondary:hover {
            background: #334155;
        }

        .dc-btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
        }

        .dc-btn-teal-light {
            background: #ccfbf1;
            color: #115e59;
        }
        :is(.dark) .dc-btn-teal-light {
            background: #134e4a;
            color: #5eead4;
        }
        .dc-btn-teal-light:hover {
            background: #99f6e4;
        }

        /* Toolbar Row */
        .dc-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            border-top: 1px solid #f1f5f9;
            padding-top: 1rem;
            margin-top: 1rem;
        }
        :is(.dark) .dc-toolbar {
            border-color: #1e293b;
        }

        /* Filter Pills */
        .dc-pill-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 0.1875rem;
            border-radius: 0.5rem;
            gap: 0.125rem;
        }
        :is(.dark) .dc-pill-group {
            background: #1e293b;
        }
        .dc-pill {
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 0.375rem;
            border: 0;
            background: transparent;
            color: #64748b;
            cursor: pointer;
        }
        :is(.dark) .dc-pill { color: #94a3b8; }
        .dc-pill.active {
            background: #ffffff;
            color: #0f172a;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        :is(.dark) .dc-pill.active {
            background: #334155;
            color: #ffffff;
        }

        /* Table */
        .dc-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        :is(.dark) .dc-table-card {
            background: #0f172a;
            border-color: #1e293b;
        }
        .dc-table-wrap {
            overflow-x: auto;
            width: 100%;
        }
        .dc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            text-align: left;
            font-variant-numeric: tabular-nums;
        }
        .dc-table th {
            background: #f8fafc;
            padding: 0.75rem 0.75rem;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        :is(.dark) .dc-table th {
            background: #1e293b;
            color: #94a3b8;
            border-color: #334155;
        }
        .dc-table td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        :is(.dark) .dc-table td {
            border-color: #1e293b;
        }
        .dc-table tr:hover {
            background: #f8fafc;
        }
        :is(.dark) .dc-table tr:hover {
            background: #1e293b40;
        }
        .dc-row-selected {
            background: #f0fdfa !important;
        }
        :is(.dark) .dc-row-selected {
            background: #134e4a25 !important;
        }
        .dc-row-paid {
            opacity: 0.7;
            background: #f8fafc50;
        }

        /* Badges */
        .dc-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.1875rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.625rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            line-height: 1;
        }
        .dc-badge-success { background: #dcfce7; color: #166534; }
        :is(.dark) .dc-badge-success { background: #14532d; color: #86efac; }
        .dc-badge-teal { background: #ccfbf1; color: #115e59; }
        :is(.dark) .dc-badge-teal { background: #134e4a; color: #5eead4; }
        .dc-badge-gray { background: #f1f5f9; color: #475569; }
        :is(.dark) .dc-badge-gray { background: #334155; color: #cbd5e1; }
        .dc-badge-danger { background: #fee2e2; color: #991b1b; }
        :is(.dark) .dc-badge-danger { background: #7f1d1d; color: #fca5a5; }

        /* Sticky Summary Bar */
        .dc-sticky-bar {
            position: fixed;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%);
            width: calc(100% - 2.5rem);
            max-width: 1200px;
            background: linear-gradient(135deg, #115e59 0%, #0f172a 100%);
            color: #ffffff;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.35), 0 8px 10px -6px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(45, 212, 191, 0.4);
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            z-index: 50;
        }

        .dc-stat-box {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        .dc-stat-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #5eead4;
            margin-bottom: 0.125rem;
        }
        .dc-stat-num {
            font-size: 1.375rem;
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            color: #ffffff;
        }

        .dc-post-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #14b8a6;
            color: #0f172a;
            font-weight: 800;
            font-size: 0.875rem;
            padding: 0.625rem 1.25rem;
            border-radius: 0.625rem;
            border: 0;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
            transition: all 0.15s ease-in-out;
        }
        .dc-post-btn:hover:not(:disabled) {
            background: #2dd4bf;
            transform: translateY(-1px);
        }
        .dc-post-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }

        .dc-icon {
            width: 1rem;
            height: 1rem;
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
        }
    </style>

    <div class="dc-container">
        {{-- Card 1: Controls & Automated Receipt Series --}}
        <div class="dc-card">
            <div class="dc-controls-grid">
                {{-- Area / Branch --}}
                @if (auth()->user()->isAdmin())
                    <div>
                        <label class="dc-label">Area / Branch</label>
                        <select wire:model.live="area_id" class="dc-select">
                            @foreach ($this->areas as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="dc-label">Your Area</label>
                        <div style="display: flex; align-items: center; gap: 0.5rem; height: 38px; padding: 0 0.75rem; background: rgba(13, 148, 136, 0.1); border-radius: 0.5rem; color: #0d9488; font-weight: 700; font-size: 0.875rem;">
                            <svg class="dc-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            {{ auth()->user()->area?->name }}
                        </div>
                    </div>
                @endif

                {{-- Collection Date --}}
                <div>
                    <label class="dc-label">Collection Date</label>
                    <input type="date" wire:model.live="date" class="dc-input" />
                </div>

                {{-- Starting Booklet Receipt No --}}
                <div style="flex-grow: 1;">
                    <label class="dc-label">Starting Booklet Receipt No.</label>
                    <div style="display: flex; gap: 0.5rem;">
                        <input
                            type="text"
                            wire:model="start_receipt_no"
                            placeholder="e.g. 1001 or 000501"
                            class="dc-input"
                            style="font-family: monospace;"
                        />
                        <button
                            type="button"
                            wire:click="autoNumberReceipts"
                            class="dc-btn dc-btn-secondary"
                            title="Auto-fills receipt numbers sequentially down all accounts with amounts"
                        >
                            Auto-number
                        </button>
                    </div>
                </div>

                {{-- Print Route Sheet Link --}}
                <div style="display: flex; justify-content: flex-end;">
                    <a
                        href="{{ route('print.reports.collection-sheet', ['area' => $area_id, 'date' => $date]) }}"
                        target="_blank"
                        class="dc-btn dc-btn-secondary"
                    >
                        <svg class="dc-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" /></svg>
                        Print Sheet
                    </a>
                </div>
            </div>

            {{-- Toolbar: Quick Tools & Filters --}}
            <div class="dc-toolbar">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Quick tools:</span>
                    <button
                        type="button"
                        wire:click="fillAllWithDue"
                        class="dc-btn dc-btn-sm dc-btn-teal-light"
                    >
                        Fill All with Due Amount
                    </button>
                    <button
                        type="button"
                        wire:click="clearAll"
                        class="dc-btn dc-btn-sm dc-btn-secondary"
                    >
                        Clear Amounts
                    </button>
                </div>

                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                    {{-- Status Filter Pills --}}
                    <div class="dc-pill-group">
                        <button
                            type="button"
                            wire:click="$set('status_filter', 'all')"
                            class="dc-pill {{ $status_filter === 'all' ? 'active' : '' }}"
                        >
                            All ({{ $totalRows }})
                        </button>
                        <button
                            type="button"
                            wire:click="$set('status_filter', 'pending')"
                            class="dc-pill {{ $status_filter === 'pending' ? 'active' : '' }}"
                        >
                            Pending ({{ max(0, $totalRows - $collectingCount - $alreadyPaidCount) }})
                        </button>
                        <button
                            type="button"
                            wire:click="$set('status_filter', 'collected')"
                            class="dc-pill {{ $status_filter === 'collected' ? 'active' : '' }}"
                        >
                            Collected ({{ $collectingCount + $alreadyPaidCount }})
                        </button>
                    </div>

                    {{-- Search Input --}}
                    <div style="position: relative; width: 220px;">
                        <input
                            type="text"
                            wire:model.live.debounce.150ms="search"
                            placeholder="Search client or loan..."
                            class="dc-input dc-input-sm"
                            style="padding-left: 1.75rem;"
                        />
                        <svg class="dc-icon" style="position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); color: #94a3b8;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Main Collection Data Sheet Table --}}
        <div class="dc-table-card">
            <div class="dc-table-wrap">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th style="width: 35px; text-align: center;">#</th>
                            <th style="min-width: 190px;">Borrower & Loan</th>
                            <th style="text-align: right; width: 95px;">Due Today</th>
                            <th style="text-align: right; width: 95px;">Arrears</th>
                            <th style="text-align: right; width: 105px;">To Collect</th>
                            <th style="text-align: center; width: 140px;">Quick Fill</th>
                            <th style="width: 130px;">Receipt No.</th>
                            <th style="width: 135px;">Collected (₱)</th>
                            <th style="min-width: 140px;">Remarks</th>
                            <th style="text-align: center; width: 110px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filtered as $loanId => $row)
                            @php
                                $isPaid = $row['is_already_paid'];
                                $hasAmount = (float)($row['amount'] ?? 0) > 0;
                            @endphp
                            <tr
                                wire:key="row-{{ $loanId }}"
                                class="{{ $hasAmount ? 'dc-row-selected' : '' }} {{ $isPaid ? 'dc-row-paid' : '' }}"
                            >
                                {{-- Index --}}
                                <td style="text-align: center; color: #94a3b8; font-family: monospace;">
                                    {{ $loop->iteration }}
                                </td>

                                {{-- Borrower Info --}}
                                <td>
                                    <div style="font-weight: 700; color: #0f172a; line-height: 1.25;" class="dark:text-white">
                                        {{ $row['borrower_name'] }}
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; color: #64748b; margin-top: 0.125rem;">
                                        <span style="font-family: monospace; font-weight: 600;">{{ $row['loan_no'] }}</span>
                                        <span>·</span>
                                        <span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">{{ $row['borrower_address'] }}</span>
                                    </div>
                                </td>

                                {{-- Due Today --}}
                                <td style="text-align: right; font-weight: 600; color: #334155;" class="dark:text-gray-300">
                                    ₱{{ $peso($row['due_today']) }}
                                </td>

                                {{-- Arrears --}}
                                <td style="text-align: right;">
                                    @if ($row['arrears'] > 0)
                                        <span style="font-weight: 800; color: #dc2626;">₱{{ $peso($row['arrears']) }}</span>
                                    @else
                                        <span style="color: #cbd5e1;">—</span>
                                    @endif
                                </td>

                                {{-- Total to Collect --}}
                                <td style="text-align: right; font-weight: 800; color: #0d9488;">
                                    ₱{{ $peso($row['to_collect']) }}
                                </td>

                                {{-- Quick Action Buttons --}}
                                <td style="text-align: center;">
                                    @if (! $isPaid)
                                        <div style="display: inline-flex; gap: 0.25rem;">
                                            <button
                                                type="button"
                                                wire:click="fillRowFull({{ $loanId }})"
                                                class="dc-btn dc-btn-sm dc-btn-teal-light"
                                                title="Fill Total Due (₱{{ $peso($row['to_collect']) }})"
                                            >
                                                Full
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="fillRowDueToday({{ $loanId }})"
                                                class="dc-btn dc-btn-sm dc-btn-secondary"
                                                title="Fill Today Only (₱{{ $peso($row['due_today']) }})"
                                            >
                                                Today
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="skipRow({{ $loanId }})"
                                                class="dc-btn dc-btn-sm dc-btn-secondary"
                                                style="color: #94a3b8;"
                                                title="Skip / Set ₱0"
                                            >
                                                Skip
                                            </button>
                                        </div>
                                    @else
                                        <span style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">Posted</span>
                                    @endif
                                </td>

                                {{-- Receipt No. Input --}}
                                <td>
                                    @if (! $isPaid)
                                        <input
                                            type="text"
                                            wire:model="rows.{{ $loanId }}.receipt_no"
                                            placeholder="RCP No."
                                            class="dc-input dc-input-sm"
                                            style="font-family: monospace; font-weight: 600;"
                                        />
                                    @else
                                        <span style="font-family: monospace; font-weight: 700; color: #16a34a; font-size: 0.75rem;">✓ Recorded</span>
                                    @endif
                                </td>

                                {{-- Collected Amount Input --}}
                                <td>
                                    @if (! $isPaid)
                                        <div style="position: relative;">
                                            <span style="position: absolute; left: 0.5rem; top: 50%; transform: translateY(-50%); font-size: 0.75rem; font-weight: 700; color: #94a3b8;">₱</span>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                wire:model.live.debounce.300ms="rows.{{ $loanId }}.amount"
                                                placeholder="0.00"
                                                class="dc-input dc-input-sm"
                                                style="padding-left: 1.35rem; font-weight: 700;"
                                            />
                                        </div>
                                    @else
                                        <span style="font-weight: 800; color: #16a34a; font-size: 0.8125rem;">
                                            ₱{{ $peso($row['paid_today']) }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Remarks --}}
                                <td>
                                    @if (! $isPaid)
                                        <input
                                            type="text"
                                            wire:model="rows.{{ $loanId }}.remarks"
                                            placeholder="Notes"
                                            class="dc-input dc-input-sm"
                                        />
                                    @else
                                        <span style="color: #94a3b8;">—</span>
                                    @endif
                                </td>

                                {{-- Status Badge --}}
                                <td style="text-align: center;">
                                    @if ($isPaid)
                                        <span class="dc-badge dc-badge-success">PAID TODAY</span>
                                    @elseif ($hasAmount)
                                        <span class="dc-badge dc-badge-teal">READY TO POST</span>
                                    @else
                                        <span class="dc-badge dc-badge-gray">UNPAID / SKIP</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" style="padding: 3rem 1rem; text-align: center; color: #64748b;">
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <svg style="width: 2.5rem; height: 2.5rem; color: #cbd5e1;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        <span style="font-weight: 600;">Walang accounts na dapat singilin sa napiling petsa at area.</span>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Sticky Remittance & Posting Summary Bar --}}
    <div class="dc-sticky-bar">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 2rem;">
            <div class="dc-stat-box">
                <span class="dc-stat-label">Total Accounts</span>
                <span class="dc-stat-num">{{ $totalRows }}</span>
            </div>

            <div class="dc-stat-box">
                <span class="dc-stat-label">Ready to Post</span>
                <div>
                    <span class="dc-stat-num" style="color: #5eead4;">{{ $collectingCount }}</span>
                    <span style="font-size: 0.75rem; color: #99f6e4;">/ {{ $totalRows }}</span>
                </div>
            </div>

            <div class="dc-stat-box" style="border-left: 1px solid rgba(255, 255, 255, 0.15); padding-left: 1.5rem;">
                <span class="dc-stat-label">Total Remittance (Cash on Hand)</span>
                <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                    <span class="dc-stat-num" style="font-size: 1.5rem;">₱{{ $peso($collectingTotal) }}</span>
                    <span style="font-size: 0.75rem; color: #99f6e4;">(Expected: ₱{{ $peso($totalExpected) }})</span>
                </div>
            </div>
        </div>

        <div>
            <button
                type="button"
                wire:click="postCollections"
                wire:loading.attr="disabled"
                @if ($collectingCount === 0) disabled @endif
                class="dc-post-btn"
            >
                <svg wire:loading class="dc-icon" style="animation: spin 1s linear infinite;" fill="none" viewBox="0 0 24 24"><circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                <span>Post Collections ({{ $collectingCount }} accounts)</span>
            </button>
        </div>
    </div>
</x-filament-panels::page>
