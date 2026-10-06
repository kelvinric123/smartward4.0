import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../theme';
import Pill from './Pill';
import VitalsTrendModal from './VitalsTrendModal';

function ewsTone(ews) {
  if (ews == null) return { bg: 'rgba(255,255,255,0.18)', color: '#fff', label: 'No vitals' };
  if (ews >= 5) return { bg: colors.rose500, color: '#fff', label: `EWS ${ews}` };
  if (ews >= 3) return { bg: colors.amber500, color: '#fff', label: `EWS ${ews}` };
  return { bg: colors.emerald500, color: '#fff', label: `EWS ${ews}` };
}

function infusionTone(status) {
  if (status === 'alarming') return { bg: colors.rose100, color: colors.rose700 };
  if (status === 'paused') return { bg: colors.amber100, color: colors.amber700 };
  return { bg: colors.emerald100, color: colors.emerald700 };
}

function headerGradientFor(status) {
  // RN core has no gradient — pick a solid that matches the vibe.
  if (status === 'reserved') return '#92400e';
  if (status === 'occupied') return '#0b3a4a';
  return '#475569';
}

function fallRiskLabel(fr) {
  if (!fr) return 'None';
  return fr.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function VitalTile({ label, value, onPress, disabled }) {
  const inner = (
    <>
      <Text style={styles.vitalLabel}>{label}</Text>
      <Text style={styles.vitalValue}>{value ?? '--'}</Text>
      {!disabled && onPress ? (
        <Text style={styles.vitalTapHint}>tap ›</Text>
      ) : null}
    </>
  );
  if (disabled || !onPress) {
    return <View style={styles.vital}>{inner}</View>;
  }
  return (
    <TouchableOpacity onPress={onPress} activeOpacity={0.7} style={styles.vital}>
      {inner}
    </TouchableOpacity>
  );
}

function DetailRow({ label, value, valueColor }) {
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={[styles.detailValue, valueColor && { color: valueColor }]} numberOfLines={2}>
        {value}
      </Text>
    </View>
  );
}

export default function BedCard({ bed, fallbackNurse }) {
  const [trendOpen, setTrendOpen] = useState(false);
  const [trendMetric, setTrendMetric] = useState('systolic_bp');
  const openTrend = (metricKey) => {
    setTrendMetric(metricKey);
    setTrendOpen(true);
  };
  const ews = bed.ews_has_vitals ? bed.ews : null;
  const ewsBadge = ewsTone(ews);
  const headerBg = headerGradientFor(bed.status);
  const v = bed.vitals ?? {};
  const bp =
    v.systolic_bp || v.diastolic_bp
      ? `${v.systolic_bp ?? '--'}/${v.diastolic_bp ?? '--'}`
      : '--';

  return (
    <View style={styles.card}>
      <View style={[styles.header, { backgroundColor: headerBg }]}>
        <View style={styles.headerRow}>
          <View style={{ flex: 1, paddingRight: 12 }}>
            <Pill label={`BED ${bed.number}`} bg="rgba(255,255,255,0.14)" color={colors.cyan50} />
            <Text style={styles.patientName} numberOfLines={1}>
              {bed.patient_name ?? 'Unoccupied Bed'}
            </Text>
            <Text style={styles.patientMeta} numberOfLines={1}>
              MRN {bed.mrn ?? 'N/A'}
              {(bed.gender || bed.age) ? `   ·   ${bed.gender ?? '-'}, ${bed.age ?? '-'} yrs` : ''}
            </Text>
          </View>
          <View style={{ alignItems: 'flex-end' }}>
            <Pill label={ewsBadge.label} bg={ewsBadge.bg} color={ewsBadge.color} size="lg" />
            <Text style={styles.statusLabel}>{bed.status?.toUpperCase?.()}</Text>
          </View>
        </View>
      </View>

      <View style={styles.body}>
        <View style={styles.row2}>
          <View style={styles.miniBox}>
            <Text style={styles.miniLabel}>STAY</Text>
            <Text style={styles.miniValue}>
              {bed.days != null ? `${bed.days}d ${bed.hours ?? 0}h` : 'N/A'}
            </Text>
          </View>
          <View style={styles.miniBox}>
            <Text style={styles.miniLabel}>NURSE</Text>
            <Text style={styles.miniValue} numberOfLines={2}>
              {bed.nurse_on_duty ?? fallbackNurse}
            </Text>
          </View>
        </View>

        <View style={styles.sectionBordered}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>PATIENT DETAILS</Text>
            {bed.is_pending_discharge ? (
              <Pill label="Pending discharge" bg={colors.amber100} color={colors.amber700} />
            ) : null}
          </View>
          <DetailRow label="Consultant" value={bed.consultant ?? 'Not assigned'} />
          <DetailRow label="Diet" value={bed.diet_types_display ?? 'Regular diet'} />
          <DetailRow label="Isolation" value={bed.isolation_type_name ?? 'None'} />
          <DetailRow label="Fall risk" value={fallRiskLabel(bed.fall_risk)} />
          {bed.is_outside ? (
            <DetailRow
              label="Movement"
              value={bed.current_movement_location ?? 'Outside ward'}
              valueColor={colors.amber700}
            />
          ) : null}
        </View>

        <View style={styles.sectionDark}>
          <View style={styles.sectionHeader}>
            <Text style={[styles.sectionTitle, { color: colors.cyan100 }]}>LATEST VITALS</Text>
            <Text style={styles.darkMeta}>{v.recorded_at_label ?? 'Awaiting data'}</Text>
          </View>
          <Text style={styles.trendHintText}>Tap any value to view its trend chart</Text>
          <View style={styles.vitalsGrid}>
            <VitalTile label="PULSE" value={v.pulse_rate} onPress={() => openTrend('pulse_rate')} />
            <VitalTile label="BP" value={bp} onPress={() => openTrend('systolic_bp')} />
            <VitalTile label="SPO2" value={v.spo2 ? `${v.spo2}%` : '--'} onPress={() => openTrend('spo2')} />
            <VitalTile label="RESP" value={v.respiratory_rate} onPress={() => openTrend('respiratory_rate')} />
            <VitalTile
              label="TEMP"
              value={v.temperature != null ? `${Number(v.temperature).toFixed(1)}°C` : '--'}
              onPress={() => openTrend('temperature')}
            />
            <VitalTile label="HGT" value={bed.last_hgt?.value ?? '--'} disabled />
          </View>
        </View>

        <View style={styles.sectionBordered}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>INFUSION</Text>
            <Pill
              label={`${bed.infusions?.length ?? 0} active`}
              bg={colors.slate100}
              color={colors.slate600}
            />
          </View>

          {!bed.infusions || bed.infusions.length === 0 ? (
            <Text style={styles.emptyText}>No active infusion activity for this patient.</Text>
          ) : (
            bed.infusions.slice(0, 3).map((inf) => {
              const tone = infusionTone(inf.status);
              return (
                <View key={inf.id} style={styles.infusionRow}>
                  <View style={styles.infusionTop}>
                    <View style={{ flex: 1, paddingRight: 8 }}>
                      <Text style={styles.medName} numberOfLines={1}>
                        {inf.medication_name}
                      </Text>
                      <Text style={styles.medMeta} numberOfLines={1}>
                        {inf.device_id ?? 'Unknown device'}
                        {inf.flow_rate ? `   ·   ${Number(inf.flow_rate).toFixed(2)} mL/hr` : ''}
                      </Text>
                    </View>
                    <View style={{ gap: 4, alignItems: 'flex-end' }}>
                      <Pill
                        label={inf.status?.toUpperCase?.() ?? ''}
                        bg={tone.bg}
                        color={tone.color}
                      />
                      {inf.is_warning ? (
                        <Pill label="WARNING" bg={colors.amber100} color={colors.amber700} />
                      ) : null}
                    </View>
                  </View>
                  <View style={styles.infusionGrid}>
                    <View style={styles.infusionCell}>
                      <Text style={styles.infusionCellLabel}>REMAINING</Text>
                      <Text style={styles.infusionCellValue}>{inf.formatted_remaining_time}</Text>
                    </View>
                    <View style={styles.infusionCell}>
                      <Text style={styles.infusionCellLabel}>VOLUME</Text>
                      <Text style={styles.infusionCellValue}>
                        {inf.remaining_volume != null
                          ? `${Number(inf.remaining_volume).toFixed(1)} mL`
                          : '--'}
                      </Text>
                    </View>
                    <View style={styles.infusionCell}>
                      <Text style={styles.infusionCellLabel}>UPDATED</Text>
                      <Text style={styles.infusionCellValue}>{inf.last_updated_label ?? '--'}</Text>
                    </View>
                  </View>
                </View>
              );
            })
          )}
        </View>
      </View>

      <VitalsTrendModal
        visible={trendOpen}
        onClose={() => setTrendOpen(false)}
        bed={bed}
        initialMetric={trendMetric}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderRadius: radius.xl,
    backgroundColor: colors.card,
    overflow: 'hidden',
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 18 },
    shadowOpacity: 0.12,
    shadowRadius: 28,
    elevation: 4,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
  },
  header: {
    paddingHorizontal: 16,
    paddingVertical: 14,
  },
  headerRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
  },
  patientName: {
    marginTop: 8,
    color: '#fff',
    fontSize: 20,
    fontWeight: '800',
  },
  patientMeta: {
    marginTop: 2,
    color: 'rgba(207, 250, 254, 0.85)',
    fontSize: 12,
  },
  statusLabel: {
    marginTop: 6,
    color: 'rgba(207, 250, 254, 0.8)',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
  },
  body: {
    padding: 14,
    gap: 12,
  },
  row2: {
    flexDirection: 'row',
    gap: 10,
  },
  miniBox: {
    flex: 1,
    borderRadius: radius.md,
    backgroundColor: colors.slate50,
    padding: 10,
  },
  miniLabel: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 1.4,
    color: colors.mutedSoft,
  },
  miniValue: {
    marginTop: 4,
    fontSize: 13,
    fontWeight: '700',
    color: colors.slate900,
  },
  sectionBordered: {
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: 12,
  },
  sectionDark: {
    borderRadius: radius.lg,
    backgroundColor: colors.slate950,
    padding: 12,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  sectionTitle: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
    color: colors.muted,
  },
  darkMeta: {
    fontSize: 10,
    color: '#cbd5e1',
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: 4,
    gap: 16,
  },
  detailLabel: {
    color: colors.muted,
    fontSize: 12,
    flex: 0,
  },
  detailValue: {
    color: colors.slate900,
    fontSize: 12,
    fontWeight: '600',
    maxWidth: '60%',
    textAlign: 'right',
  },
  vitalsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  vital: {
    width: '31.5%',
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderRadius: radius.md,
    padding: 8,
  },
  vitalLabel: {
    color: '#cbd5e1',
    fontSize: 9,
    fontWeight: '700',
    letterSpacing: 1.4,
  },
  vitalValue: {
    marginTop: 4,
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },
  vitalTapHint: {
    marginTop: 4,
    color: 'rgba(165, 243, 252, 0.65)',
    fontSize: 9,
    fontWeight: '700',
    letterSpacing: 0.4,
  },
  trendHintText: {
    color: colors.cyan200,
    fontSize: 11,
    fontWeight: '600',
    marginBottom: 8,
    fontStyle: 'italic',
  },
  emptyText: {
    color: colors.muted,
    fontSize: 12,
  },
  infusionRow: {
    marginTop: 8,
    backgroundColor: colors.slate50,
    borderRadius: radius.md,
    padding: 10,
  },
  infusionTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  medName: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.slate900,
  },
  medMeta: {
    marginTop: 2,
    fontSize: 11,
    color: colors.muted,
  },
  infusionGrid: {
    flexDirection: 'row',
    marginTop: 8,
    gap: 8,
  },
  infusionCell: {
    flex: 1,
  },
  infusionCellLabel: {
    fontSize: 9,
    fontWeight: '700',
    letterSpacing: 1.2,
    color: colors.muted,
  },
  infusionCellValue: {
    marginTop: 2,
    fontSize: 12,
    fontWeight: '700',
    color: colors.slate900,
  },
});
