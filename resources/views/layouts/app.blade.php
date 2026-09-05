<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts - Using system fonts for local hosting -->
    <style>
        .font-sans {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gradient-to-br from-blue-50 via-cyan-50 to-teal-50 overflow-hidden">
    <div class="flex h-screen min-h-0" x-data="{ hideNav: false, hideFooter: false }"
        @fullscreenchange.window="hideNav = !!document.fullscreenElement"
        @toggle-custom-fullscreen.window="hideNav = $event.detail.enabled; hideFooter = $event.detail.enabled">
        <div x-show="!hideNav" x-transition:enter="transition ease-out duration-200"
            x-transition:leave="transition ease-in duration-200">
            @include('layouts.navigation')
        </div>

        <div class="flex-1 flex flex-col overflow-hidden min-h-0">
            <!-- Page Heading -->
            @isset($header)
                <header class="{{ request()->routeIs('ward.dashboard') ? 'bg-gradient-to-br from-blue-600 to-cyan-500 shadow-sm border-b border-blue-400' : 'bg-white/80 backdrop-blur-sm shadow-sm border-b border-blue-100' }}">
                    <div class="w-full px-4 sm:px-6 lg:px-8 py-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto overscroll-contain min-h-0">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer x-show="!hideFooter" x-transition class="bg-white/60 backdrop-blur-sm border-t border-blue-100 py-4"
                :class="hideNav ? 'fixed bottom-0 left-0 right-0 z-50' : ''">
                <div class="w-full px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-600">
                            <span class="font-semibold text-blue-600">PHKL</span> Smart Ward 4.0
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-xs text-gray-500">Developed by</span>
                            <a href="https://qmed.asia" target="_blank" class="hover:opacity-75 transition-opacity">
                                <img src="{{ asset('logo_qmed.png') }}" alt="Qmed" class="h-5 w-auto">
                            </a>
                            <span class="text-xs text-gray-400">© 2025</span>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>
    @include('components.delete-passphrase-modal')

    <script>
        /**
         * Keep search and filter boxes empty until someone actually types.
         *
         * Chrome ignores autocomplete="off" and will drop the signed-in
         * account's email into a lone text input it decides looks like a
         * username field, so several search boxes came up pre-filled. The
         * attributes below ask the browser and the common password managers
         * to leave these fields alone; the watchdog is what guarantees it,
         * by putting back whatever the server rendered whenever the value
         * changes without a keystroke behind it.
         *
         * Server-rendered values (a search term kept across a page reload)
         * live in `defaultValue`, which autofill does not touch - so they
         * survive, and only the browser's guesses get wiped.
         */
        (function () {
            const typed = new WeakSet();
            const SEARCHY = /search|filter|query/i;

            function isSearchBox(el) {
                if (!(el instanceof HTMLInputElement)) return false;
                if (!['text', 'search', ''].includes(el.type)) return false;

                return SEARCHY.test(el.id) || SEARCHY.test(el.name)
                    || SEARCHY.test(el.placeholder) || SEARCHY.test(el.className)
                    || SEARCHY.test(el.getAttribute('x-model') || '');
            }

            function harden(el) {
                if (el.dataset.autofillGuarded) return;
                el.dataset.autofillGuarded = '1';
                el.setAttribute('autocomplete', 'off');
                el.setAttribute('data-lpignore', 'true');   // LastPass
                el.setAttribute('data-form-type', 'other'); // Dashlane
                el.setAttribute('data-1p-ignore', '');      // 1Password
            }

            // Browsers flag the fields they filled themselves. That is the one
            // signal that separates the browser's guess from a value the app
            // put there on purpose.
            function isAutofilled(el) {
                for (const selector of [':autofill', ':-webkit-autofill']) {
                    try {
                        if (el.matches(selector)) return true;
                    } catch (e) {
                        // Selector unsupported in this browser - try the next.
                    }
                }
                return false;
            }

            function restore(el) {
                if (typed.has(el)) return;

                const rendered = el.defaultValue || '';
                if (el.value === rendered) return;

                // An x-model field's value belongs to Alpine, which sets it
                // from its own state - only clear those when the browser has
                // actually flagged them as autofilled, or we would wipe values
                // the page deliberately put there.
                if (el.hasAttribute('x-model') && !isAutofilled(el)) return;

                el.value = rendered;
                // Alpine and other x-model bindings track `input`, so tell them.
                el.dispatchEvent(new Event('input', { bubbles: true }));
            }

            function sweep(root) {
                (root || document).querySelectorAll('input').forEach(function (el) {
                    if (!isSearchBox(el)) return;
                    harden(el);
                    restore(el);
                });
            }

            ['keydown', 'paste', 'compositionstart'].forEach(function (type) {
                document.addEventListener(type, function (e) {
                    if (isSearchBox(e.target)) typed.add(e.target);
                }, true);
            });

            // Autofill lands at unpredictable moments - on paint, once the page
            // settles, and again the first time a field is focused.
            document.addEventListener('DOMContentLoaded', function () { sweep(); });
            window.addEventListener('load', function () { sweep(); });
            [50, 250, 700, 1500].forEach(function (ms) { setTimeout(function () { sweep(); }, ms); });

            document.addEventListener('focusin', function (e) {
                if (isSearchBox(e.target)) { harden(e.target); restore(e.target); }
            });

            // Fields inside modals and other late-rendered markup.
            new MutationObserver(function (records) {
                records.forEach(function (record) {
                    record.addedNodes.forEach(function (node) {
                        if (node.nodeType !== 1) return;
                        if (isSearchBox(node)) { harden(node); restore(node); }
                        else sweep(node);
                    });
                });
            }).observe(document.documentElement, { childList: true, subtree: true });
        })();
    </script>
</body>

</html>