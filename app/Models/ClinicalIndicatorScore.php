<?php

namespace App\Models;

use App\Support\ClinicalIndicatorLibrary;
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
     * The item breakdown in its conventional short form, such as E3 V4 M6 for
     * GCS, where the total alone would hide which response changed. Null for
     * scales whose items carry no abbr, which are read by their total.
     */
    public function breakdown(): ?string
    {
        if (empty($this->item_scores)) {
            return null;
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
