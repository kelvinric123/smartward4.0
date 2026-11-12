{{-- The Settings panel's time format, shared by Command Center V2's and Command Center V2 (ED)'s: how the board
     says a time of day, 24-hour or 12-hour with AM/PM, and whether with the seconds. Part of the display settings
     (cfg.clock), so kept on this screen only. $id prefixes its ids; $about says which times it covers --}}
<section class="ccx-set-group" aria-labelledby="{{ $id }}-set-clock">
    <div class="ccx-set-title">
        <div>
            <h3 id="{{ $id }}-set-clock">Time format</h3>
            <p class="ccx-set-about">{{ $about }}</p>
        </div>
    </div>
    <div class="ccx-set-row">
        <span class="lbl" id="{{ $id }}-set-hours">Hours</span>
        <div class="ccx-seg" role="radiogroup" aria-labelledby="{{ $id }}-set-hours">
            <button type="button" role="radio" :aria-checked="(!cfg.clock.hour12).toString()" @click="cfg.clock.hour12 = false">24-hour</button>
            <button type="button" role="radio" :aria-checked="cfg.clock.hour12.toString()" @click="cfg.clock.hour12 = true">12-hour (AM/PM)</button>
        </div>
    </div>
    <div class="ccx-set-row">
        <span class="lbl" id="{{ $id }}-set-seconds">Seconds</span>
        <div class="ccx-seg" role="radiogroup" aria-labelledby="{{ $id }}-set-seconds">
            <button type="button" role="radio" :aria-checked="cfg.clock.seconds.toString()" @click="cfg.clock.seconds = true">Show</button>
            <button type="button" role="radio" :aria-checked="(!cfg.clock.seconds).toString()" @click="cfg.clock.seconds = false">Hours and minutes</button>
        </div>
    </div>
    <p class="ccx-set-hint" x-text="'Now ' + timeOf(now, cfg.clock.seconds)"></p>
</section>
