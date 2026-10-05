{{-- The Settings panel of Command Center V2 (ED). First the time-in-ED target, the hospital's, saved on the server
     so every screen measures against the same one. Then this screen's display, as on Command Center V2: its size
     (a preset for its resolution, and a size on top), the theme, and each section's size and whether it shows,
     with TV mode's rows and seconds a page. The display is kept in this browser's localStorage, so every screen
     keeps its own. A modal <dialog>, so it sits over the board, and over TV mode's full screen --}}
@include('command-center-v2.partials.settings-styles')

<dialog class="ccx-settings" x-ref="settings" aria-labelledby="cced-settings-title"
    @close="settingsClosed()" @click="$event.target === $el && closeSettings()">
    <div class="ccx-set-panel">
        <header class="ccx-set-head">
            <div>
                <h2 id="cced-settings-title">Settings</h2>
                <p>The target applies to every screen. The display settings are saved on this screen only.</p>
            </div>
            <button type="button" class="ccx-btn is-square" @click="closeSettings()" aria-label="Close the settings">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </header>

        <div class="ccx-set-body">
            {{-- The time-in-ED target, for every screen --}}
            <section class="ccx-set-group" aria-labelledby="cced-set-target">
                <div class="ccx-set-title">
                    <div>
                        <h3 id="cced-set-target">Time-in-ED target</h3>
                        <p class="ccx-set-about">Patients in the ED longer than this since they arrived are flagged, and listed under Needs attention.</p>
                    </div>
                </div>
                <div class="ccx-set-row">
                    <label for="cced-set-target-minutes">Target</label>
                    <select id="cced-set-target-minutes" :disabled="targetSaving" @change="saveTarget($event.target.value)">
                        <template x-for="minutes in s.targets.choices" :key="minutes">
                            <option :value="minutes" :selected="s.targets.minutes === minutes" x-text="hm(minutes)"></option>
                        </template>
                    </select>
                </div>
                <p class="ccx-set-hint" :class="{ 'is-warn': targetError }"
                    x-text="targetError || (targetSaving ? 'Saving…' : 'Saved for every screen')"></p>
            </section>

            {{-- The screen: its preset, the size on top, and the theme --}}
            <section class="ccx-set-group" aria-labelledby="cced-set-screen">
                <div class="ccx-set-title"><h3 id="cced-set-screen">Screen</h3></div>
                <p class="ccx-set-about" x-text="screenNote"></p>
                <div class="ccx-presets" role="radiogroup" aria-labelledby="cced-set-screen">
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
                    <label for="cced-set-size">Size</label>
                    <div class="ccx-slider">
                        <button type="button" class="ccx-step" @click="nudgeSize(-5)" :disabled="cfg.size <= 50" aria-label="Smaller">&minus;</button>
                        <input id="cced-set-size" type="range" class="ccx-range" min="50" max="250" step="5" x-model.number="cfg.size"
                            :style="`--fill: ${(cfg.size - 50) / 2}%`">
                        <button type="button" class="ccx-step" @click="nudgeSize(5)" :disabled="cfg.size >= 250" aria-label="Bigger">+</button>
                        <output for="cced-set-size" x-text="cfg.size + '%'"></output>
                    </div>
                </div>
                <p class="ccx-set-hint">Everything on the board, on top of the screen preset</p>
                <div class="ccx-set-row">
                    <span class="lbl" id="cced-set-theme">Theme</span>
                    <div class="ccx-seg" role="radiogroup" aria-labelledby="cced-set-theme">
                        <button type="button" role="radio" :aria-checked="(theme === 'dark').toString()" @click="theme === 'dark' || toggleTheme()">Dark</button>
                        <button type="button" role="radio" :aria-checked="(theme === 'light').toString()" @click="theme === 'light' || toggleTheme()">Light</button>
                    </div>
                </div>
            </section>

            <p class="ccx-set-lead"><span class="ccx-tv-tag">TV</span> settings are for TV mode, where Zones, Trends and Time in ED take turns beside Needs attention.</p>

            {{-- Each section: whether it shows and its size, and for TV mode its rows and seconds a page --}}
            <template x-for="section in sections" :key="section.key">
                <section class="ccx-set-group" :aria-labelledby="'cced-set-' + section.key" @focusin="preview(section.key)" @pointerdown="preview(section.key)">
                    <div class="ccx-set-title">
                        <div>
                            <h3 :id="'cced-set-' + section.key" x-text="section.label"></h3>
                            <p class="ccx-set-about" x-text="section.about"></p>
                        </div>
                        <button type="button" role="switch" class="ccx-switch" :aria-checked="cfg[section.key].show.toString()"
                            :disabled="cfg[section.key].show && !canHide(section.key)" @click="toggleSection(section.key)"
                            :title="cfg[section.key].show && !canHide(section.key) ? 'TV mode needs one of Zones, Trends and Time in ED' : ''">
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
                        <p class="ccx-set-hint is-warn" x-show="section.key === 'zones' && zonesFitNote" x-text="zonesFitNote"></p>
                        <template x-if="section.perPage">
                            <div>
                                <div class="ccx-set-row">
                                    <label :for="'cced-set-rows-' + section.key"><span x-text="section.perPage"></span><span class="ccx-tv-tag">TV</span></label>
                                    <select :id="'cced-set-rows-' + section.key" @change="cfg[section.key].perPage = Number($event.target.value)">
                                        <option value="0" :selected="cfg[section.key].perPage === 0">As many as fit</option>
                                        <template x-for="rows in section.pageSizes" :key="rows">
                                            <option :value="rows" :selected="cfg[section.key].perPage === rows" x-text="rows"></option>
                                        </template>
                                    </select>
                                </div>
                                <p class="ccx-set-hint" x-show="section.key === 'zones'" x-text="fitNote(section.key)"></p>
                            </div>
                        </template>
                        <template x-if="section.seconds">
                            <div class="ccx-set-row">
                                <label :for="'cced-set-secs-' + section.key">Seconds a page<span class="ccx-tv-tag">TV</span></label>
                                <select :id="'cced-set-secs-' + section.key" @change="cfg[section.key].seconds = Number($event.target.value)">
                                    <template x-for="seconds in secondsOptions" :key="seconds">
                                        <option :value="seconds" :selected="cfg[section.key].seconds === seconds" x-text="seconds + ' s'"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                    </div>
                </section>
            </template>
        </div>

        <footer class="ccx-set-foot">
            <button type="button" class="ccx-btn" @click="resetSettings()">Reset display</button>
            <button type="button" class="ccx-btn is-primary" @click="closeSettings()">Done</button>
        </footer>
    </div>
</dialog>
