<x-app-layout>
    @verbatim
    <style>
        /* Command Center V2: its own dark (default) and light theme, sized in em so TV mode scales it all */
        .ccx {
            --page: #0d1117; --surface: #161b22; --surface-2: #1d232c; --line: rgba(255, 255, 255, .08);
            --ink-1: #eef1f5; --ink-2: #b8bec8; --ink-3: #8d95a1;
            --grid: #262d36; --axis: #3a424e;
            --accent: #3987e5; --series-2: #d95926;
            --good: #0ca30c; --warning: #fab219; --serious: #ec835a; --critical: #d03b3b;
            --delta-good: #3fb950; --delta-bad: #f47067; --link: #6cb6ff;
            --flash: rgba(57, 135, 229, .24); --shadow: 0 8px 24px rgba(0, 0, 0, .35);
            --heat: 57 135 229;
            color-scheme: dark;
            container-type: inline-size; container-name: ccx;
            position: relative; min-height: 100%;
            padding: 1.5em 1.75em 2em;
            background: var(--page); color: var(--ink-1);
            font-size: 14px; line-height: 1.45;
        }
        .ccx[data-theme="light"] {
            --page: #f3f3ef; --surface: #fcfcfb; --surface-2: #f1f1ec; --line: rgba(11, 11, 11, .10);
            --ink-1: #0b0b0b; --ink-2: #52514e; --ink-3: #6b6a65;
            --grid: #e1e0d9; --axis: #c3c2b7;
            --accent: #2a78d6; --series-2: #eb6834;
            --delta-good: #006300; --delta-bad: #b42318; --link: #1f5fae;
            --flash: rgba(42, 120, 214, .16); --shadow: 0 8px 24px rgba(11, 11, 11, .12);
            --heat: 42 120 214;
            color-scheme: light;
        }
        .ccx *, .ccx *::before, .ccx *::after { box-sizing: border-box; }
        .ccx p, .ccx h1, .ccx h2, .ccx dl, .ccx dd, .ccx ol { margin: 0; }
        .ccx ol { list-style: none; padding: 0; }
        .ccx a { color: inherit; text-decoration: none; }
        .ccx a:hover { text-decoration: underline; text-underline-offset: 3px; }
        .ccx :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }

        .ccx .is-good { --sev: var(--good); } .ccx .is-warning { --sev: var(--warning); }
        .ccx .is-serious { --sev: var(--serious); } .ccx .is-critical { --sev: var(--critical); }

        /* Refresh countdown along the top edge */
        .ccx-progress { position: absolute; inset: 0 0 auto 0; height: 2px; overflow: hidden; }
        .ccx-progress span { display: block; height: 100%; background: var(--accent); opacity: .7; transform-origin: left; animation: ccx-countdown linear forwards; }
        .ccx-progress span.is-stopped { animation-play-state: paused; opacity: .25; }

        /* Header */
        .ccx-top { display: flex; flex-wrap: wrap; align-items: center; gap: 1em 1.75em; margin-bottom: 1.25em; }
        .ccx-brand { display: flex; align-items: center; gap: .9em; flex: 1 1 24em; min-width: 0; }
        /* The navbar logo is made for the sidebar's brand gradient, so it sits on the same */
        .ccx-logo { display: flex; align-items: center; height: 3.4em; padding: .4em .7em; border-radius: 10px; background: linear-gradient(135deg, rgb(var(--brand-600)), rgb(var(--accent-500))); flex-shrink: 0; }
        .ccx-logo img { max-height: 2.6em; width: auto; }
        .ccx-eyebrow { font-size: .786em; color: var(--ink-3); text-transform: uppercase; letter-spacing: .08em; }
        .ccx h1 { font-size: 1.65em; font-weight: 700; letter-spacing: -.01em; line-height: 1.15; }
        .ccx-sub { font-size: .929em; color: var(--ink-2); margin-top: .1em; }
        .ccx-status { display: flex; flex-direction: column; align-items: flex-end; gap: .35em; }
        .ccx-pill { display: inline-flex; align-items: center; gap: .5em; padding: .3em .8em; border-radius: 999px; background: var(--surface); border: 1px solid var(--line); font-size: .857em; font-weight: 600; }
        .ccx-pill .dot { width: .6em; height: .6em; border-radius: 50%; background: var(--ink-3); }
        .ccx-pill.is-live .dot { background: var(--good); animation: ccx-pulse 2s infinite; }
        .ccx-pill.is-retrying .dot { background: var(--warning); }
        .ccx-pill.is-signed-out .dot { background: var(--critical); }
        .ccx-updated { font-size: .786em; color: var(--ink-3); }
        .ccx-clock { text-align: right; }
        .ccx-clock .time { font-size: 2.3em; font-weight: 650; line-height: 1; letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
        .ccx-clock .sec { font-size: .5em; color: var(--ink-3); margin-left: .08em; }
        .ccx-clock .date { font-size: .857em; color: var(--ink-2); margin-top: .35em; }
        .ccx-actions { display: flex; flex-wrap: wrap; gap: .5em; }
        .ccx-btn { display: inline-flex; align-items: center; gap: .45em; height: 2.45em; padding: 0 .9em; border-radius: 9px; background: var(--surface); border: 1px solid var(--line); color: var(--ink-1); font: inherit; font-size: .857em; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .ccx-btn:hover { background: var(--surface-2); text-decoration: none; }
        .ccx-btn svg { width: 1.25em; height: 1.25em; flex-shrink: 0; }
        .ccx-btn.is-square { width: 2.45em; padding: 0; justify-content: center; }
        .ccx-banner { display: flex; flex-wrap: wrap; align-items: center; gap: .75em; margin-bottom: 1em; padding: .75em 1em; border-radius: 12px; background: var(--surface); border: 1px solid var(--line); border-left: 4px solid var(--critical); }

        /* Cards */
        .ccx-section { display: grid; gap: 1em; margin-bottom: 1em; grid-template-columns: minmax(0, 1fr); }
        .ccx-card { position: relative; min-width: 0; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 1.1em 1.25em 1.2em; }
        .ccx-card > header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5em .75em; margin-bottom: .95em; }
        .ccx h2 { font-size: .857em; font-weight: 650; text-transform: uppercase; letter-spacing: .07em; color: var(--ink-2); }
        .ccx-muted { color: var(--ink-3); font-size: .857em; }
        .ccx-chip { display: inline-flex; align-items: center; gap: .4em; padding: .2em .65em .2em .4em; border-radius: 999px; background: var(--surface-2); border: 1px solid var(--line); font-size: .786em; font-weight: 600; white-space: nowrap; }
        .ccx-ico { display: inline-flex; width: 1.2em; height: 1.2em; flex-shrink: 0; color: var(--sev, var(--ink-3)); }
        .ccx-ico svg { width: 100%; height: 100%; display: block; }
        .ccx .is-changed { animation: ccx-flash 2.4s ease-out; }

        /* Headline figures */
        .ccx-hero { display: flex; align-items: baseline; flex-wrap: wrap; gap: .2em .6em; }
        .ccx-hero .num { font-size: 4em; font-weight: 650; line-height: 1; letter-spacing: -.02em; }
        .ccx-hero .num.is-mid { font-size: 3em; }
        .ccx-hero .cap { color: var(--ink-2); font-size: .929em; }
        .ccx-meter { position: relative; height: .6em; margin: 1.1em 0 .4em; }
        .ccx-meter::before { content: ""; position: absolute; inset: 0; border-radius: 4px; background: var(--m, var(--accent)); opacity: .2; }
        .ccx-meter .fill { position: absolute; inset: 0 auto 0 0; border-radius: 4px; background: var(--m, var(--accent)); transition: width .8s ease; }
        .ccx-meter .tick { position: absolute; top: -.35em; bottom: -.35em; width: 2px; margin-left: -1px; background: var(--ink-2); border-radius: 1px; }
        .ccx-meter.is-warning { --m: var(--warning); } .ccx-meter.is-serious { --m: var(--serious); } .ccx-meter.is-critical { --m: var(--critical); }
        .ccx-meter-legend { display: flex; justify-content: space-between; gap: .5em; font-size: .786em; color: var(--ink-3); }
        .ccx-minis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5em; margin-top: 1em; padding-top: .9em; border-top: 1px solid var(--line); }
        .ccx-minis dt { font-size: .786em; color: var(--ink-3); }
        .ccx-minis dd { font-size: 1.5em; font-weight: 650; line-height: 1.2; }
        .ccx-pair { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1em; }
        .ccx-pair .lbl { font-size: .857em; color: var(--ink-2); }
        .ccx-pair .num { font-size: 3em; font-weight: 650; line-height: 1.05; letter-spacing: -.02em; }
        .ccx-delta { font-size: .786em; color: var(--ink-3); margin-top: .15em; }
        .ccx-delta.is-up-good, .ccx-delta.is-down-good { color: var(--delta-good); }
        .ccx-delta.is-up-bad, .ccx-delta.is-down-bad { color: var(--delta-bad); }
        .ccx-rows { display: grid; gap: .1em; margin-top: 1em; padding-top: .6em; border-top: 1px solid var(--line); }
        .ccx-rows > div { display: flex; align-items: baseline; justify-content: space-between; gap: .75em; padding: .32em 0; }
        .ccx-rows dt { color: var(--ink-2); font-size: .929em; display: inline-flex; align-items: center; gap: .45em; }
        .ccx-rows dd { font-weight: 650; font-size: 1.07em; white-space: nowrap; text-align: right; }
        .ccx-rows dd small, .ccx-minis dd small { font-size: .75em; font-weight: 500; color: var(--ink-3); margin-left: .3em; }
        .ccx-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5em; text-align: center; padding: 1.5em 1em; color: var(--ink-2); }
        .ccx-empty .ccx-ico { width: 2em; height: 2em; }
        .ccx-link { color: var(--link); font-weight: 600; }

        /* Ward table */
        .ccx-tablewrap { overflow-x: auto; margin: 0 -1.25em -1.2em; }
        .ccx-table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .ccx-table th { padding: .55em .65em; font-size: .75em; font-weight: 650; line-height: 1.25; text-transform: uppercase; letter-spacing: .05em; color: var(--ink-3); text-align: right; vertical-align: bottom; border-bottom: 1px solid var(--line); }
        .ccx-table td { padding: .6em .65em; text-align: right; white-space: nowrap; border-bottom: 1px solid var(--line); }
        .ccx-table th:first-child, .ccx-table td:first-child { text-align: left; padding-left: 1.25em; }
        .ccx-table th:last-child, .ccx-table td:last-child { padding-right: 1.25em; }
        .ccx-table tbody tr:hover { background: var(--surface-2); }
        .ccx-table tfoot td { font-weight: 650; border-bottom: 0; border-top: 1px solid var(--axis); }
        .ccx-ward { display: flex; align-items: center; gap: .6em; }
        .ccx-ward .name { font-weight: 600; }
        .ccx-ward .meta { font-size: .786em; color: var(--ink-3); }
        .ccx-tag { display: inline-block; margin-left: .35em; padding: .05em .4em; border-radius: 4px; font-size: .75em; font-weight: 700; color: var(--ink-1); background: var(--surface-2); border: 1px solid var(--line); vertical-align: 1px; }
        .ccx-occ { display: flex; align-items: center; justify-content: flex-end; gap: .6em; }
        .ccx-occ .ccx-meter { width: 6.5em; margin: 0; height: .5em; }
        .ccx-occ .val { min-width: 3.2em; }
        .ccx-sub-num { display: block; font-size: .75em; color: var(--ink-3); }
        .ccx-zero { color: var(--ink-3); }
        .ccx-flag { display: inline-flex; align-items: center; justify-content: flex-end; gap: .35em; }
        .ccx-flag .ccx-ico { width: 1em; height: 1em; }

        /* Attention list */
        .ccx-attention { display: flex; flex-direction: column; }
        .ccx-feedwrap { position: relative; }
        .ccx-feedwrap.has-more::after { content: ""; position: sticky; display: block; bottom: 0; height: 2.5em; margin-top: -2.5em; background: linear-gradient(transparent, var(--surface)); pointer-events: none; }
        .ccx-feed { display: grid; gap: .5em; }
        .ccx-item { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: start; gap: .7em; padding: .65em .75em; border-radius: 10px; background: var(--surface-2); border-left: 3px solid var(--sev); }
        .ccx-item .ccx-ico { margin-top: .1em; }
        .ccx-item .title { font-weight: 600; line-height: 1.3; }
        .ccx-item .detail { font-size: .857em; color: var(--ink-2); margin-top: .15em; }
        .ccx-item .sev { font-size: .75em; font-weight: 650; color: var(--ink-3); text-transform: uppercase; letter-spacing: .05em; }
        .ccx-more { margin-top: .75em; font-size: .857em; color: var(--ink-3); }

        /* Charts */
        .ccx-legend { display: flex; flex-wrap: wrap; gap: .4em 1em; font-size: .857em; color: var(--ink-2); }
        .ccx-legend li { display: inline-flex; align-items: center; gap: .4em; }
        .ccx-key { width: .75em; height: .75em; border-radius: 2px; background: var(--accent); }
        .ccx-key.is-2 { background: var(--series-2); }
        .ccx-plotwrap { position: relative; height: 11em; margin: .6em 0 0 2.6em; }
        .ccx-grid { position: absolute; left: 0; right: 0; border-top: 1px solid var(--grid); }
        .ccx-grid.is-base { border-top-color: var(--axis); }
        .ccx-grid span { position: absolute; right: calc(100% + .55em); top: -.7em; font-size: .75em; color: var(--ink-3); font-variant-numeric: tabular-nums; }
        .ccx-ref { position: absolute; left: 0; right: 0; border-top: 1px dashed var(--ink-3); pointer-events: none; }
        .ccx-ref span { position: absolute; right: 0; bottom: .25em; padding: 0 .35em; font-size: .75em; color: var(--ink-2); background: var(--surface); border-radius: 3px; }
        .ccx-svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
        .ccx-svg .area { fill: var(--accent); opacity: .12; }
        .ccx-svg .line { fill: none; stroke: var(--accent); stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
        .ccx-dot { position: absolute; width: 10px; height: 10px; margin: 0 0 -5px -5px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 2px var(--surface); pointer-events: none; }
        .ccx-cross { position: absolute; top: 0; bottom: 0; border-left: 1px solid var(--ink-3); pointer-events: none; }
        .ccx-hits, .ccx-cols { position: absolute; inset: 0; display: flex; }
        .ccx-hits button, .ccx-cols button { flex: 1 1 0; min-width: 0; height: 100%; padding: 0; margin: 0; border: 0; background: transparent; cursor: default; font: inherit; color: inherit; }
        .ccx-cols button { display: flex; align-items: flex-end; justify-content: center; gap: 2px; position: relative; }
        .ccx-cols .col { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; width: min(12px, 36%); }
        .ccx-cols .bar { width: 100%; border-radius: 4px 4px 0 0; background: var(--accent); transition: height .8s ease; }
        .ccx-cols .bar.is-2 { background: var(--series-2); }
        .ccx-cols .cap { font-size: .75em; font-weight: 600; color: var(--ink-2); line-height: 1; margin-bottom: .3em; }
        .ccx-cols button:hover .bar, .ccx-cols button:focus-visible .bar { filter: brightness(1.2); }
        .ccx-cols button:hover, .ccx-cols button:focus-visible { background: var(--surface-2); border-radius: 4px; }
        .ccx-tip { position: absolute; top: -.25em; z-index: 5; pointer-events: none; padding: .45em .7em; border-radius: 8px; background: var(--surface-2); border: 1px solid var(--line); box-shadow: var(--shadow); font-size: .857em; white-space: nowrap; }
        .ccx-tip .v { font-weight: 650; }
        .ccx-tip .k { color: var(--ink-3); }
        .ccx-tip .row { display: flex; align-items: center; gap: .45em; }
        .ccx-tip .swatch { width: .9em; height: 2px; background: var(--accent); }
        .ccx-tip .swatch.is-2 { background: var(--series-2); }
        .ccx-xaxis { display: flex; justify-content: space-between; margin-left: 2.6em; padding-top: .45em; font-size: .75em; color: var(--ink-3); }
        .ccx-datatable { width: 100%; font-size: .857em; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        .ccx-datatable th, .ccx-datatable td { padding: .3em .4em; text-align: right; border-bottom: 1px solid var(--line); }
        .ccx-datatable th:first-child, .ccx-datatable td:first-child { text-align: left; }
        .ccx-datatable th { color: var(--ink-3); font-weight: 600; }
        .ccx-tablescroll { max-height: 12.5em; overflow-y: auto; margin-top: .6em; }
        .ccx-toggle { font: inherit; font-size: .786em; font-weight: 600; color: var(--ink-2); background: transparent; border: 1px solid var(--line); border-radius: 6px; padding: .15em .55em; cursor: pointer; }
        .ccx-toggle:hover { background: var(--surface-2); }
        .ccx-hbars { display: grid; gap: .85em; }
        .ccx-hbar { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: .3em .75em; align-items: baseline; }
        .ccx-hbar .label { color: var(--ink-2); font-size: .929em; }
        .ccx-hbar .value { font-weight: 650; font-variant-numeric: tabular-nums; }
        .ccx-hbar .value small { font-weight: 500; color: var(--ink-3); margin-left: .35em; }
        .ccx-hbar .track { grid-column: 1 / -1; height: .5em; border-left: 1px solid var(--axis); }
        .ccx-hbar .fill { height: 100%; border-radius: 0 4px 4px 0; background: var(--accent); transition: width .8s ease; }
        .ccx-foot { margin-top: .5em; font-size: .786em; color: var(--ink-3); line-height: 1.6; }

        /* Layout: one column on a phone, the full board on a wall screen. In em, so TV mode's larger type moves them too */
        @container ccx (min-width: 46em) {
            .ccx-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ccx-trends { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @container ccx (min-width: 84em) {
            .ccx-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .ccx-trends { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        /* The ward table beside the attention list only once every column fits */
        @container ccx (min-width: 107em) {
            .ccx-mid { grid-template-columns: minmax(0, 2.1fr) minmax(0, 1fr); }
            /* The list runs as long as the ward table beside it, and scrolls */
            .ccx-feedwrap { flex: 1 1 auto; min-height: 16em; contain: size; overflow-y: auto; overscroll-behavior: contain; }
        }
        @container ccx (max-width: 37em) {
            .ccx-status { align-items: flex-start; }
            .ccx-clock { text-align: left; }
            .ccx-hero .num { font-size: 3.2em; }
        }
        @media (max-width: 560px) { .ccx { padding: 1em 16px 1.5em; } }

        /* TV mode: one landscape screen and nothing below it. The type follows the smaller of the screen's
           width and height, so any landscape TV gets the same layout. The headline cards stay put; beneath
           them the wards and the trends take turns beside the attention list, and each pages through what
           it cannot show at once */
        .ccx.is-tv {
            font-size: clamp(10px, min(.78vw, 1.38vh), 28px);
            height: 100vh; display: flex; flex-direction: column; overflow: hidden;
            padding: 1em 1.25em;
        }
        .ccx.is-tv .ccx-top { margin-bottom: .8em; flex-shrink: 0; }
        .ccx.is-tv .ccx-banner { flex-shrink: 0; }
        .ccx.is-tv .ccx-body { flex: 1 1 auto; min-height: 0; display: grid; gap: .8em; grid-template-columns: minmax(0, 1.125fr) minmax(0, 1.125fr) minmax(0, 1fr); grid-template-rows: auto auto minmax(0, 1fr); }
        .ccx.is-tv .ccx-section { margin: 0; }
        .ccx.is-tv .ccx-kpis { grid-column: 1 / -1; grid-row: 1; gap: .8em; }
        .ccx.is-tv .ccx-mid, .ccx.is-tv .ccx-trends { display: contents; }
        .ccx.is-tv .ccx-pager { grid-column: 1 / span 2; grid-row: 2; }
        .ccx.is-tv .ccx-attention { grid-column: 3; grid-row: 2 / span 2; min-height: 0; }
        /* The pages share one place and cross-fade: the one going fades out while the one coming fades and settles in */
        .ccx.is-tv .ccx-wards, .ccx.is-tv .ccx-acuity { grid-column: 1 / span 2; grid-row: 3; }
        .ccx.is-tv .ccx-census { grid-column: 1; grid-row: 3; }
        .ccx.is-tv .ccx-flowchart { grid-column: 2; grid-row: 3; }
        .ccx.is-tv .ccx-page { min-height: 0; opacity: 0; transform: scale(.985); visibility: hidden; pointer-events: none;
            transition: opacity .6s ease, transform .6s ease, visibility 0s linear .6s; }
        .ccx.is-tv .ccx-page.is-current { opacity: 1; transform: none; visibility: visible; pointer-events: auto;
            transition: opacity .7s ease .2s, transform .8s cubic-bezier(.2, .7, .2, 1) .2s, visibility 0s linear 0s; }
        .ccx.is-tv .ccx-page.is-current.is-later { transition-delay: .4s, .4s, 0s; }
        .ccx.is-tv .ccx-foot, .ccx.is-tv .ccx-toggle { display: none; }
        /* The headline cards, compact: their detail rows side by side */
        .ccx.is-tv .ccx-card { padding: .85em 1.05em .95em; }
        .ccx.is-tv .ccx-card > header { margin-bottom: .6em; }
        .ccx.is-tv .ccx-hero .num { font-size: 3.1em; }
        .ccx.is-tv .ccx-hero .num.is-mid, .ccx.is-tv .ccx-pair .num { font-size: 2.5em; }
        .ccx.is-tv .ccx-meter { margin: .75em 0 .35em; }
        .ccx.is-tv .ccx-minis { margin-top: .7em; padding-top: .6em; }
        .ccx.is-tv .ccx-minis dd { font-size: 1.3em; }
        .ccx.is-tv .ccx-rows { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .55em .9em; margin-top: .7em; padding-top: .65em; }
        .ccx.is-tv .ccx-rows > div { flex-direction: column; align-items: flex-start; justify-content: flex-start; gap: .1em; padding: 0; }
        .ccx.is-tv .ccx-rows dt { font-size: .8em; }
        .ccx.is-tv .ccx-rows dd { text-align: left; white-space: normal; }
        .ccx.is-tv .ccx-rows dd small { margin-left: .25em; }
        /* Wards and attention: page by page, the table's header and totals staying put. The space after
           the last row lets the last page start at the top like the others */
        .ccx.is-tv .ccx-wards { display: flex; flex-direction: column; }
        .ccx.is-tv .ccx-wards .ccx-tablewrap { margin: 0 -1.05em -.95em; }
        .ccx.is-tv .ccx-wards .ccx-tablewrap, .ccx.is-tv .ccx-feedwrap { flex: 1 1 auto; min-height: 0; contain: size; overflow: hidden auto; scrollbar-width: none; }
        .ccx.is-tv .ccx-wards .ccx-tablewrap::-webkit-scrollbar, .ccx.is-tv .ccx-feedwrap::-webkit-scrollbar { display: none; }
        .ccx.is-tv .ccx-wards .ccx-tablewrap::after, .ccx.is-tv .ccx-feedwrap::after { content: ""; display: block; position: static; height: 100%; margin: 0; background: none; }
        .ccx.is-tv .ccx-table thead th { position: sticky; top: 0; z-index: 1; background: var(--surface); }
        .ccx.is-tv .ccx-table tfoot td { position: sticky; bottom: 0; z-index: 1; background: var(--surface); }
        .ccx.is-tv .ccx-table tbody tr:hover { background: none; }
        .ccx.is-tv .is-off { visibility: hidden; }
        /* Trends: the two charts across the whole page */
        .ccx.is-tv .ccx-trends > .ccx-card { display: flex; flex-direction: column; }
        .ccx.is-tv .ccx-chartbody { flex: 1 1 auto; display: flex; flex-direction: column; min-height: 0; }
        .ccx.is-tv .ccx-plotwrap { flex: 1 1 auto; height: auto; min-height: 6em; }
        /* Acuity: a tile each, then every ward's share as a heat table (one hue, darker = a bigger share) */
        .ccx.is-tv .ccx-acuity .ccx-hbars { grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1em 1.8em; }
        .ccx.is-tv .ccx-acuity .ccx-hbar { grid-template-columns: minmax(0, 1fr); gap: .3em; }
        .ccx.is-tv .ccx-acuity .ccx-hbar .value { font-size: 2.3em; line-height: 1.05; }
        .ccx.is-tv .ccx-acuity .ccx-hbar .value small { font-size: .42em; }
        .ccx-matrixwrap, .ccx-heatkey { display: none; }
        .ccx.is-tv .ccx-heatkey { display: inline-flex; align-items: center; gap: .45em; font-size: .786em; color: var(--ink-3); }
        .ccx-heatkey .ramp { width: 6em; height: .6em; border-radius: 3px; background: linear-gradient(90deg, rgb(var(--heat) / .16), rgb(var(--heat) / .8)); }
        .ccx.is-tv .ccx-matrixwrap { display: block; flex: 1 1 auto; min-height: 0; contain: size; overflow: hidden auto; scrollbar-width: none; margin: 1.2em -1.05em -.95em; border-top: 1px solid var(--line); }
        .ccx.is-tv .ccx-matrixwrap::-webkit-scrollbar { display: none; }
        .ccx.is-tv .ccx-matrixwrap::after { content: ""; display: block; height: 100%; }
        .ccx-matrix th:nth-child(n + 3), .ccx-matrix td:nth-child(n + 3) { text-align: center; }
        .ccx-matrix td.heat { font-weight: 650; border: 2px solid var(--surface); border-radius: 6px; }
        /* Which page is up, and how long it has left */
        .ccx-pager { display: flex; flex-wrap: wrap; align-items: center; gap: .5em; }
        .ccx-tab { display: inline-flex; align-items: center; gap: .45em; padding: .3em .95em; border-radius: 999px; background: var(--surface); border: 1px solid var(--line); color: var(--ink-2); font: inherit; font-size: .857em; font-weight: 650; cursor: pointer; }
        .ccx-tab.is-on { color: var(--ink-1); border-color: var(--accent); box-shadow: inset 0 0 0 1px var(--accent); }
        .ccx-tab .count { color: var(--ink-3); font-weight: 600; }
        .ccx-hold { margin-left: auto; }
        .ccx-hold svg { width: 1.05em; height: 1.05em; }
        .ccx-pager .bar { flex: 1 0 100%; height: 2px; overflow: hidden; border-radius: 1px; background: var(--grid); }
        .ccx-pager .bar span { display: block; height: 100%; background: var(--accent); transform-origin: left; animation: ccx-countdown linear forwards; }
        .ccx-pager .bar span.is-stopped { animation-play-state: paused; }
        .ccx-pagecount { font-size: .786em; font-weight: 600; color: var(--ink-3); }

        @keyframes ccx-countdown { from { transform: scaleX(0); } to { transform: scaleX(1); } }
        @keyframes ccx-flash { from { background-color: var(--flash); } to { background-color: transparent; } }
        @keyframes ccx-pulse {
            0% { box-shadow: 0 0 0 0 rgba(12, 163, 12, .55); }
            70% { box-shadow: 0 0 0 .5em rgba(12, 163, 12, 0); }
            100% { box-shadow: 0 0 0 0 rgba(12, 163, 12, 0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .ccx *, .ccx *::before { animation: none !important; transition: none !important; }
        }
    </style>
    @endverbatim

    <div class="ccx" data-theme="dark" x-data="commandCenterV2" data-url="{{ route('command-center-v2.data') }}"
        :data-theme="theme" :class="{ 'is-tv': tv }" @keydown.escape.window="tv && setTv(false)">
        <script>
            // The theme this screen was left on, before the first paint
            try { if (localStorage.getItem('ccv2Theme') === 'light') document.currentScript.parentElement.dataset.theme = 'light'; } catch (e) {}
        </script>

        <div class="ccx-progress" aria-hidden="true">
            <template x-for="c in [cycle]" :key="c">
                <span :class="{ 'is-stopped': connection !== 'live' }" :style="`animation-duration: ${s.refresh_seconds}s`"></span>
            </template>
        </div>

        {{-- ------ Header: whose board, how fresh, the time ------ --}}
        <header class="ccx-top">
            <div class="ccx-brand">
                <span class="ccx-logo"><img src="{{ $logoUrl }}" alt="{{ $hospital?->name ?? 'Hospital' }} logo"></span>
                <div style="min-width: 0">
                    <p class="ccx-eyebrow">{{ $hospital?->name ?? config('app.name') }}</p>
                    <h1>Command Center V2</h1>
                    <p class="ccx-sub">Executive overview &middot; every ward, live</p>
                </div>
            </div>

            <div class="ccx-status" role="status" aria-live="polite">
                <span class="ccx-pill" :class="'is-' + connection"><span class="dot"></span><span x-text="connectionLabel">Live</span></span>
                <span class="ccx-updated" x-text="(connection === 'live' ? 'Updated ' : 'Last update ') + s.generated_time + ' · every ' + s.refresh_seconds + ' s'"></span>
            </div>

            <div class="ccx-clock" aria-hidden="true">
                <p class="time"><span x-text="clockTime"></span><span class="sec" x-text="clockSeconds"></span></p>
                <p class="date" x-text="clockDate"></p>
            </div>

            <div class="ccx-actions">
                <a href="{{ route('command-center.index') }}" class="ccx-btn" x-show="!tv" title="The Command Center: every patient needing attention, and the period analytics">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7" /></svg>
                    Operations view
                </a>
                <button type="button" class="ccx-btn is-square" @click="toggleTheme()" :aria-label="theme === 'dark' ? 'Switch to the light theme' : 'Switch to the dark theme'" :title="theme === 'dark' ? 'Light theme' : 'Dark theme'">
                    <svg x-show="theme === 'dark'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                    <svg x-show="theme === 'light'" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                </button>
                <button type="button" class="ccx-btn" @click="setTv(!tv)" :aria-pressed="tv.toString()" title="Full screen for the command centre wall, without the menu">
                    <svg x-show="!tv" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                    <svg x-show="tv" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    <span x-text="tv ? 'Exit TV mode' : 'TV mode'">TV mode</span>
                </button>
            </div>
        </header>

        <div class="ccx-banner" x-show="connection === 'signed-out'" x-cloak role="alert">
            <span class="ccx-ico is-critical" x-html="icon('critical')"></span>
            <span>Signed out, so these figures have stopped updating.</span>
            <button type="button" class="ccx-btn" @click="window.location.reload()">Sign in again</button>
        </div>

        <div class="ccx-body">
        {{-- ------ The four areas management answers for ------ --}}
        <section class="ccx-section ccx-kpis" aria-label="Key figures">
            {{-- Capacity --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.capacity }">
                <header>
                    <h2>Bed occupancy</h2>
                    <span class="ccx-chip" :class="'is-' + (s.capacity.status || 'good')" x-show="s.capacity.status">
                        <span class="ccx-ico" x-html="icon(s.capacity.status)"></span>
                        <span x-text="capacityLabel(s.capacity.status)"></span>
                    </span>
                </header>
                <div class="ccx-hero">
                    <span class="num" x-text="s.capacity.occupancy === null ? '—' : dec(s.capacity.occupancy) + '%'"></span>
                    <span class="cap" x-text="`${fmt(s.capacity.occupied)} of ${fmt(s.capacity.beds)} beds occupied`"></span>
                </div>
                <div class="ccx-meter" :class="'is-' + (s.capacity.status || 'good')" role="img"
                    :aria-label="`Occupancy ${dec(s.capacity.occupancy)}% against a target of ${s.targets.occupancy}%`">
                    <span class="fill" :style="`width: ${Math.min(100, s.capacity.occupancy || 0)}%`"></span>
                    <span class="tick" :style="`left: ${s.targets.occupancy}%`"></span>
                </div>
                <div class="ccx-meter-legend"><span>0%</span><span x-text="`Target ${s.targets.occupancy}% or under`"></span><span>100%</span></div>
                <dl class="ccx-minis">
                    <div><dt>Free beds</dt><dd x-text="fmt(s.capacity.free)"></dd></div>
                    <div><dt>Prebooked</dt><dd x-text="fmt(s.capacity.incoming)"></dd></div>
                    <div><dt>To discharge</dt><dd x-text="fmt(s.capacity.pending_discharge)"></dd></div>
                </dl>
                <p class="ccx-muted" style="margin-top: .75em" x-show="s.capacity.critical_care"
                    x-text="s.capacity.critical_care ? `Critical care: ${s.capacity.critical_care.occupied} of ${s.capacity.critical_care.beds} beds · ${s.capacity.critical_care.ventilated} ventilated` : ''"></p>
            </article>

            {{-- Patient flow --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.flow }">
                <header>
                    <h2>Patient flow today</h2>
                    <span class="ccx-muted">Since midnight</span>
                </header>
                <div class="ccx-pair">
                    <div>
                        <p class="lbl">Admissions</p>
                        <p class="num" x-text="fmt(s.flow.admissions_today)"></p>
                        <p class="ccx-delta" x-text="versus(s.flow.admissions_today, s.flow.admissions_same_time_yesterday, 'same time yesterday')"></p>
                    </div>
                    <div>
                        <p class="lbl">Discharges</p>
                        <p class="num" x-text="fmt(s.flow.discharges_today)"></p>
                        <p class="ccx-delta" x-text="versus(s.flow.discharges_today, s.flow.discharges_same_time_yesterday, 'same time yesterday')"></p>
                    </div>
                </div>
                <dl class="ccx-rows">
                    <div>
                        <dt x-text="`Discharged by ${hour(s.targets.noon_hour)}`"></dt>
                        <dd><span x-text="pct(s.flow.discharged_by_noon_pct)"></span><small x-text="`${s.flow.discharged_by_noon} of ${s.flow.discharges_today}`"></small></dd>
                    </div>
                    <div>
                        <dt x-text="`By ${hour(s.targets.noon_hour)}, last ${s.targets.los_days} days`"></dt>
                        <dd><span x-text="pct(s.flow.discharged_by_noon_pct_period)"></span><small x-text="`of ${fmt(s.flow.discharges_period)}`"></small></dd>
                    </div>
                    <div>
                        <dt x-text="`Average stay, last ${s.targets.los_days} days`"></dt>
                        <dd>
                            <span x-text="days(s.flow.alos)"></span>
                            <small class="ccx-delta" style="display: inline" :class="alosTone" x-text="alosChange"></small>
                        </dd>
                    </div>
                </dl>
            </article>

            {{-- Quality & safety --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.quality }">
                <header>
                    <h2>Quality &amp; safety</h2>
                    <span class="ccx-chip" :class="'is-' + s.quality.status">
                        <span class="ccx-ico" x-html="icon(s.quality.status)"></span>
                        <span x-text="qualityLabel(s.quality.status)"></span>
                    </span>
                </header>
                <div class="ccx-hero">
                    <span class="num is-mid" x-text="fmt(s.quality.needs_attention)"></span>
                    <span class="cap" x-text="`of ${fmt(s.quality.inpatients)} inpatients need attention now`"></span>
                </div>
                <dl class="ccx-rows">
                    <div>
                        <dt><span class="ccx-ico is-serious" x-show="s.quality.ews_urgent" x-html="icon('serious')"></span>EWS 5 or more</dt>
                        <dd><span x-text="fmt(s.quality.ews_urgent)"></span><small x-text="`${fmt(s.quality.ews_warning)} at 3–4`"></small></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico is-serious" x-show="s.quality.escalations" x-html="icon('serious')"></span>Monitor escalations</dt>
                        <dd x-text="fmt(s.quality.escalations)"></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico is-warning" x-show="s.quality.overdue_care" x-html="icon('warning')"></span>Care overdue</dt>
                        <dd><span x-text="fmt(s.quality.overdue_care)"></span><small>patients</small></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico is-warning" x-show="s.quality.obs_due > s.quality.obs_done" x-html="icon('warning')"></span><span x-text="`Vital signs in ${s.targets.obs_hours} h`"></span></dt>
                        <dd><span x-text="pct(s.quality.obs_pct)"></span><small x-text="`${fmt(s.quality.obs_done)} of ${fmt(s.quality.obs_due)}`"></small></dd>
                    </div>
                    <div>
                        <dt><span class="ccx-ico" :class="s.quality.alerts.waiting_urgent ? 'is-critical' : 'is-warning'" x-show="s.quality.alerts.waiting" x-html="icon(s.quality.alerts.waiting_urgent ? 'critical' : 'warning')"></span>Alerts waiting</dt>
                        <dd><span x-text="fmt(s.quality.alerts.pending)"></span><small x-text="`${fmt(s.quality.alerts.urgent)} urgent`"></small></dd>
                    </div>
                    <div>
                        <dt>Median response today</dt>
                        <dd><span x-text="mins(s.quality.alerts.median_today)"></span><small x-text="`7-day ${mins(s.quality.alerts.median_week)}`"></small></dd>
                    </div>
                </dl>
            </article>

            {{-- Workforce --}}
            <article class="ccx-card" :class="{ 'is-changed': changed.workforce }">
                <header>
                    <h2>Nurses on shift</h2>
                    <span class="ccx-chip" :class="'is-' + (s.workforce.status || 'good')" x-show="s.workforce.status">
                        <span class="ccx-ico" x-html="icon(s.workforce.status)"></span>
                        <span x-text="workforceLabel(s.workforce.status)"></span>
                    </span>
                </header>
                <template x-if="s.workforce.rostered_wards > 0">
                    <div>
                        <div class="ccx-hero">
                            <span class="num" x-text="dec(s.workforce.ratio)"></span>
                            <span class="cap">patients per nurse on duty</span>
                        </div>
                        <div class="ccx-meter" :class="'is-' + (s.workforce.coverage !== null && s.workforce.coverage < 100 ? 'serious' : 'good')" role="img"
                            :aria-label="`${s.workforce.on_duty} nurses on duty against a minimum of ${s.workforce.minimum}`">
                            <span class="fill" :style="`width: ${Math.min(100, s.workforce.coverage || 0)}%`"></span>
                        </div>
                        <div class="ccx-meter-legend"><span x-text="`${pct(s.workforce.coverage)} of minimum staffing`"></span><span x-text="s.workforce.shifts.join(' · ') + ' shift'"></span></div>
                        <dl class="ccx-rows">
                            <div><dt>Nurses on duty</dt><dd><span x-text="fmt(s.workforce.on_duty)"></span><small x-text="`minimum ${fmt(s.workforce.minimum)}`"></small></dd></div>
                            <div><dt>Wards below minimum</dt><dd><span x-text="fmt(s.workforce.below_minimum)"></span><small x-text="`of ${fmt(s.workforce.rostered_wards)} rostered`"></small></dd></div>
                            <div><dt>Wards without a roster</dt><dd x-text="fmt(s.workforce.wards - s.workforce.rostered_wards)"></dd></div>
                        </dl>
                    </div>
                </template>
                <template x-if="s.workforce.rostered_wards === 0">
                    <div class="ccx-empty">
                        <p>No ward has a roster for the shift on now.</p>
                        <a href="{{ route('ward.ai-schedule') }}" class="ccx-link">Plan one in the AI Nurse Schedule</a>
                    </div>
                </template>
            </article>
        </section>

        {{-- ------ TV mode: the page up beside the attention list, and how long it has left ------ --}}
        <nav class="ccx-pager" x-show="tv" x-cloak aria-label="Pages">
            <template x-for="page in tvPages" :key="page.key">
                <button type="button" class="ccx-tab" :class="{ 'is-on': tvPage === page.key }" @click="showTv(page.key)" :aria-current="tvPage === page.key">
                    <span x-text="page.label"></span>
                    <span class="count" x-show="tvPage === page.key && subPages > 1" x-text="`${subPage + 1}/${subPages}`"></span>
                </button>
            </template>
            <button type="button" class="ccx-tab ccx-hold" :class="{ 'is-on': paused }" @click="paused = !paused"
                :aria-pressed="paused.toString()" :title="paused ? 'Carry on turning the pages' : 'Keep this page up'">
                <svg x-show="!paused" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><rect x="5" y="4" width="3.5" height="12" rx="1" /><rect x="11.5" y="4" width="3.5" height="12" rx="1" /></svg>
                <svg x-show="paused" x-cloak viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.5 4.2v11.6a.8.8 0 0 0 1.2.7l9-5.8a.8.8 0 0 0 0-1.4l-9-5.8a.8.8 0 0 0-1.2.7Z" /></svg>
                <span x-text="paused ? 'Paused · resume' : 'Pause'">Pause</span>
            </button>
            <span class="bar" aria-hidden="true">
                <template x-for="k in [stepKey]" :key="k">
                    <span :class="{ 'is-stopped': paused }" :style="`animation-duration: ${stepSeconds}s`"></span>
                </template>
            </span>
        </nav>

        {{-- ------ Every ward, and what needs attention ------ --}}
        <section class="ccx-section ccx-mid">
            <article class="ccx-card ccx-wards ccx-page" :class="{ 'is-current': tvPage === 'wards' }">
                <header>
                    <h2>Wards</h2>
                    <span class="ccx-muted" x-show="!tv">A ward's name opens its dashboard</span>
                    <span class="ccx-pagecount" x-show="tv && tvPage === 'wards' && subPages > 1" x-text="`Page ${subPage + 1} of ${subPages}`"></span>
                </header>
                <div class="ccx-tablewrap" x-ref="wards">
                    <table class="ccx-table">
                        <thead>
                            <tr>
                                <th scope="col">Ward</th>
                                <th scope="col">Occupancy</th>
                                <th scope="col" title="Available beds">Free</th>
                                <th scope="col" title="Prebooked, waiting for a bed">Prebooked</th>
                                <th scope="col" title="Pending discharge">To discharge</th>
                                <th scope="col" title="Admitted and discharged since midnight">In / out today</th>
                                <th scope="col" title="Patients with an EWS of 5 or more, from vital signs in the last 24 h">EWS&nbsp;5+</th>
                                <th scope="col" title="Patients with monitored assessments or medication doses overdue">Care overdue</th>
                                <th scope="col" title="Inpatients with vital signs in the last 24 h">Vitals 24&nbsp;h</th>
                                <th scope="col" title="Ward alerts waiting for a response">Alerts</th>
                                <th scope="col" title="Nurses on the current shift in the AI Nurse Schedule, against the ward's minimum">Nurses</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="w in s.wards" :key="w.id">
                                <tr data-row :class="{ 'is-changed': changed['ward.' + w.id] }">
                                    <td>
                                        <div class="ccx-ward">
                                            <span class="ccx-ico" :class="'is-' + w.status" x-html="icon(w.status)" role="img" :aria-label="sevLabel(w.status)" :title="sevLabel(w.status)"></span>
                                            <div>
                                                <a :href="w.url" class="name" x-text="w.name"></a><span class="ccx-tag" x-show="w.critical_care" title="Critical care ward">CC</span>
                                                <div class="meta" x-text="[w.code, w.type].filter(Boolean).join(' · ')"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="ccx-occ">
                                            <div class="ccx-meter" :class="'is-' + (w.occupancy_status || 'good')" aria-hidden="true">
                                                <span class="fill" :style="`width: ${Math.min(100, w.occupancy || 0)}%`"></span>
                                                <span class="tick" :style="`left: ${s.targets.occupancy}%`"></span>
                                            </div>
                                            <span class="val">
                                                <span x-text="w.occupancy === null ? '—' : dec(w.occupancy) + '%'"></span>
                                                <span class="ccx-sub-num" x-text="`${w.occupied}/${w.beds}`"></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td x-text="fmt(w.free)"></td>
                                    <td><span :class="{ 'ccx-zero': !w.incoming }" x-text="dash(w.incoming)"></span></td>
                                    <td><span :class="{ 'ccx-zero': !w.pending_discharge }" x-text="dash(w.pending_discharge)"></span></td>
                                    <td x-text="`${w.admissions_today} / ${w.discharges_today}`"></td>
                                    <td>
                                        <span class="ccx-flag">
                                            <span class="ccx-ico is-serious" x-show="w.ews_urgent" x-html="icon('serious')"></span>
                                            <span :class="{ 'ccx-zero': !w.ews_urgent }" x-text="dash(w.ews_urgent)"></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="ccx-flag">
                                            <span class="ccx-ico is-warning" x-show="w.overdue_care" x-html="icon('warning')"></span>
                                            <span :class="{ 'ccx-zero': !w.overdue_care }" x-text="dash(w.overdue_care)"></span>
                                        </span>
                                    </td>
                                    <td>
                                        <span x-text="pct(w.obs_pct)"></span>
                                        <span class="ccx-sub-num" x-show="w.obs_due" x-text="`${w.obs_done} of ${w.obs_due}`"></span>
                                    </td>
                                    <td>
                                        <span class="ccx-flag">
                                            <span class="ccx-ico" :class="w.alerts_waiting_urgent ? 'is-critical' : 'is-warning'" x-show="w.alerts_waiting" x-html="icon(w.alerts_waiting_urgent ? 'critical' : 'warning')"></span>
                                            <span :class="{ 'ccx-zero': !w.alerts }" x-text="dash(w.alerts)"></span>
                                        </span>
                                        <span class="ccx-sub-num" x-show="w.alerts_urgent" x-text="`${w.alerts_urgent} urgent`"></span>
                                    </td>
                                    <td>
                                        <template x-if="w.staffing">
                                            <div>
                                                <span class="ccx-flag">
                                                    <span class="ccx-ico is-serious" x-show="w.staffing.status === 'serious'" x-html="icon('serious')"></span>
                                                    <span x-text="`${w.staffing.on_duty} of ${w.staffing.minimum}`"></span>
                                                </span>
                                                <span class="ccx-sub-num" x-text="w.staffing.shift + (w.staffing.ratio === null ? '' : ` · ${dec(w.staffing.ratio)} per nurse`)"></span>
                                            </div>
                                        </template>
                                        <template x-if="!w.staffing">
                                            <span class="ccx-zero" title="No roster for this ward's current shift">No roster</span>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="s.wards.length === 0">
                                <td colspan="11" style="text-align: center; padding: 2em" class="ccx-zero">No active wards yet</td>
                            </tr>
                        </tbody>
                        <tfoot x-show="s.wards.length > 1">
                            <tr>
                                <td>All wards</td>
                                <td>
                                    <span x-text="s.capacity.occupancy === null ? '—' : dec(s.capacity.occupancy) + '%'"></span>
                                    <span class="ccx-sub-num" x-text="`${s.capacity.occupied}/${s.capacity.beds}`"></span>
                                </td>
                                <td x-text="fmt(s.capacity.free)"></td>
                                <td x-text="fmt(s.capacity.incoming)"></td>
                                <td x-text="fmt(s.capacity.pending_discharge)"></td>
                                <td x-text="`${s.flow.admissions_today} / ${s.flow.discharges_today}`"></td>
                                <td x-text="fmt(s.quality.ews_urgent)"></td>
                                <td x-text="fmt(s.quality.overdue_care)"></td>
                                <td x-text="pct(s.quality.obs_pct)"></td>
                                <td x-text="fmt(s.quality.alerts.pending)"></td>
                                <td x-text="s.workforce.rostered_wards ? `${s.workforce.on_duty} of ${s.workforce.minimum}` : '—'"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </article>

            <article class="ccx-card ccx-attention" aria-labelledby="ccx-attention-title">
                <header>
                    <h2 id="ccx-attention-title">Needs attention</h2>
                    <span class="ccx-muted" x-text="(s.attention_total ? `${s.attention_total} · most serious first` : '') + (tv && feedPages > 1 ? ` · page ${feedPage + 1} of ${feedPages}` : '')"></span>
                </header>
                <div class="ccx-feedwrap" x-ref="feed" :class="{ 'has-more': feedMore }" @scroll.passive="checkFeed()">
                <ol class="ccx-feed">
                    <template x-for="item in s.attention" :key="item.key">
                        <li class="ccx-item" :class="['is-' + item.severity, { 'is-changed': changed['item.' + item.key] }]">
                            <span class="ccx-ico" x-html="icon(item.severity)"></span>
                            <div>
                                <template x-if="item.url"><a :href="item.url" class="title" x-text="item.title"></a></template>
                                <template x-if="!item.url"><p class="title" x-text="item.title"></p></template>
                                <p class="detail" x-text="item.detail"></p>
                            </div>
                            <span class="sev" x-text="sevLabel(item.severity)"></span>
                        </li>
                    </template>
                </ol>
                <div class="ccx-empty" x-show="s.attention.length === 0">
                    <span class="ccx-ico is-good" x-html="icon('good')"></span>
                    <p><strong>Nothing needs attention</strong></p>
                    <p class="ccx-muted">Every ward is within its thresholds right now.</p>
                </div>
                </div>
                <p class="ccx-more" x-show="feedMore || s.attention_total > s.attention.length"
                    x-text="[feedMore ? 'Scroll for more' : '', s.attention_total > s.attention.length ? `${s.attention_total - s.attention.length} more not listed: see the ward table` : ''].filter(Boolean).join(' · ')"></p>
            </article>
        </section>

        {{-- ------ Trends ------ --}}
        <section class="ccx-section ccx-trends">
            {{-- Midnight census --}}
            <article class="ccx-card ccx-census ccx-page" :class="{ 'is-current': tvPage === 'trends' }">
                <header>
                    <h2 x-text="`Inpatients at midnight · ${s.trend.census.length} days`"></h2>
                    <button type="button" class="ccx-toggle" @click="tables.census = !tables.census" x-text="tables.census ? 'Chart' : 'Table'" :aria-pressed="tables.census.toString()"></button>
                </header>
                <div class="ccx-chartbody" x-show="tv || !tables.census">
                    <div class="ccx-plotwrap" @mouseleave="hover.census = null">
                        <template x-for="t in census.ticks" :key="'c' + t">
                            <div class="ccx-grid" :class="{ 'is-base': t === 0 }" :style="`bottom: ${t / census.max * 100}%`"><span x-text="fmt(t)"></span></div>
                        </template>
                        <div class="ccx-ref" x-show="s.capacity.beds" :style="`bottom: ${s.capacity.beds / census.max * 100}%`"><span x-text="`${fmt(s.capacity.beds)} beds`"></span></div>
                        <svg class="ccx-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <path class="area" :d="census.area"></path>
                            <path class="line" :d="census.line" vector-effect="non-scaling-stroke"></path>
                        </svg>
                        <span class="ccx-dot" :style="`left: ${band(s.trend.census.length - 1, s.trend.census.length)}%; bottom: ${s.trend.census[s.trend.census.length - 1] / census.max * 100}%`"></span>
                        <div class="ccx-cross" x-show="hover.census !== null" :style="`left: ${band(hover.census, s.trend.census.length)}%`"></div>
                        <div class="ccx-hits">
                            <template x-for="(v, i) in s.trend.census" :key="'ch' + i">
                                <button type="button" @mouseenter="hover.census = i" @focus="hover.census = i" @blur="hover.census = null"
                                    :aria-label="`${s.trend.days[i]}: ${v} inpatients`"></button>
                            </template>
                        </div>
                        <div class="ccx-tip" x-show="hover.census !== null" x-cloak :style="tipAt(hover.census, s.trend.census.length)">
                            <template x-if="hover.census !== null">
                                <div>
                                    <div class="k" x-text="s.trend.days[hover.census] + (hover.census === s.trend.census.length - 1 ? ' · now' : ' · midnight')"></div>
                                    <div><span class="v" x-text="fmt(s.trend.census[hover.census])"></span> inpatients</div>
                                    <div class="k" x-show="s.capacity.beds" x-text="`${dec(s.trend.census[hover.census] / s.capacity.beds * 100)}% of ${s.capacity.beds} beds`"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="ccx-xaxis"><span x-text="s.trend.labels[0]"></span><span x-text="s.trend.labels[Math.floor(s.trend.labels.length / 2)]"></span><span>Now</span></div>
                </div>
                <div class="ccx-tablescroll" x-show="tables.census && !tv" x-cloak>
                    <table class="ccx-datatable">
                        <thead><tr><th scope="col">Day</th><th scope="col">Inpatients</th><th scope="col">Occupancy</th></tr></thead>
                        <tbody>
                            <template x-for="(v, i) in s.trend.census" :key="'ct' + i">
                                <tr><td x-text="s.trend.days[i]"></td><td x-text="fmt(v)"></td><td x-text="s.capacity.beds ? dec(v / s.capacity.beds * 100) + '%' : '—'"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>

            {{-- Admissions and discharges per day --}}
            <article class="ccx-card ccx-flowchart ccx-page is-later" :class="{ 'is-current': tvPage === 'trends' }">
                <header>
                    <h2 x-text="`Admissions & discharges · ${s.trend.admissions.length} days`"></h2>
                    <button type="button" class="ccx-toggle" @click="tables.flow = !tables.flow" x-text="tables.flow ? 'Chart' : 'Table'" :aria-pressed="tables.flow.toString()"></button>
                </header>
                <ul class="ccx-legend" style="list-style: none; padding: 0; margin: 0">
                    <li><span class="ccx-key"></span>Admissions</li>
                    <li><span class="ccx-key is-2"></span>Discharges</li>
                </ul>
                <div class="ccx-chartbody" x-show="tv || !tables.flow">
                    <div class="ccx-plotwrap" @mouseleave="hover.flow = null">
                        <template x-for="t in flowScale.ticks" :key="'f' + t">
                            <div class="ccx-grid" :class="{ 'is-base': t === 0 }" :style="`bottom: ${t / flowScale.max * 100}%`"><span x-text="fmt(t)"></span></div>
                        </template>
                        <div class="ccx-cols">
                            <template x-for="(a, i) in s.trend.admissions" :key="'fb' + i">
                                <button type="button" @mouseenter="hover.flow = i" @focus="hover.flow = i" @blur="hover.flow = null"
                                    :aria-label="`${s.trend.days[i]}: ${a} admitted, ${s.trend.discharges[i]} discharged`">
                                    <span class="col">
                                        <span class="cap" x-show="i === s.trend.admissions.length - 1" x-text="a"></span>
                                        <span class="bar" :style="`height: ${a / flowScale.max * 100}%`"></span>
                                    </span>
                                    <span class="col">
                                        <span class="cap" x-show="i === s.trend.admissions.length - 1" x-text="s.trend.discharges[i]"></span>
                                        <span class="bar is-2" :style="`height: ${s.trend.discharges[i] / flowScale.max * 100}%`"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                        <div class="ccx-tip" x-show="hover.flow !== null" x-cloak :style="tipAt(hover.flow, s.trend.admissions.length)">
                            <template x-if="hover.flow !== null">
                                <div>
                                    <div class="k" x-text="s.trend.days[hover.flow] + (hover.flow === s.trend.admissions.length - 1 ? ' · so far' : '')"></div>
                                    <div class="row"><span class="swatch"></span><span class="v" x-text="fmt(s.trend.admissions[hover.flow])"></span><span class="k">admitted</span></div>
                                    <div class="row"><span class="swatch is-2"></span><span class="v" x-text="fmt(s.trend.discharges[hover.flow])"></span><span class="k">discharged</span></div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="ccx-xaxis"><span x-text="s.trend.labels[0]"></span><span x-text="s.trend.labels[Math.floor(s.trend.labels.length / 2)]"></span><span>Today</span></div>
                </div>
                <div class="ccx-tablescroll" x-show="tables.flow && !tv" x-cloak>
                    <table class="ccx-datatable">
                        <thead><tr><th scope="col">Day</th><th scope="col">Admitted</th><th scope="col">Discharged</th></tr></thead>
                        <tbody>
                            <template x-for="(a, i) in s.trend.admissions" :key="'ft' + i">
                                <tr><td x-text="s.trend.days[i]"></td><td x-text="fmt(a)"></td><td x-text="fmt(s.trend.discharges[i])"></td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>

            {{-- Acuity and risk; in TV mode also each ward's share, as a heat table --}}
            <article class="ccx-card ccx-acuity ccx-page" :class="{ 'is-current': tvPage === 'acuity' }">
                <header>
                    <h2>Acuity &amp; risk</h2>
                    <span style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: .4em 1.4em">
                        <span class="ccx-muted" x-text="`Share of ${fmt(s.quality.inpatients)} inpatients`"></span>
                        <span class="ccx-heatkey" aria-hidden="true"><span>Share of the ward</span><span>0%</span><span class="ramp"></span><span>100%</span></span>
                        <span class="ccx-pagecount" x-show="tv && tvPage === 'acuity' && subPages > 1" x-text="`Page ${subPage + 1} of ${subPages}`"></span>
                    </span>
                </header>
                <div class="ccx-hbars">
                    <template x-for="row in s.acuity" :key="row.key">
                        <div class="ccx-hbar">
                            <span class="label" x-text="tv ? row.short : row.label"></span>
                            <span class="value"><span x-text="fmt(row.count)"></span><small x-text="pct(row.pct)"></small></span>
                            <div class="track" aria-hidden="true"><div class="fill" :style="`width: ${acuityMax ? row.count / acuityMax * 100 : 0}%`"></div></div>
                        </div>
                    </template>
                </div>
                <div class="ccx-matrixwrap" x-ref="matrix">
                    <table class="ccx-table ccx-matrix">
                        <thead>
                            <tr>
                                <th scope="col">Ward</th>
                                <th scope="col">Inpatients</th>
                                <template x-for="row in s.acuity" :key="'mh' + row.key">
                                    <th scope="col" x-text="row.short"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="w in s.wards" :key="'m' + w.id">
                                <tr data-row>
                                    <td><span class="name" x-text="w.name"></span><span class="ccx-tag" x-show="w.critical_care">CC</span></td>
                                    <td x-text="fmt(w.inpatients)"></td>
                                    <template x-for="row in s.acuity" :key="'mc' + w.id + row.key">
                                        <td class="heat" :style="heat(w, row.key)"
                                            :title="`${w.name}: ${w[row.key]} of ${w.inpatients} inpatients ${row.short.toLowerCase()}`">
                                            <span :class="{ 'ccx-zero': !w[row.key] }" x-text="dash(w[row.key])"></span>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <p class="ccx-foot">
            Occupancy is occupied beds over active beds, against a target of <span x-text="s.targets.occupancy"></span>% or under.
            Admissions and discharges come from the admission log; the midnight census is worked back from today's inpatients.
            Clinical figures are the ones the ward dashboards raise; an EWS counts while its vital signs are under <span x-text="s.targets.obs_hours"></span> h old.
            Alerts count as waiting after <span x-text="s.targets.alert_wait_minutes"></span> min, and response times only include alerts a person answered.
            Nurses are those rostered for each ward's current shift in the AI Nurse Schedule. No patient is named on this screen.
        </p>
        </div>
    </div>

    <script type="application/json" id="ccv2-snapshot">@json($snapshot)</script>

    @verbatim
    <script>
        document.addEventListener('alpine:init', () => {
            const ICONS = {
                critical: '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="currentColor"/><path d="M10 5.3v6M10 14.4v.3" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg>',
                serious: '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.2 18.6 17.6H1.4Z" fill="currentColor" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 7.6v4.6M10 15v.3" stroke="#1a1a19" stroke-width="2" stroke-linecap="round"/></svg>',
                warning: '<svg viewBox="0 0 20 20" aria-hidden="true"><rect x="4" y="4" width="12" height="12" rx="2" transform="rotate(45 10 10)" fill="currentColor"/><path d="M10 6.4v4.6M10 13.6v.3" stroke="#1a1a19" stroke-width="2" stroke-linecap="round"/></svg>',
                good: '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="9" fill="currentColor"/><path d="m6.2 10.3 2.6 2.6 5-5.4" fill="none" stroke="#fff" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            };
            const DASH = '—';
            const missing = (n) => n === null || n === undefined;

            // TV mode's pages, in turn beside the attention list
            const TV_PAGES = [
                { key: 'wards', label: 'Wards' },
                { key: 'trends', label: 'Trends' },
                { key: 'acuity', label: 'Acuity & risk' },
            ];
            // How long each page (or each screenful of a page's rows) stays up, and each page of the attention list
            const STEP_SECONDS = 15;
            const FEED_SECONDS = 10;

            // A clean axis for counts: about four whole-number steps, the top at or above the highest value
            const scale = (highest) => {
                const rough = Math.max(highest, 1) / 4;
                const exp = Math.pow(10, Math.floor(Math.log10(rough)));
                const step = [1, 2, 5, 10].map((m) => m * exp).find((s) => s >= rough && Number.isInteger(s)) ?? 10 * exp;
                const max = Math.max(step, Math.ceil(highest / step) * step);
                const ticks = [];
                for (let t = 0; t <= max; t += step) ticks.push(t);
                return { max, ticks };
            };

            window.Alpine.data('commandCenterV2', () => ({
                s: JSON.parse(document.getElementById('ccv2-snapshot').textContent),
                url: null,
                connection: 'live',
                loading: false,
                lastOk: Date.now(),
                now: new Date(),
                cycle: 0,
                timer: null,
                theme: 'dark',
                tv: false,
                changed: {},
                hover: { census: null, flow: null },
                tables: { census: false, flow: false },
                feedMore: false,
                // TV mode's pages: the one up, and which screenful of its rows. They turn until someone presses Pause
                tvPages: TV_PAGES,
                paused: false,
                stepSeconds: STEP_SECONDS,
                tvPage: 'wards',
                subPage: 0,
                subPages: 1,
                feedPage: 0,
                feedPages: 1,
                stepLeft: STEP_SECONDS,
                stepKey: 0,
                feedTick: 0,

                init() {
                    this.url = this.$el.dataset.url;
                    try { this.theme = localStorage.getItem('ccv2Theme') === 'light' ? 'light' : 'dark'; } catch (e) {}
                    try { this.tv = localStorage.getItem('ccv2Tv') === 'on'; } catch (e) {}
                    if (this.tv) this.$nextTick(() => this.announceTv(true));

                    setInterval(() => {
                        this.now = new Date();
                        this.rotate();
                    }, 1000);
                    this.schedule();
                    this.$nextTick(() => this.checkFeed());
                    if (window.ResizeObserver) {
                        const resized = new ResizeObserver(() => this.refit());
                        [this.$refs.feed, this.$refs.wards, this.$refs.matrix].forEach((box) => resized.observe(box));
                    }

                    // Straight back up to date when the screen is looked at again
                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible' && Date.now() - this.lastOk >= this.s.refresh_seconds * 1000) this.load();
                    });
                    // Leaving the browser's full screen (Esc) leaves TV mode too
                    document.addEventListener('fullscreenchange', () => {
                        if (!document.fullscreenElement && this.tv) this.setTv(false);
                    });
                },

                schedule() {
                    clearTimeout(this.timer);
                    this.cycle++;
                    this.timer = setTimeout(() => this.load(), this.s.refresh_seconds * 1000);
                },

                async load() {
                    if (this.loading || this.connection === 'signed-out') return;
                    // Nobody is looking: wait for the next round rather than ask the server
                    if (document.visibilityState === 'hidden') return this.schedule();
                    this.loading = true;
                    try {
                        const response = await fetch(this.url, {
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });
                        if ([401, 403, 419].includes(response.status)) {
                            this.connection = 'signed-out';
                            return;
                        }
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        const next = await response.json();
                        this.markChanges(this.s, next);
                        this.s = next;
                        this.connection = 'live';
                        this.lastOk = Date.now();
                        this.$nextTick(() => this.refit());
                    } catch (e) {
                        this.connection = 'retrying';
                    } finally {
                        this.loading = false;
                        if (this.connection !== 'signed-out') this.schedule();
                    }
                },

                // Briefly light up what the latest figures changed
                markChanges(before, after) {
                    const sign = (value) => JSON.stringify(value);
                    const flash = (key) => {
                        this.changed[key] = false;
                        this.$nextTick(() => {
                            this.changed[key] = true;
                            setTimeout(() => { this.changed[key] = false; }, 2500);
                        });
                    };
                    const cards = {
                        capacity: (s) => s.capacity,
                        flow: (s) => s.flow,
                        quality: (s) => ({ ...s.quality, alerts: { ...s.quality.alerts, oldest: null, median_week: null } }),
                        workforce: (s) => s.workforce,
                    };
                    for (const [key, pick] of Object.entries(cards)) {
                        if (sign(pick(before)) !== sign(pick(after))) flash(key);
                    }
                    const ward = (w) => sign({ ...w, alerts_oldest: null });
                    const was = Object.fromEntries(before.wards.map((w) => [w.id, ward(w)]));
                    after.wards.forEach((w) => { if (w.id in was && was[w.id] !== ward(w)) flash('ward.' + w.id); });
                    const listed = new Set(before.attention.map((item) => item.key));
                    after.attention.forEach((item) => { if (!listed.has(item.key)) flash('item.' + item.key); });
                },

                toggleTheme() {
                    this.theme = this.theme === 'dark' ? 'light' : 'dark';
                    try { localStorage.setItem('ccv2Theme', this.theme); } catch (e) {}
                },

                setTv(on) {
                    this.tv = on;
                    try { localStorage.setItem('ccv2Tv', on ? 'on' : 'off'); } catch (e) {}
                    this.announceTv(on);
                    if (on && !document.fullscreenElement && document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen().catch(() => {});
                    } else if (!on && document.fullscreenElement && document.exitFullscreen) {
                        document.exitFullscreen().catch(() => {});
                    }
                },

                // The app layout hides its menu and footer on this event
                announceTv(on) {
                    window.dispatchEvent(new CustomEvent('toggle-custom-fullscreen', { detail: { enabled: on } }));
                    this.tvPage = TV_PAGES[0].key;
                    this.subPage = this.feedPage = this.feedTick = 0;
                    this.paused = false;
                    this.restartStep();
                    // Once the layout has settled at its new size (the menu takes 200 ms to go)
                    setTimeout(() => {
                        [this.$refs.wards, this.$refs.matrix, this.$refs.feed].forEach((box) => { box.scrollTop = 0; });
                        this.refit();
                    }, 300);
                },

                // Whether the attention list runs on past what its card shows; TV mode pages through it instead
                checkFeed() {
                    const feed = this.$refs.feed;
                    this.feedMore = !this.tv && !!feed && feed.scrollHeight - feed.scrollTop - feed.clientHeight > 4;
                },

                // After new figures or a new size: the pages as they now fall
                refit() {
                    this.checkFeed();
                    if (this.tv) {
                        this.goSubPage(this.subPage);
                        this.goFeedPage(this.feedPage);
                    }
                },

                // TV mode, every second. The wards, trends and acuity take turns, a page whose rows do not all
                // fit showing them a screenful at a time; the attention list pages on its own. Pause holds it all
                rotate() {
                    if (!this.tv || this.paused) return;
                    if (--this.stepLeft <= 0) this.nextStep();
                    if (++this.feedTick >= FEED_SECONDS) {
                        this.feedTick = 0;
                        this.goFeedPage(this.feedPage + 1);
                    }
                },
                nextStep() {
                    if (this.subPage + 1 < this.subPages) {
                        this.goSubPage(this.subPage + 1);
                    } else {
                        const at = TV_PAGES.findIndex((page) => page.key === this.tvPage);
                        this.showTv(TV_PAGES[(at + 1) % TV_PAGES.length].key);
                        return;
                    }
                    this.restartStep();
                },
                showTv(page) {
                    this.tvPage = page;
                    this.subPage = 0;
                    this.$nextTick(() => this.goSubPage(0));
                    this.restartStep();
                },
                restartStep() {
                    this.stepLeft = STEP_SECONDS;
                    this.stepKey++;
                },

                // One screenful of the rows on the page up: the ward table's, or the acuity heat table's
                goSubPage(page) {
                    const box = { wards: this.$refs.wards, acuity: this.$refs.matrix }[this.tvPage];
                    if (!box || box.offsetParent === null) {
                        this.subPage = 0;
                        this.subPages = 1;
                        return;
                    }
                    const rows = [...box.querySelectorAll('tbody tr[data-row]')];
                    const foot = box.querySelector('tfoot');
                    const pages = this.pages(box, rows, foot && foot.offsetParent !== null ? foot.offsetHeight : 0);
                    this.subPages = pages.length;
                    this.subPage = Math.min(page, pages.length - 1);
                    this.turnTo(box, rows, pages[this.subPage]);
                },
                goFeedPage(page) {
                    const box = this.$refs.feed;
                    if (box.offsetParent === null) return;
                    const rows = [...box.querySelectorAll('.ccx-item')];
                    const pages = this.pages(box, rows);
                    this.feedPages = pages.length;
                    this.feedPage = page % pages.length;
                    this.turnTo(box, rows, pages[this.feedPage]);
                },

                // The pages a box's rows fall into: each takes the rows that fit whole between where the first
                // row sits (under any sticky header) and the sticky footer (bottom), and its scroll offset puts
                // its first row where the first row of all sits
                pages(box, rows, bottom = 0) {
                    if (!rows.length) return [{ start: 0, rows: [] }];
                    const origin = box.getBoundingClientRect().top - box.scrollTop;
                    const first = rows[0].getBoundingClientRect().top - origin;
                    const view = box.clientHeight - first - bottom;
                    const pages = [];
                    let pageTop = null;
                    for (const row of rows) {
                        const rect = row.getBoundingClientRect();
                        const at = rect.top - origin;
                        if (pageTop === null || at + rect.height > pageTop + view + 1) {
                            pageTop = at;
                            pages.push({ start: Math.round(at - first), rows: [] });
                        }
                        pages[pages.length - 1].rows.push(row);
                    }
                    return pages;
                },
                // Show one page: its rows fade in where the last page was, and the rest are hidden, so
                // nothing half-cut peeks in at the bottom
                turnTo(box, rows, page) {
                    const shown = new Set(page.rows);
                    rows.forEach((row) => row.classList.toggle('is-off', !shown.has(row)));
                    if (box.scrollTop === page.start) return;
                    box.scrollTop = page.start;
                    page.rows.forEach((row) => row.animate?.([{ opacity: 0 }, { opacity: 1 }], { duration: 450, easing: 'ease-out' }));
                },

                get connectionLabel() {
                    return { live: 'Live', retrying: 'Reconnecting…', 'signed-out': 'Signed out' }[this.connection];
                },
                get clockTime() {
                    return String(this.now.getHours()).padStart(2, '0') + ':' + String(this.now.getMinutes()).padStart(2, '0');
                },
                get clockSeconds() {
                    return ':' + String(this.now.getSeconds()).padStart(2, '0');
                },
                get clockDate() {
                    return this.now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                },

                // Average stay: shorter than the 30 days before is the better way
                get alosChange() {
                    const { alos, alos_previous: before } = this.s.flow;
                    if (missing(alos) || missing(before)) return '';
                    const d = Math.round((alos - before) * 10) / 10;
                    return d === 0 ? 'same as before' : `${d > 0 ? '▲' : '▼'} ${this.dec(Math.abs(d))} d vs previous`;
                },
                get alosTone() {
                    const { alos, alos_previous: before } = this.s.flow;
                    if (missing(alos) || missing(before) || Math.round((alos - before) * 10) === 0) return '';
                    return alos < before ? 'is-down-good' : 'is-up-bad';
                },

                get census() {
                    const values = this.s.trend.census;
                    const n = values.length;
                    const { max, ticks } = scale(Math.max(this.s.capacity.beds || 0, ...values));
                    const points = values.map((v, i) => [this.band(i, n), 100 - v / max * 100]);
                    const line = points.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(2) + ' ' + p[1].toFixed(2)).join(' ');
                    const area = n ? `${line} L${points[n - 1][0].toFixed(2)} 100 L${points[0][0].toFixed(2)} 100 Z` : '';
                    return { max, ticks, line, area };
                },
                get flowScale() {
                    return scale(Math.max(0, ...this.s.trend.admissions, ...this.s.trend.discharges));
                },
                get acuityMax() {
                    return Math.max(0, ...this.s.acuity.map((row) => row.count));
                },

                // Centre of band i of n, as a percentage across the plot
                band(i, n) {
                    return (i + 0.5) / n * 100;
                },
                tipAt(i, n) {
                    if (i === null) return '';
                    const x = this.band(i, n);
                    return `left: ${x}%; transform: translateX(${x < 30 ? '-8%' : x > 70 ? '-92%' : '-50%'})`;
                },

                icon(severity) {
                    return ICONS[severity] || ICONS.good;
                },
                sevLabel(severity) {
                    return { critical: 'Critical', serious: 'Serious', warning: 'Watch', good: 'OK' }[severity] || '';
                },
                capacityLabel(status) {
                    return { good: 'Within target', warning: 'Above target', critical: 'Near full' }[status] || '';
                },
                qualityLabel(status) {
                    return { good: 'No exceptions', warning: 'Watch', serious: 'Serious', critical: 'Critical' }[status] || '';
                },
                workforceLabel(status) {
                    return { good: 'Staffed to minimum', serious: 'Below minimum' }[status] || '';
                },

                fmt(n) {
                    return missing(n) ? DASH : Number(n).toLocaleString('en-US');
                },
                dec(n) {
                    return missing(n) ? DASH : Number(n).toLocaleString('en-US', { maximumFractionDigits: 1 });
                },
                pct(n) {
                    return missing(n) ? DASH : this.dec(n) + '%';
                },
                // A count in the ward table: a dash for none, so the ones with some stand out
                dash(n) {
                    return n ? this.fmt(n) : '–';
                },
                // A heat-table cell: one hue, deeper the bigger the share of the ward's inpatients
                heat(ward, key) {
                    const count = ward[key] || 0;
                    if (!count || !ward.inpatients) return '';
                    return `background: rgb(var(--heat) / ${(0.16 + 0.64 * Math.min(1, count / ward.inpatients)).toFixed(2)})`;
                },
                mins(n) {
                    return missing(n) ? DASH : (n < 10 ? this.dec(n) : Math.round(n).toLocaleString('en-US')) + ' min';
                },
                days(n) {
                    return missing(n) ? DASH : this.dec(n) + (n === 1 ? ' day' : ' days');
                },
                // 12 -> "12 pm", 11 -> "11 am"
                hour(h) {
                    return `${h % 12 || 12} ${h < 12 ? 'am' : 'pm'}`;
                },
                versus(now, before, when) {
                    if (missing(now) || missing(before)) return '';
                    const d = now - before;
                    return d === 0 ? `Same as ${when}` : `${d > 0 ? '▲' : '▼'} ${Math.abs(d)} vs ${when}`;
                },
            }));
        });
    </script>
    @endverbatim
</x-app-layout>
