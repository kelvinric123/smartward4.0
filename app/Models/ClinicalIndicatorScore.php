<?php

namespace App\Models;

use App\Support\ClinicalIndicatorLibrary;
use App\Support\ClinicalIndicatorReadings;
use App\Support\ClinicalIndicatorScreen;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalIndicatorScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'clinical_indicator_id',
        'ward_id',
        'score',
        'band_label',
        'band_tone',
        'item_scores',
        'notes',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'item_scores' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function clinicalIndicator()
    {
        return $this->belongsTo(ClinicalIndicator::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Resolve and stamp the risk band for a score against a scale. Stored on
     * the row so a later change to the library's bands cannot silently rewrite
     * how an already-recorded score reads.
     */
    public function applyBand(string $indicatorCode): self
    {
        $band = ClinicalIndicatorLibrary::bandFor($indicatorCode, $this->score);

        $this->band_label = $band['label'] ?? null;
        $this->band_tone = $band['tone'] ?? null;

        return $this;
    }

    /**
     * Readings typed in from the monitor (the hemodynamic numerics) rather
     * than a scale scored item by item. Their score is only the worst flag, so
     * the readings are what to show.
     */
    public function isReadings(): bool
    {
        return !empty($this->item_scores) && isset($this->item_scores[0]['unit']);
    }

    /**
     * A screen asked question by question (the C-SSRS) rather than a scale
     * totalled item by item. Its score is only the most serious answer, so
     * the band and the answers are what to show.
     */
    public function isScreen(): bool
    {
        return !empty($this->item_scores) && array_key_exists('asked', $this->item_scores[0]);
    }

    /**
     * Whether the score is a total worth showing. Readings and screens keep
     * only the level their worst reading or most serious answer reached.
     */
    public function hasTotal(): bool
    {
        return !$this->isReadings() && !$this->isScreen();
    }

    /**
     * The item breakdown in its conventional short form, such as E3 V4 M6 for
     * GCS, where the total alone would hide which response changed. Null for
     * scales whose items carry no abbr, which are read by their total.
     *
     * Readings come back as the values with their units, arrows marking any
     * outside the normal range, e.g. ABP 85/42 (56) mmHg ↓↓ · CVP 12 mmHg ↑.
     * A screen gives the questions answered yes, e.g. Yes to Q1, Q2.
     */
    public function breakdown(): ?string
    {
        if (empty($this->item_scores)) {
            return null;
        }

        if ($this->isReadings()) {
            return ClinicalIndicatorReadings::summary($this->item_scores, $this->clinicalIndicator?->definition());
        }

        if ($this->isScreen()) {
            return ClinicalIndicatorScreen::summary($this->item_scores);
        }

        $parts = [];
        foreach ($this->item_scores as $item) {
            if (empty($item['abbr'])) {
                return null;
            }
            $parts[] = $item['abbr'] . $item['value'];
        }

        return implode(' ', $parts);
    }
}
