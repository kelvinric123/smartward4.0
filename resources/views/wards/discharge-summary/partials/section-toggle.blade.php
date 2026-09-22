{{-- Section visibility, shared by the view's tabs and the print page's section
     chooser. Both drive the same [data-section] blocks in partials/summary,
     so neither has to know what a section contains. --}}
<style>
    /* The group wrapper carries `grid`, and a class beats the browser's own
       [hidden] rule, so hiding needs saying explicitly. */
    [hidden] {
        display: none !important;
    }
</style>

<script>
    (function () {
        /**
         * Show only the named sections. Pass the string 'all' for every one.
         */
        window.showSummarySections = function (keys) {
            const wanted = keys === 'all' ? null : new Set(keys);

            document.querySelectorAll('[data-section]').forEach(function (section) {
                section.hidden = wanted !== null && !wanted.has(section.dataset.section);
            });

            // A row holding two sections side by side has to collapse when
            // neither is shown, or its margin leaves a gap behind.
            document.querySelectorAll('[data-section-group]').forEach(function (group) {
                group.hidden = Array.from(group.querySelectorAll('[data-section]'))
                    .every(function (section) { return section.hidden; });
            });
        };
    })();
</script>
