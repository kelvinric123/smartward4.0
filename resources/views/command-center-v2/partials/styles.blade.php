{{-- The Command Center boards' own look (dark by default, a light theme, TV mode), sized in em. Shared by
     Command Center V2 and Command Center V2 (ED) --}}
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
            /* The display settings' screen preset (--ccx-base) and size (--ccx-size) */
            font-size: calc(var(--ccx-base, 14px) * var(--ccx-size, 1)); line-height: 1.45;
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
            font-size: calc(var(--ccx-base, clamp(10px, min(.78vw, 1.38vh), 28px)) * var(--ccx-size, 1));
            height: 100vh; display: flex; flex-direction: column; overflow: hidden;
            padding: 1em 1.25em;
        }
        .ccx.is-tv .ccx-top { margin-bottom: .8em; flex-shrink: 0; }
        .ccx.is-tv .ccx-banner { flex-shrink: 0; }
        .ccx.is-tv .ccx-body { flex: 1 1 auto; min-height: 0; display: grid; gap: .8em; grid-template-columns: minmax(0, 1.125fr) minmax(0, 1.125fr) minmax(0, 1fr); grid-template-rows: auto auto minmax(0, 1fr); }
        .ccx.is-tv .ccx-section { margin: 0; }
        /* Four across however large the settings make them, so they stay one row */
        .ccx.is-tv .ccx-kpis { grid-column: 1 / -1; grid-row: 1; gap: .8em; grid-template-columns: repeat(4, minmax(0, 1fr)); }
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

        /* ------ From the display settings (partials/settings): each section's own size, the ward table's
           columns, and TV mode with a set number of rows a page or without the headline cards or the list ------ */
        .ccx-kpis { font-size: calc(var(--ccx-kpis, 1) * 1em); }
        .ccx-wards { font-size: calc(var(--ccx-wards, 1) * 1em); }
        .ccx-attention { font-size: calc(var(--ccx-attention, 1) * 1em); }
        .ccx-census, .ccx-flowchart { font-size: calc(var(--ccx-trends, 1) * 1em); }
        .ccx-acuity { font-size: calc(var(--ccx-acuity, 1) * 1em); }
        /* Ward table columns left out, by position (WARD_COLUMNS in the script): 2 Occupancy, 3 Free, 4 Prebooked,
           5 To discharge, 6 In / out today, 7 EWS 5+, 8 Care overdue, 9 Vitals 24 h, 10 Alerts, 11 Nurses */
        .ccx-table.hide-2 tr > :nth-child(2), .ccx-table.hide-3 tr > :nth-child(3), .ccx-table.hide-4 tr > :nth-child(4),
        .ccx-table.hide-5 tr > :nth-child(5), .ccx-table.hide-6 tr > :nth-child(6), .ccx-table.hide-7 tr > :nth-child(7),
        .ccx-table.hide-8 tr > :nth-child(8), .ccx-table.hide-9 tr > :nth-child(9), .ccx-table.hide-10 tr > :nth-child(10),
        .ccx-table.hide-11 tr > :nth-child(11) { display: none; }
        .ccx.no-attention .ccx-mid { grid-template-columns: minmax(0, 1fr); }
        /* TV mode: a ward table wider than its card is scaled down until it fits (--ccx-fit), and with a set
           number of rows a page, the rows share the card's height between them (--ccx-row) */
        .ccx.is-tv .ccx-wards .ccx-table { font-size: calc(var(--ccx-fit, 1) * 1em); }
        .ccx.is-tv tr[data-row] { height: var(--ccx-row, auto); }
        .ccx.is-tv.no-kpis .ccx-body { grid-template-rows: auto minmax(0, 1fr); }
        .ccx.is-tv.no-kpis .ccx-pager { grid-row: 1; }
        .ccx.is-tv.no-kpis .ccx-attention { grid-row: 1 / span 2; }
        .ccx.is-tv.no-kpis .ccx-page { grid-row: 2; }
        .ccx.is-tv.no-attention .ccx-body { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ccx.is-tv.no-attention .ccx-pager, .ccx.is-tv.no-attention .ccx-wards, .ccx.is-tv.no-attention .ccx-acuity { grid-column: 1 / -1; }

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
