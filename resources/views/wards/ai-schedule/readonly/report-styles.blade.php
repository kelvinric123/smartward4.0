{{--
    Styles the report printouts add to readonly/styles.blade.php: the summary
    tiles across the top and the tight day-by-day grids. Each report partial
    includes this once (@once), so it sits with the report that uses it.
    Sizes are in rem, so the printout's text size scales them; .ro-mono
    keeps them readable in black and white.
--}}
<style>
    .rp-tiles {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(8.5rem, 1fr));
        gap: 0.375rem;
    }

    .rp-tile {
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        padding: 0.3rem 0.5rem;
        break-inside: avoid;
    }

    .rp-tile-label {
        color: #4b5563;
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .rp-tile-value {
        color: #111827;
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.35rem;
    }

    .rp-tile-note {
        color: #6b7280;
        font-size: 0.62rem;
    }

    /* A flagged tile: thicker edge as well as colour, so it shows in black and white */
    .rp-tile-alert {
        border: 2px solid #dc2626;
    }

    .rp-tile-warn {
        border: 2px solid #d97706;
    }

    /* Day columns: every day the same width, so a month fits across landscape paper */
    .rp-grid {
        table-layout: fixed;
    }

    .rp-grid th,
    .rp-grid td {
        padding: 0.15rem 0.1rem !important;
        text-align: center;
        font-size: 0.65rem;
    }

    .rp-grid .rp-grid-label {
        width: 6.5rem;
        text-align: left;
        padding-left: 0.3rem !important;
    }

    /* A long table of days: tighter rows, so a month runs to two landscape pages, not three */
    .rp-dense th,
    .rp-dense td {
        padding-top: 0.05rem !important;
        padding-bottom: 0.05rem !important;
        line-height: 1.15;
    }

    .rp-num {
        font-variant-numeric: tabular-nums;
        text-align: right;
        white-space: nowrap;
    }

    .rp-section-title {
        margin-bottom: 0.25rem;
        color: #374151;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .rp-total td,
    .rp-total th {
        border-top: 2px solid #6b7280;
        font-weight: 700;
    }

    .ro-mono .rp-tile {
        border-color: #000 !important;
    }

    .ro-mono .rp-tile-alert,
    .ro-mono .rp-tile-warn {
        border-width: 3px !important;
    }

    .ro-mono .rp-tile-label,
    .ro-mono .rp-tile-note {
        color: #333 !important;
    }
</style>
