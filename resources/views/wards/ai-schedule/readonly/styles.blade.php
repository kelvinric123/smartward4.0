{{--
    Styles for the read-only partials beside this file, included once in the
    <head> of a page that shows them (the printouts and the view-only link).
    Sizes are in rem, so a page can scale everything by its root font size.

    .ro-mono on a wrapper turns colour into black and white for printers that
    print grey: shifts and levels keep their meaning by text, borders and
    weight. .page-break starts a new printed page.
--}}
<style>
    .ro-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ro-table th,
    .ro-table td {
        border: 1px solid #d1d5db;
        padding: 0.25rem 0.375rem;
        vertical-align: middle;
    }

    .ro-table thead th {
        background: #f3f4f6;
        color: #374151;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    /* Column headings repeat on every printed page; a row never splits across two */
    .ro-table thead {
        display: table-header-group;
    }

    .ro-table tr {
        break-inside: avoid;
    }

    .ro-avoid {
        break-inside: avoid;
    }

    .ro-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.25rem;
        padding: 0 0.25rem;
        border: 1px solid transparent;
        border-radius: 0.25rem;
        font-size: 0.75rem;
        font-weight: 700;
        line-height: 1.25rem;
        white-space: nowrap;
    }

    .ro-level {
        display: inline-flex;
        align-items: center;
        border: 1px solid transparent;
        border-radius: 9999px;
        padding: 0 0.4rem;
        font-size: 0.7rem;
        font-weight: 600;
        line-height: 1.15rem;
        white-space: nowrap;
    }

    .ro-staff {
        display: inline-block;
        border-radius: 0.25rem;
        padding: 0 0.3rem;
        font-size: 0.75rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .ro-small {
        font-size: 0.65rem;
    }

    .ro-write {
        min-width: 9rem;
    }

    .ro-cut {
        border: 1px dashed #9ca3af;
    }

    @media screen {
        .page-break {
            margin-top: 2.5rem;
        }
    }

    @media print {
        .page-break {
            break-before: page;
        }
    }

    /* Black and white */
    .ro-mono,
    .ro-mono * {
        color: #000 !important;
    }

    .ro-mono *:not(.ro-keep) {
        background-color: transparent !important;
        background-image: none !important;
    }

    .ro-mono .ro-muted {
        color: #444 !important;
    }

    .ro-mono .ro-chip,
    .ro-mono .ro-level,
    .ro-mono .ro-staff {
        border: 1px solid #000 !important;
    }

    .ro-mono .ro-chip-OFF {
        border-style: dotted !important;
        font-weight: 400 !important;
    }

    .ro-mono .ro-chip-leave {
        border-style: dashed !important;
        font-style: italic;
    }

    .ro-mono .ro-chip-other {
        border-color: #9ca3af !important;
        font-weight: 400 !important;
    }

    .ro-mono .ro-level-3 {
        border-width: 2px !important;
        font-weight: 700 !important;
    }

    .ro-mono .ro-staff-short {
        border-width: 2px !important;
        font-weight: 800 !important;
    }

    .ro-mono .ro-staff-met,
    .ro-mono .ro-staff-over {
        border-color: #9ca3af !important;
    }

    .ro-mono .ro-chip-ON,
    .ro-mono .ro-level-4,
    .ro-mono .ro-bar {
        background-color: #000 !important;
        color: #fff !important;
    }

    .ro-mono .ro-table th,
    .ro-mono .ro-table td {
        border-color: #6b7280 !important;
    }
</style>
