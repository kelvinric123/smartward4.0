<?php

namespace App\Support;

/**
 * The evidence behind the AI Nurse Schedule's patient acuity and acuity-based
 * staffing: the sources, and the principles the page applies with where each
 * one is in the source. The Patient acuity tab, the printouts and the shared
 * view all show them from here, so the references read the same everywhere.
 *
 * Page numbers are the pages of the ANA PDF. The other sources are cited by
 * the ANA paper and named here as cited in it.
 */
final class AcuityReferences
{
    public const PRIMARY = 'ana2017';

    public const SOURCES = [
        'ana2017' => [
            'short' => 'ANA, 2017',
            'citation' => 'American Nurses Association. (2017). Defining staffing: Workforce management, patient classification and acuity systems, the request for proposal process. Silver Spring, MD: American Nurses Association.',
            'url' => 'https://www.nursingworld.org/globalassets/practiceandpolicy/work-environment/nurse-staffing/website-staffing-and-acuity-systems-pdf-final_2017.pdf',
            'cited' => false,
        ],
        'degroot1989' => [
            'short' => 'De Groot, 1989',
            'citation' => 'De Groot, H. A. (1989). Patient classification system evaluation: Essential system elements, part 1. Journal of Nursing Administration, 19(6), 30-35.',
            'url' => null,
            'cited' => true,
        ],
        'bolton2001' => [
            'short' => 'Bolton et al., 2001',
            'citation' => 'Bolton, L., Jones, D., Aydin, C., Donaldson, N., Brown, D., Lowe, M., McFarland, P., & Harms, D. (2001). A response to California\'s mandated nursing ratios. Journal of Nursing Scholarship, 33(2), 179-184.',
            'url' => null,
            'cited' => true,
        ],
        'fasoli2011' => [
            'short' => 'Fasoli et al., 2011',
            'citation' => 'Fasoli, D., Fincke, M., & Haddock, K. (2011). Going beyond patient classification systems to create an evidence-based staffing methodology. Journal of Nursing Administration, 41(10), 434-439.',
            'url' => null,
            'cited' => true,
        ],
        'okeefe2016' => [
            'short' => 'O\'Keefe, 2016',
            'citation' => 'O\'Keefe, M. (2016). Practical steps for applying acuity-based staffing. American Nurse Today, 11(9), 30-34.',
            'url' => null,
            'cited' => true,
        ],
    ];

    /**
     * Bolton and colleagues' point that a fixed minimum does not follow the
     * patients' needs, cited the same wherever the tab, the printouts or the
     * code make it. It supports keeping the minimum as a floor only.
     */
    public const RATIOS_CITE = 'Bolton et al., 2001, as cited in ANA, 2017, p. 11';

    /**
     * The principles the page applies: what the source says (paraphrased, one
     * short quotation), where, and how this page puts it into practice. A
     * sentence from a different place in the source goes under 'more', with
     * its own citation, so each citation covers only what it supports.
     */
    public const PRINCIPLES = [
        [
            'title' => 'What a patient classification system is',
            'text' => 'The methods and processes for determining, validating and monitoring each patient\'s care requirements over time, used for unit staffing, patient assignments, case mix analysis and budget planning.',
            'cite' => 'De Groot, 1989, as cited in ANA, 2017, pp. 9-10',
            'more' => [
                ['text' => 'ANA adds equal workload distribution to the uses of classification data.', 'cite' => 'ANA, 2017, p. 10'],
            ],
            'applied' => 'Each patient has a care level, every rating is kept with the indicators at the time, and the levels drive the staffing below and the workload on the Bed assignment tab.',
        ],
        [
            'title' => 'Match nurses to patients',
            'text' => 'Staffing matches resources to patients on their care needs, the staff skill mix, education and competencies, and unit variables.',
            'cite' => 'ANA, 2017, pp. 3-4',
            'applied' => 'The recommendation counts registered nurses only; students and care assistants are shown but not counted toward the care hours.',
        ],
        [
            'title' => 'Scheduling, staffing and improving',
            'text' => 'Workforce management has four phases: forecasting, scheduling (weeks ahead), staffing (4 to 48 hours before the shift) and improving by monitoring outcomes.',
            'cite' => 'ANA, 2017, pp. 4-7',
            'applied' => 'The Roster tab schedules, this tab checks each shift against the patients\' needs, and the rating check below supports improving.',
        ],
        [
            'title' => 'Care levels (prototype classification)',
            'text' => 'A prototype tool describes care levels in general terms, such as minimum, moderate and maximum care, and the nurse matches each patient to the closest description.',
            'cite' => 'ANA, 2017, pp. 10-11',
            'applied' => 'Levels 1 Minimal to 4 Intensive, the levels the ward dashboard already shows.',
        ],
        [
            'title' => 'Ratios alone are not enough',
            'text' => 'Fixed ratios and grids identify only minimum staffing and do not adjust for patients\' changing care needs.',
            'cite' => self::RATIOS_CITE,
            'applied' => 'The minimum in the staffing rules stays the floor; the acuity recommendation is shown beside it, shift by shift.',
        ],
        [
            'title' => 'Care is more than a list of tasks',
            'text' => 'Care-interaction tools rate distinct dimensions of care (physical, psychological, social) and treat patient care as more than the sum of its tasks, so they rely on professional judgement.',
            'cite' => 'ANA, 2017, pp. 11-12',
            'applied' => 'Indicators are grouped into areas of care; needs in three or more areas at once raise the suggestion; the nurse\'s rating stands over the suggestion.',
        ],
        [
            'title' => 'Validity and reliability',
            'text' => 'A tool is valid when research shows the care patients receive rises with each level; a tool without established validity should not be used. It is reliable when nurses who know a patient rate them independently and choose the same level, and each rater\'s reliability should be checked at least once a year.',
            'cite' => 'ANA, 2017, pp. 12-14',
            'applied' => 'The indicators and levels are not validated: ANA expects validity to be shown by research before a tool is relied on, and warns against tools without it (pp. 12-13). The rating check shows how often ratings agree with the indicators, which is not reliability between nurses; for that, nurses rate the same patients independently, at least once a year (pp. 13-14).',
        ],
        [
            'title' => 'Simple, visible and verifiable',
            'text' => 'Use as few indicators as capture the care, and make the ratings easy to verify objectively.',
            'cite' => 'Fasoli et al., 2011, as cited in ANA, 2017, pp. 14-15',
            'more' => [
                ['text' => 'The nurses choosing a system should see every indicator, its weighting and how the care levels are calculated.', 'cite' => 'ANA, 2017, p. 14'],
            ],
            'applied' => 'Every indicator, the level it suggests and its data source are listed under How the levels are suggested, and each patient shows the indicators behind the suggestion.',
        ],
        [
            'title' => 'Why acuity-based staffing',
            'quote' => 'patient acuity-driven staffing is an effective way to optimize nurse staffing',
            'text' => 'A growing body of evidence supports staffing to patient acuity to improve patient outcomes.',
            'cite' => 'O\'Keefe, 2016, as cited in ANA, 2017, p. 9',
            'applied' => 'Recommended nurses per shift are worked out from the care levels, not from the bed count.',
        ],
        [
            'title' => 'Hours of care per patient day',
            'text' => 'Total hours of nursing care per patient per day is one of the quality benchmarks an organisation may track to judge whether a new acuity or classification system is working.',
            'cite' => 'ANA, 2017, p. 24',
            'applied' => 'The hours of care for each level are the ward\'s own settings: ANA gives no hours per level. The defaults are SmartWard\'s starting values, to be checked against the ward\'s own care.',
        ],
    ];

    public const DISCLAIMER = 'A planning aid, not a validated acuity tool. The indicators, levels and hours are starting values: check them against your ward\'s own care before relying on them, and let the nurse\'s judgement decide.';

    /** The sources in citation order: the ANA paper, then those it cites. */
    public static function sources(): array
    {
        return self::SOURCES;
    }

    public static function primary(): array
    {
        return self::SOURCES[self::PRIMARY];
    }
}
