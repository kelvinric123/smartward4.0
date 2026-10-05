<?php

namespace App\Models\Concerns;

use App\Models\VitalSign;
use Illuminate\Support\Str;

/**
 * Oxygen as the bedside records it: a delivery device (VitalSign::OXYGEN_DELIVERY_OPTIONS)
 * with a flow rate in L/min and/or an FiO2, where the device has them. Vital sign readings
 * and Oxygen Therapy changes keep it in the same three columns: oxygen_delivery,
 * oxygen_flow_rate and fio2_percent.
 */
trait DescribesOxygen
{
    /**
     * Oxygen delivery label, e.g. "Nasal Cannula / Prongs". Null when nothing was recorded.
     */
    public function oxygenDeliveryLabel(): ?string
    {
        if (!$this->oxygen_delivery) {
            return null;
        }

        return VitalSign::OXYGEN_DELIVERY_OPTIONS[$this->oxygen_delivery] ?? Str::headline($this->oxygen_delivery);
    }

    /**
     * Short chart label, e.g. "NP 2L" or "VM 40%". Null when nothing was recorded.
     */
    public function oxygenShortLabel(): ?string
    {
        if (!$this->oxygen_delivery) {
            return null;
        }

        $label = VitalSign::OXYGEN_DELIVERY_SHORT[$this->oxygen_delivery] ?? strtoupper(substr($this->oxygen_delivery, 0, 4));

        if ($this->oxygen_flow_rate !== null) {
            $label .= ' ' . self::formatFlowRate($this->oxygen_flow_rate) . 'L';
        } elseif ($this->fio2_percent !== null) {
            $label .= ' ' . $this->fio2_percent . '%';
        }

        return $label;
    }

    /**
     * The settings in words: "2 L/min", "FiO₂ 40%", "50 L/min, FiO₂ 60%", or "No supplemental O₂"
     * on room air. Null when nothing was recorded, or a device was recorded without settings.
     */
    public function oxygenSettingsLabel(): ?string
    {
        if (!$this->oxygen_delivery) {
            return null;
        }

        if (!$this->isOnOxygen()) {
            return 'No supplemental O₂';
        }

        $parts = array_filter([
            $this->oxygen_flow_rate !== null ? self::formatFlowRate($this->oxygen_flow_rate) . ' L/min' : null,
            $this->fio2_percent !== null ? 'FiO₂ ' . $this->fio2_percent . '%' : null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    /**
     * On supplemental oxygen: anything recorded other than room air.
     * Display only - the EWS score deliberately does not use this.
     */
    public function isOnOxygen(): bool
    {
        return $this->oxygen_delivery !== null && $this->oxygen_delivery !== VitalSign::OXYGEN_ROOM_AIR;
    }

    /**
     * Same device, flow rate and FiO2 as another record (a reading or a change).
     */
    public function hasSameOxygenAs(object $other): bool
    {
        $same = fn ($a, $b) => $a === null || $b === null ? $a === $b : abs((float) $a - (float) $b) < 0.01;

        return $this->oxygen_delivery === $other->oxygen_delivery
            && $same($this->oxygen_flow_rate, $other->oxygen_flow_rate)
            && $same($this->fio2_percent, $other->fio2_percent);
    }

    /**
     * A flow rate without trailing zeros: 2.0 -> "2", 0.5 -> "0.5".
     */
    public static function formatFlowRate(float|int|string $flowRate): string
    {
        return rtrim(rtrim(number_format((float) $flowRate, 1), '0'), '.');
    }
}
