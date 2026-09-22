<script>
    /**
     * Keep the browser from dropping the signed-in account's email into
     * this app's own text fields.
     *
     * Chrome remembers the credentials used to sign in here and, on any
     * later page of the same origin, fills that username into whatever
     * text input its heuristics decide looks like a login box - a list
     * search box, a nurse's email, an app-username field. It ignores
     * autocomplete="off" when it does this.
     *
     * Two things stop it, in this order:
     *
     *   1. Every guarded field is marked read-only as it is parsed.
     *      Chrome skips read-only fields entirely, which is the only
     *      thing that reliably beats the page-load fill. The flag is
     *      dropped the instant the field is focused, clicked or typed
     *      into, so it is invisible to whoever is using the page.
     *   2. A watchdog, in case a fill still lands: a field the user has
     *      never touched that the browser has flagged as autofilled is
     *      put back to what the server rendered.
     *
     * Values the page itself renders live in `defaultValue`, which
     * autofill does not touch - a search term kept across a reload, or a
     * nurse's saved email, both survive.
     *
     * Include this in the <head> of every signed-in document - the app
     * layout and each stand-alone iframe page - so the observer below sees
     * inputs as they are parsed, ahead of the browser's first fill pass.
     * It is deliberately left out of the sign-in pages, where the browser
     * filling in the account is the whole point.
     *
     * Opt a single field back in with data-autofill-allow.
     */
    (function () {
        // Types the browser will put a username or an email into.
        const GUARDED_TYPES = ['text', 'search', 'email', 'tel', 'url', ''];

        // Fields the user has focused, clicked or typed in. Once someone
        // has touched a field its contents are their business, including
        // a suggestion they picked themselves.
        const touched = new WeakSet();

        function isGuarded(el) {
            return el instanceof HTMLInputElement
                && !el.hasAttribute('data-autofill-allow')
                && GUARDED_TYPES.includes((el.getAttribute('type') || '').toLowerCase());
        }

        // The unprompted fill happens while the page is loading. Once that
        // window has passed there is nothing left to block, so the fields
        // are handed back - a read-only field is exempt from `required`,
        // and we would rather not weaken that for the whole session.
        let fillWindowOpen = true;

        function harden(el) {
            if (el.dataset.autofillGuarded) return;
            el.dataset.autofillGuarded = '1';
            el.setAttribute('autocomplete', 'off');
            el.setAttribute('data-lpignore', 'true');   // LastPass
            el.setAttribute('data-form-type', 'other'); // Dashlane
            el.setAttribute('data-1p-ignore', '');      // 1Password

            if (fillWindowOpen && !el.readOnly) {
                el.readOnly = true;
                el.dataset.autofillReadonly = '1';
            }
        }

        // Drop only the read-only flag this script set, never one the page
        // asked for itself.
        function unlock(el) {
            if (el.dataset.autofillReadonly) {
                el.readOnly = false;
                delete el.dataset.autofillReadonly;
            }
        }

        // Touched by the user: hand the field back and stop policing it,
        // including a suggestion they picked themselves.
        function release(el) {
            touched.add(el);
            unlock(el);
        }

        function closeFillWindow() {
            if (!fillWindowOpen) return;
            fillWindowOpen = false;
            document.querySelectorAll('[data-autofill-readonly]').forEach(unlock);
        }

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
            if (touched.has(el)) return;
            if (!isAutofilled(el)) return;

            const rendered = el.defaultValue || '';
            if (el.value === rendered) return;

            el.value = rendered;
            // Alpine and other x-model bindings track `input`, so tell them.
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function sweep(root) {
            (root || document).querySelectorAll('input').forEach(function (el) {
                if (!isGuarded(el)) return;
                harden(el);
                restore(el);
            });
        }

        ['pointerdown', 'focusin', 'keydown', 'paste', 'compositionstart'].forEach(function (type) {
            document.addEventListener(type, function (e) {
                if (isGuarded(e.target)) release(e.target);
            }, true);
        });

        // Autofill lands at unpredictable moments - on paint, and again
        // once the page settles.
        document.addEventListener('DOMContentLoaded', function () { sweep(); });
        window.addEventListener('load', function () { sweep(); });
        [50, 250, 700, 1500].forEach(function (ms) { setTimeout(function () { sweep(); }, ms); });
        window.addEventListener('load', function () { setTimeout(closeFillWindow, 2000); });
        setTimeout(closeFillWindow, 5000); // however the load event goes

        // Fields still being parsed, and those inside modals and other
        // markup added later.
        new MutationObserver(function (records) {
            records.forEach(function (record) {
                record.addedNodes.forEach(function (node) {
                    if (node.nodeType !== 1) return;
                    if (isGuarded(node)) { harden(node); restore(node); }
                    else if (node.querySelectorAll) sweep(node);
                });
            });
        }).observe(document.documentElement, { childList: true, subtree: true });
    })();
</script>
