{{--
    Patient Details -> Discharge Summary tab.

    The summary pulls every record of the stay (vitals, I/O, doses, orders,
    infusions, the ECG store, the infusion engine), so it is not built with the
    rest of Patient Details: the tab fetches it the first time it is opened, and
    again on Refresh or when another admission is picked. The body comes from
    PatientDischargeSummaryController@panel (wards.discharge-summary.panel) and
    its buttons call back into this component (load / jump / printSummary).
--}}
@if (($patientTabs['discharge_summary'] ?? false) && $patient)
    <script>
        window.dischargeSummaryTab = window.dischargeSummaryTab || function (panelUrl) {
            return {
                html: '',
                status: 'idle', // idle | loading | ready | error
                error: '',
                admission: null,

                ensureLoaded() {
                    if (this.status === 'idle') {
                        this.load();
                    }
                },

                load(admission) {
                    if (admission !== undefined) {
                        this.admission = admission || null;
                    }

                    const url = new URL(panelUrl, window.location.href);
                    if (this.admission) {
                        url.searchParams.set('admission', this.admission);
                    }

                    this.status = 'loading';
                    this.error = '';

                    fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then((response) => {
                            // An expired session answers with the login page
                            // rather than an error status.
                            if (response.redirected || !response.ok) {
                                throw new Error(response.redirected
                                    ? 'Your session has expired. Reload the page and sign in again.'
                                    : 'The summary could not be built (error ' + response.status + ').');
                            }
                            return response.text();
                        })
                        .then((html) => {
                            this.html = html;
                            this.status = 'ready';
                        })
                        .catch((e) => {
                            this.error = e && e.message && !(e instanceof TypeError)
                                ? e.message
                                : 'The summary could not be loaded. Check the connection and try again.';
                            this.status = 'error';
                        });
                },

                // Scroll to a section of the summary, clear of the sticky contents bar.
                jump(key) {
                    const target = this.$root.querySelector('[data-section="' + key + '"]');
                    if (!target) {
                        return;
                    }
                    const bar = this.$root.querySelector('[data-summary-contents]');
                    const offset = (bar ? bar.offsetHeight : 0) + 12;
                    window.scrollTo({
                        top: target.getBoundingClientRect().top + window.scrollY - offset,
                        behavior: 'smooth',
                    });
                },

                printSummary(url, provisional) {
                    if (provisional && !window.confirm(
                        'This patient has NOT been discharged yet.\n\n' +
                        'The discharge summary may not be correct or complete: anything recorded ' +
                        'between now and the actual discharge will be missing.\n\n' +
                        'Open it for printing anyway as a provisional copy?')) {
                        return;
                    }
                    window.open(url, '_blank');
                },
            };
        };
    </script>

    <div x-show="activeTab === 'discharge_summary'" x-cloak
        x-data="dischargeSummaryTab(@js(route('ward.discharge-summary.panel', $patient)))"
        x-effect="if (activeTab === 'discharge_summary') ensureLoaded()">

        {{-- First load --}}
        <div x-show="status === 'loading' && !html" class="space-y-3 py-2" aria-live="polite">
            <p class="flex items-center gap-2 text-sm text-gray-500">
                <svg class="h-4 w-4 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Building the discharge summary from this admission's records&hellip;
            </p>
            <div class="h-24 animate-pulse rounded-lg bg-gray-100"></div>
            <div class="h-40 animate-pulse rounded-lg bg-gray-100"></div>
        </div>

        {{-- Failed load --}}
        <div x-show="status === 'error'" x-cloak
            class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
            <p class="text-sm text-red-700" x-text="error"></p>
            <button type="button" @click="load()"
                class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100">
                Try again
            </button>
        </div>

        {{-- Refresh over an existing summary keeps it on screen, dimmed --}}
        <div class="relative" :class="status === 'loading' && html ? 'pointer-events-none opacity-60' : ''">
            <div x-html="html"></div>
        </div>
    </div>
@endif
