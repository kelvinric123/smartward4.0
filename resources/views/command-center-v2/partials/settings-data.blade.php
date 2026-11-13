{{-- The Settings panel's data mode, shared by Command Center V2's and Command Center V2 (ED)'s: the recorded figures
     (Live) or made-up ones for showing the board (Demo). Part of the display settings (cfg.data), so kept on this
     screen only, and only this switch changes it: nothing else on the system turns a board's demo on or off.
     $id prefixes its ids; $about says what it covers; the board's dataModeNote says what each mode shows --}}
<section class="ccx-set-group" aria-labelledby="{{ $id }}-set-data">
    <div class="ccx-set-title">
        <div>
            <h3 id="{{ $id }}-set-data">Data mode</h3>
            <p class="ccx-set-about">{{ $about }}</p>
        </div>
        <span class="ccx-chip" :class="{ 'is-demo': cfg.data.demo }" x-text="cfg.data.demo ? 'Showing demo' : 'Showing live'"></span>
    </div>
    <div class="ccx-set-row">
        <span class="lbl" id="{{ $id }}-set-data-mode">Mode</span>
        <div class="ccx-seg" role="radiogroup" aria-labelledby="{{ $id }}-set-data-mode">
            <button type="button" role="radio" :aria-checked="(!cfg.data.demo).toString()" @click="cfg.data.demo = false">Live</button>
            <button type="button" role="radio" :aria-checked="cfg.data.demo.toString()" @click="cfg.data.demo = true">Demo</button>
        </div>
    </div>
    <p class="ccx-set-hint" :class="{ 'is-warn': cfg.data.demo }" x-text="dataModeNote"></p>
</section>
