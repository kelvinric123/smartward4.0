{{-- The Settings panel's look, shared by Command Center V2's and Command Center V2 (ED)'s --}}
@verbatim
<style>
    /* Down the right of the screen. Its type follows the window rather than the board, so it stays usable at any size */
    .ccx-settings {
        position: fixed; inset: 0 0 0 auto; width: min(30em, 100vw); height: 100%; max-width: none; max-height: none;
        margin: 0; padding: 0; border: 0; border-left: 1px solid var(--line); overflow: hidden;
        background: var(--surface); color: var(--ink-1); box-shadow: var(--shadow);
        font-size: clamp(13px, min(.85vw, 1.5vh), 34px); line-height: 1.45;
    }
    .ccx-settings[open] { display: flex; }
    .ccx-settings::backdrop { background: rgba(0, 0, 0, .25); }
    .ccx-set-panel { display: flex; flex-direction: column; width: 100%; min-height: 0; }
    .ccx-set-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1em; padding: 1.1em 1.25em 1em; border-bottom: 1px solid var(--line); }
    .ccx .ccx-set-head h2 { font-size: 1.3em; font-weight: 700; letter-spacing: -.01em; text-transform: none; color: var(--ink-1); }
    .ccx-set-head p { margin-top: .2em; font-size: .857em; color: var(--ink-3); }
    .ccx-set-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 1.25em 1em; }
    .ccx-set-group { padding: 1.1em 0; border-bottom: 1px solid var(--line); }
    .ccx-set-group:last-child { border-bottom: 0; }
    .ccx-set-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 1em; }
    .ccx .ccx-set-title h3 { margin: 0; font-size: 1em; font-weight: 650; }
    .ccx-set-about { margin-top: .1em; font-size: .786em; color: var(--ink-3); }
    .ccx-set-lead { margin: 1.1em 0 0; font-size: .857em; color: var(--ink-2); }
    .ccx-set-row { display: flex; align-items: center; justify-content: space-between; gap: .5em 1em; margin-top: .8em; }
    .ccx-set-row > label, .ccx-set-row > .lbl { font-size: .929em; color: var(--ink-2); }
    .ccx-set-row.is-stacked { flex-direction: column; align-items: stretch; }
    .ccx-set-hint { margin-top: .35em; font-size: .786em; color: var(--ink-3); text-align: right; }
    .ccx-set-hint.is-warn { color: var(--delta-bad); }
    .ccx-tv-tag { display: inline-block; margin-left: .35em; padding: 0 .35em; border-radius: 4px; border: 1px solid var(--axis); font-size: .7em; font-weight: 700; letter-spacing: .05em; color: var(--ink-2); vertical-align: .15em; }
    .ccx-set-lead .ccx-tv-tag { margin: 0 .15em 0 0; }

    /* Screen presets: Auto across the top, the resolutions two by two */
    .ccx-presets { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5em; margin-top: .8em; }
    .ccx-preset { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: .1em; padding: .6em .8em; border-radius: 10px;
        background: var(--surface-2); border: 1px solid var(--line); color: var(--ink-1); font: inherit; text-align: left; cursor: pointer; }
    .ccx-preset.is-auto { grid-column: 1 / -1; }
    .ccx-preset:hover { border-color: var(--axis); }
    .ccx-preset.is-on { border-color: var(--accent); box-shadow: inset 0 0 0 1px var(--accent); }
    .ccx-preset .name { font-size: 1.07em; font-weight: 700; }
    .ccx-preset .res { font-size: .786em; color: var(--ink-3); font-variant-numeric: tabular-nums; }
    .ccx-preset .here { position: absolute; top: .6em; right: .7em; font-size: .643em; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--link); }

    /* Size: a slider with a step either side, and steppers for each section */
    .ccx-slider { display: flex; align-items: center; gap: .45em; flex: 1 1 auto; max-width: 19em; }
    .ccx-slider output, .ccx-stepper output { min-width: 3.3em; font-weight: 650; font-variant-numeric: tabular-nums; }
    .ccx-slider output { text-align: right; }
    .ccx-stepper { display: inline-flex; align-items: center; gap: .35em; }
    .ccx-stepper output { text-align: center; }
    .ccx-step { flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 2.1em; height: 2.1em; padding: 0;
        border-radius: 8px; border: 1px solid var(--line); background: var(--surface-2); color: var(--ink-1); font: inherit; font-weight: 700; line-height: 1; cursor: pointer; }
    .ccx-step:hover:not(:disabled) { border-color: var(--axis); }
    .ccx-step:disabled { opacity: .4; cursor: default; }
    .ccx-range { -webkit-appearance: none; appearance: none; flex: 1 1 auto; min-width: 0; height: 1.6em; margin: 0; background: transparent; cursor: pointer; }
    .ccx-range::-webkit-slider-runnable-track { height: .4em; border-radius: 999px; background: linear-gradient(90deg, var(--accent) var(--fill), var(--axis) var(--fill)); }
    .ccx-range::-webkit-slider-thumb { -webkit-appearance: none; width: 1.2em; height: 1.2em; margin-top: -.4em; border-radius: 50%; background: var(--accent); border: .2em solid var(--surface); box-shadow: 0 0 0 1px var(--accent); }
    .ccx-range::-moz-range-track { height: .4em; border-radius: 999px; background: var(--axis); }
    .ccx-range::-moz-range-progress { height: .4em; border-radius: 999px; background: var(--accent); }
    .ccx-range::-moz-range-thumb { width: .8em; height: .8em; border-radius: 50%; background: var(--accent); border: .2em solid var(--surface); box-shadow: 0 0 0 1px var(--accent); }

    /* Selects (over the forms plugin's rem-sized look), the show switch, the theme and the ward table's columns */
    .ccx-settings select { flex-shrink: 0; min-width: 9.5em; height: 2.3em; padding: 0 2.2em 0 .75em; border-radius: 8px; border: 1px solid var(--line);
        background-color: var(--surface-2); background-position: right .5em center; background-size: 1.25em; color: var(--ink-1);
        font: inherit; font-size: .929em; cursor: pointer; }
    .ccx-settings select:focus { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
    .ccx-switch { flex-shrink: 0; display: inline-flex; align-items: center; gap: .5em; padding: .2em 0; border: 0; background: none;
        color: var(--ink-2); font: inherit; font-size: .857em; font-weight: 600; cursor: pointer; }
    .ccx-switch .track { position: relative; width: 2.5em; height: 1.45em; border-radius: 999px; background: var(--axis); transition: background-color .2s; }
    .ccx-switch .track::after { content: ""; position: absolute; top: .2em; left: .2em; width: 1.05em; height: 1.05em; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, .3); transition: transform .2s; }
    .ccx-switch[aria-checked="true"] .track { background: var(--accent); }
    .ccx-switch[aria-checked="true"] .track::after { transform: translateX(1.05em); }
    .ccx-switch:disabled { cursor: not-allowed; opacity: .55; }
    .ccx-seg { display: inline-flex; padding: .2em; border-radius: 10px; background: var(--surface-2); border: 1px solid var(--line); }
    .ccx-seg button { min-width: 5em; height: 2em; padding: 0 .9em; border: 0; border-radius: 7px; background: transparent; color: var(--ink-2); font: inherit; font-size: .857em; font-weight: 600; cursor: pointer; }
    .ccx-seg button[aria-checked="true"] { background: var(--surface); color: var(--ink-1); box-shadow: 0 0 0 1px var(--line), 0 1px 3px rgba(0, 0, 0, .2); }
    .ccx-picks { display: flex; flex-wrap: wrap; gap: .4em; margin-top: .5em; }
    .ccx-pick { display: inline-flex; align-items: center; gap: .45em; padding: .3em .75em .3em .55em; border-radius: 999px; border: 1px solid var(--line);
        background: transparent; color: var(--ink-3); font: inherit; font-size: .857em; font-weight: 600; cursor: pointer; }
    .ccx-pick::before { content: ""; width: .8em; height: .8em; border-radius: 3px; border: 1.5px solid currentColor; }
    .ccx-pick[aria-pressed="true"] { color: var(--ink-1); background: var(--surface-2); border-color: var(--axis); }
    .ccx-pick[aria-pressed="true"]::before { border-color: var(--accent); background: var(--accent) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath d='M3.5 8.5l3 3 6-7' fill='none' stroke='white' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center / 85% no-repeat; }

    .ccx-set-foot { display: flex; align-items: center; justify-content: space-between; gap: .75em; padding: .9em 1.25em; border-top: 1px solid var(--line); }
    .ccx-btn.is-primary { background: var(--accent); border-color: transparent; color: #fff; }
    .ccx-btn.is-primary:hover { background: var(--accent); filter: brightness(1.1); }
</style>
@endverbatim
