{{--
    State for the Bed assignment tab: which nurse each bed goes to. The review
    summary, the nurse cards and the bed table all read and change the same
    map, so an edit anywhere shows everywhere, and loads and balance are
    worked out live. Nothing is saved until the form is submitted.
--}}
<script>
    window.bedAssignment = function (config) {
        return {
            beds: config.beds,
            nurses: config.nurses,
            mapping: { ...config.mapping },
            saved: config.saved,
            proposal: { ...config.mapping },
            band: config.band,
            reviewOpen: config.openReview,
            weightsOpen: config.weightsOpen,
            showChanges: false,

            nurseName(id) {
                const nurse = this.nurses.find(n => String(n.id) === String(id));
                return nurse ? nurse.name : 'another nurse';
            },
            bedsOf(nurseId) {
                return this.beds.filter(bed => this.mapping[bed.id] === String(nurseId));
            },
            get unassigned() {
                return this.beds.filter(bed => !this.mapping[bed.id]);
            },
            get loads() {
                const loads = this.nurses.map(nurse => {
                    const beds = this.bedsOf(nurse.id);
                    return {
                        ...nurse,
                        beds,
                        patients: beds.filter(bed => bed.patient).length,
                        score: Math.round(beds.reduce((sum, bed) => sum + bed.score, 0) * 10) / 10,
                    };
                // Someone off the roster only counts while they still hold beds
                }).filter(load => load.rostered || load.beds.length);
                const average = loads.length ? loads.reduce((sum, load) => sum + load.score, 0) / loads.length : 0;
                return loads.map(load => ({
                    ...load,
                    level: average <= 0 ? 'even'
                        : load.score > average * (1 + this.band) ? 'heavy'
                        : load.score < average * (1 - this.band) ? 'light' : 'even',
                }));
            },
            get maxScore() {
                return Math.max(1, ...this.loads.map(load => load.score));
            },
            get spread() {
                const scores = this.loads.map(load => load.score);
                return scores.length ? { min: Math.min(...scores), max: Math.max(...scores) } : { min: 0, max: 0 };
            },
            isChanged(bedId) {
                return (this.mapping[bedId] || '') !== (this.saved[bedId] || '');
            },
            get changes() {
                return this.beds.filter(bed => this.isChanged(bed.id)).map(bed => ({
                    bed,
                    from: this.saved[bed.id] ? this.nurseName(this.saved[bed.id]) : 'Unassigned',
                    to: this.mapping[bed.id] ? this.nurseName(this.mapping[bed.id]) : 'Unassigned',
                }));
            },
            get editedSinceSuggestion() {
                return this.beds.some(bed => (this.mapping[bed.id] || '') !== (this.proposal[bed.id] || ''));
            },
            move(bedId, nurseId) {
                this.mapping[bedId] = nurseId;
            },
            backToSuggestion() {
                this.mapping = { ...this.proposal };
            },
            savedName(bedId) {
                return this.saved[bedId] ? this.nurseName(this.saved[bedId]) : 'unassigned';
            },
            levelLabel(level) {
                return { heavy: 'Heavy', light: 'Light', even: 'Balanced' }[level];
            },
            levelBadge(level) {
                return { heavy: 'bg-red-100 text-red-800', light: 'bg-sky-100 text-sky-800', even: 'bg-emerald-100 text-emerald-800' }[level];
            },
            levelBar(level) {
                return { heavy: 'bg-red-500', light: 'bg-sky-400', even: 'bg-emerald-500' }[level];
            },
            scoreChip(score) {
                return score >= 3 ? 'bg-red-100 text-red-800' : (score >= 2 ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700');
            },
            format(value) {
                return (Math.round(value * 10) / 10).toFixed(1);
            },
        };
    };
</script>
