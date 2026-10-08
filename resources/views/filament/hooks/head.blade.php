{{-- Small refinements on top of the Filament theme; no build step needed. --}}
<style>
    /* Money lines up in columns: tabular figures everywhere numbers are shown. */
    .fi-ta-text,
    .fi-in-text,
    .fi-wi-stats-overview-stat-value,
    .fi-ta-summary-row,
    .fi-input { font-variant-numeric: tabular-nums; }

    /* Stat values: slightly tighter so ₱ figures read as headlines. */
    .fi-wi-stats-overview-stat-value { letter-spacing: -0.02em; }

    /* Headerbar refinement: remove toggle button in the topbar so it looks clean with text */
    .fi-topbar-open-sidebar-btn,
    .fi-topbar-close-sidebar-btn,
    .fi-topbar-collapse-sidebar-btn-ctn {
        display: none !important;
    }

    /* Hide scrollbars globally across all elements for a clean, sleek look */
    html, body, * {
        scrollbar-width: none !important;
        -ms-overflow-style: none !important;
    }
    ::-webkit-scrollbar {
        width: 0px !important;
        height: 0px !important;
        display: none !important;
        background: transparent !important;
    }

    /* Keep long names from crushing amount columns on phones. */
    @media (max-width: 640px) {
        .fi-ta-text { white-space: normal; }
        /* The Area is in the dashboard subheading; the topbar needs the room on phones. */
        .lms-user-label { display: none; }
    }
</style>
