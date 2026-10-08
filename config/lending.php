<?php

/*
| Lending Rules (Annex A). Update these to match the Client's approved setup sheet.
*/

return [

    // Days after an installment's due date before the loan is marked Overdue.
    'overdue_grace_days' => (int) env('LENDING_OVERDUE_GRACE_DAYS', 0),

    // Daily collection includes Sundays. Set to false for Monday–Saturday collection.
    'collect_on_sundays' => (bool) env('LENDING_COLLECT_ON_SUNDAYS', true),

    // Defaults pre-filled on the New Loan form (e.g. ₱10,000 at 20% payable daily in 60 days).
    'defaults' => [
        'interest_method' => env('LENDING_DEFAULT_METHOD', 'flat'),
        'rate_basis' => env('LENDING_DEFAULT_RATE_BASIS', 'per_term'),
        'payment_frequency' => env('LENDING_DEFAULT_FREQUENCY', 'daily'),
        'interest_rate' => (float) env('LENDING_DEFAULT_RATE', 20),
        'term' => (int) env('LENDING_DEFAULT_TERM', 60),
        'term_unit' => env('LENDING_DEFAULT_TERM_UNIT', 'days'),
    ],

    // Receipts are pre-printed booklets; staff type the receipt number from the booklet.
    'receipts' => [
        // Title printed on system receipts. Not "Official Receipt": that term is reserved for BIR-registered receipts.
        'title' => env('LENDING_RECEIPT_TITLE', 'Acknowledgment Receipt'),
        // Line printed at the bottom of every receipt.
        'footer' => env('LENDING_RECEIPT_FOOTER', 'Keep this receipt. Your ledger shows this payment under the same receipt number and balance.'),
    ],

    // Loan release vouchers.
    'voucher' => [
        // First voucher number, to continue the Client's existing series (e.g. 152 after voucher 000151).
        'start' => (int) env('LENDING_VOUCHER_START', 1),
        // Names printed under "Audited by" and "Approved by". Leave empty for a blank signature line.
        'audited_by' => env('LENDING_VOUCHER_AUDITED_BY'),
        'approved_by' => env('LENDING_VOUCHER_APPROVED_BY'),
    ],

    // Shown on printed reports, receipts, and vouchers.
    'business_name' => env('LENDING_BUSINESS_NAME', env('APP_NAME', 'Lending Business')),
    'business_address' => env('LENDING_BUSINESS_ADDRESS'),
    // Optional logo file in public/, e.g. "logo.png".
    'logo' => env('LENDING_LOGO'),

    // Initial accounts and Areas created by `php artisan db:seed` (Annex A).
    'setup' => [
        'admin' => ['name' => env('SETUP_ADMIN_NAME', 'Administrator'), 'email' => env('SETUP_ADMIN_EMAIL', 'admin@lender.test')],
        'areas' => [
            1 => ['name' => env('SETUP_AREA_1_NAME', 'Area 1'), 'staff_name' => env('SETUP_AREA_1_STAFF_NAME'), 'staff_email' => env('SETUP_AREA_1_STAFF_EMAIL', 'staff1@lender.test')],
            2 => ['name' => env('SETUP_AREA_2_NAME', 'Area 2'), 'staff_name' => env('SETUP_AREA_2_STAFF_NAME'), 'staff_email' => env('SETUP_AREA_2_STAFF_EMAIL', 'staff2@lender.test')],
            3 => ['name' => env('SETUP_AREA_3_NAME', 'Area 3'), 'staff_name' => env('SETUP_AREA_3_STAFF_NAME'), 'staff_email' => env('SETUP_AREA_3_STAFF_EMAIL', 'staff3@lender.test')],
        ],
    ],

];
