{{-- The Settings panel: this screen's size (a preset for its resolution, and a size on top), the theme, and each
     section's size and whether it shows, with TV mode's rows a page, seconds a page and ward table columns.
     It edits the commandCenterV2 component's cfg, which is kept in this browser's localStorage, so every screen
     keeps its own. A modal <dialog>, so it sits over the board, and over TV mode's full screen, while the board
     shows each change as it is made --}}
@include('command-center-v2.partials.settings-styles')

<dialog class="ccx-settings" x-ref="settings" aria-labelledby="ccx-settings-title"
    @close="settingsClosed()" @click="$event.target === $el && closeSettings()">
    <div class="ccx-set-panel">
        <header class="ccx-set-head">
            <div>
                <h2 id="ccx-settings-title">Display settings</h2>
                <p>Saved on this screen only. The board shows each change as you make it.</p>
            </div>
            <button type="button" class="ccx-btn is-square" @click="closeSettings()" aria-label="Close the settings">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </header>

        <div class="ccx-set-body">
            {{-- The screen: its preset, the size on top, and the theme --}}
            <section class="ccx-set-group" aria-labelledby="ccx-set-screen">
                <div class="ccx-set-title"><h3 id="ccx-set-screen">Screen</h3></div>
                <p class="ccx-set-about" x-text="screenNote"></p>
                <div class="ccx-presets" role="radiogroup" aria-labelledby="ccx-set-screen">
                    <template x-for="preset in screens" :key="preset.key">
                        <button type="button" role="radio" class="ccx-preset" :class="{ 'is-on': cfg.screen === preset.key, 'is-auto': !preset.height }"
                            :aria-checked="(cfg.screen === preset.key).toString()" @click="cfg.screen = preset.key">
                            <span class="name" x-text="preset.label"></span>
                            <span class="res" x-text="preset.sub"></span>
                            <span class="here" x-show="preset.key === detectedScreen.key">This screen</span>
                        </button>
                    </template>
                </div>
                <div class="ccx-set-row">
                    <label for="ccx-set-size">Size</label>
                    <div class="ccx-slider">
                        <button type="button" class="ccx-step" @click="nudgeSize(-5)" :disabled="cfg.size <= 50" aria-label="Smaller">&minus;</button>
                        <input id="ccx-set-size" type="range" class="ccx-range" min="50" max="250" step="5" x-model.number="cfg.size"
                            :style="`--fill: ${(cfg.size - 50) / 2}%`" aria-describedby="ccx-set-size-about">
                        <button type="button" class="ccx-step" @click="nudgeSize(5)" :disabled="cfg.size >= 250" aria-label="Bigger">+</button>
                        <output for="ccx-set-size" x-text="cfg.size + '%'"></output>
                    </div>
                </div>
                <p class="ccx-set-hint" id="ccx-set-size-about">Everything on the board, on top of the screen preset</p>
                <div class="ccx-set-row">
                    <span class="lbl" id="ccx-set-theme">Theme</span>
                    <div class="ccx-seg" role="radiogroup" aria-labelledby="ccx-set-theme">
                        <button type="button" role="radio" :aria-checked="(theme === 'dark').toString()" @click="theme === 'dark' || toggleTheme()">Dark</button>
                        <button type="button" role="radio" :aria-checked="(theme === 'light').toString()" @click="theme === 'light' || toggleTheme()">Light</button>
                    </div>
                </div>
            </section>

            <p class="ccx-set-lead"><span class="ccx-tv-tag">TV</span> settings are for TV mode, where Wards, Trends and Acuity &amp; risk take turns beside Needs attention.</p>

            {{-- Each section: whether it shows and its size, and for TV mode its rows and seconds a page --}}
            <template x-for="section in sections" :key="section.key">
                <section class="ccx-set-group" :aria-labelledby="'ccx-set-' + section.key" @focusin="preview(section.key)" @pointerdown="preview(section.key)">
                    <div class="ccx-set-title">
                        <div>
                            <h3 :id="'ccx-set-' + section.key" x-text="section.label"></h3>
                            <p class="ccx-set-about" x-text="section.about"></p>
                        </div>
                        <button type="button" role="switch" class="ccx-switch" :aria-checked="cfg[section.key].show.toString()"
                            :disabled="cfg[section.key].show && !canHide(section.key)" @click="toggleSection(section.key)"
                            :title="cfg[section.key].show && !canHide(section.key) ? 'TV mode needs one of Wards, Trends and Acuity & risk' : ''">
                            <span class="track" aria-hidden="true"></span><span>Show</span>
                        </button>
                    </div>
                    <div x-show="cfg[section.key].show">
                        <div class="ccx-set-row">
                            <span class="lbl">Size</span>
                            <div class="ccx-stepper">
                                <button type="button" class="ccx-step" @click="nudgeSection(section.key, -10)" :disabled="cfg[section.key].size <= 50" :aria-label="section.label + ' smaller'">&minus;</button>
                                <output x-text="cfg[section.key].size + '%'"></output>
                                <button type="button" class="ccx-step" @click="nudgeSection(section.key, 10)" :disabled="cfg[section.key].size >= 200" :aria-label="section.label + ' bigger'">+</button>
                            </div>
                        </div>
                        <p class="ccx-set-hint is-warn" x-show="section.key === 'wards' && wardsFitNote" x-text="wardsFitNote"></p>
                        <template x-if="section.perPage">
                            <div>
                                <div class="ccx-set-row">
                                    <label :for="'ccx-set-rows-' + section.key"><span x-text="section.perPage"></span><span class="ccx-tv-tag">TV</span></label>
                                    <select :id="'ccx-set-rows-' + section.key" @change="cfg[section.key].perPage = Number($event.target.value)">
                                        <option value="0" :selected="cfg[section.key].perPage === 0">As many as fit</option>
                                        <template x-for="rows in section.pageSizes" :key="rows">
                                            <option :value="rows" :selected="cfg[section.key].perPage === rows" x-text="rows"></option>
                                        </template>
                                    </select>
                                </div>
                                <p class="ccx-set-hint" :class="{ 'is-warn': tv && cfg[section.key].perPage > fits[section.key] && fits[section.key] > 0 }" x-text="fitNote(section.key)"></p>
                            </div>
                        </template>
                        <template x-if="section.seconds">
                            <div class="ccx-set-row">
                                <label :for="'ccx-set-secs-' + section.key">Seconds a page<span class="ccx-tv-tag">TV</span></label>
                                <select :id="'ccx-set-secs-' + section.key" @change="cfg[section.key].seconds = Number($event.target.value)">
                                    <template x-for="seconds in secondsOptions" :key="seconds">
                                        <option :value="seconds" :selected="cfg[section.key].seconds === seconds" x-text="seconds + ' s'"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                        <template x-if="section.key === 'wards'">
                            <div class="ccx-set-row is-stacked">
                                <span class="lbl" id="ccx-set-columns">Columns</span>
                                <div class="ccx-picks" role="group" aria-labelledby="ccx-set-columns">
                                    <template x-for="column in wardColumns" :key="column.key">
                                        <button type="button" class="ccx-pick" :aria-pressed="(!cfg.wards.hidden.includes(column.key)).toString()"
                                            @click="toggleColumn(column.key)" x-text="column.label"></button>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </section>
            </template>
        </div>

        <footer class="ccx-set-foot">
            <button type="button" class="ccx-btn" @click="resetSettings()">Reset to defaults</button>
            <button type="button" class="ccx-btn is-primary" @click="closeSettings()">Done</button>
        </footer>
    </div>
</dialog>
