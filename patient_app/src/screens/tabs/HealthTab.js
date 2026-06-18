import React from 'react';
import { View, Text, StyleSheet, ScrollView } from 'react-native';
import { colors, radius, shadow } from '../../theme';
import SectionCard from '../../components/SectionCard';
import Pill from '../../components/Pill';

const TONE = {
  normal: { bg: colors.emerald50, border: colors.emerald100, text: colors.emerald700, label: 'Normal' },
  mild: { bg: colors.amber50, border: colors.amber100, text: colors.amber700, label: 'Watch' },
  high: { bg: colors.rose50, border: colors.rose100, text: colors.rose700, label: 'High' },
};

function VitalCard({ icon, name, value, unit, status, range }) {
  const t = TONE[status] ?? TONE.normal;
  return (
    <View style={[styles.vitalCard, { backgroundColor: t.bg, borderColor: t.border }]}>
      <View style={styles.vitalHeader}>
        <Text style={styles.vitalIcon}>{icon}</Text>
        <Pill label={t.label.toUpperCase()} bg="#fff" color={t.text} />
      </View>
      <Text style={[styles.vitalName, { color: t.text }]}>{name}</Text>
      <View style={styles.vitalValueRow}>
        <Text style={styles.vitalValue}>{value}</Text>
        <Text style={styles.vitalUnit}>{unit}</Text>
      </View>
      <Text style={styles.vitalRange}>Target {range}</Text>
    </View>
  );
}

function MedRow({ med }) {
  return (
    <View style={styles.medRow}>
      <View style={styles.medBubble}>
        <Text style={styles.medEmoji}>{med.icon === 'puffer' ? '🫁' : '💊'}</Text>
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.medName}>{med.name}</Text>
        <Text style={styles.medPlain}>{med.plain_name} — {med.purpose}</Text>
        <View style={styles.medMeta}>
          <Pill label={med.schedule} bg={colors.slate100} color={colors.slate700} />
          <Pill label={med.next_dose} bg={colors.brand50} color={colors.brand700} />
        </View>
      </View>
    </View>
  );
}

export default function HealthTab({ patient, vitals, medications, recoveryOverview }) {
  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <Text style={styles.headerEyebrow}>HEALTH SNAPSHOT</Text>
        <Text style={styles.headerTitle}>Your vitals & meds</Text>
        <Text style={styles.headerHint}>Live readings from the bedside monitor · {vitals.recorded_at_label}</Text>
      </View>

      <View style={styles.profileCard}>
        <View style={styles.profileRow}>
          <View style={styles.profileItem}>
            <Text style={styles.profileLabel}>DIAGNOSIS</Text>
            <Text style={styles.profileValue}>{patient.primary_diagnosis}</Text>
          </View>
          <View style={styles.profileItem}>
            <Text style={styles.profileLabel}>BLOOD</Text>
            <Text style={styles.profileValue}>{patient.blood_type}</Text>
          </View>
        </View>
        <View style={[styles.profileRow, { marginTop: 8 }]}>
          <View style={styles.profileItem}>
            <Text style={styles.profileLabel}>ALLERGIES</Text>
            <View style={{ flexDirection: 'row', gap: 6, marginTop: 4, flexWrap: 'wrap' }}>
              {patient.allergies.map((a) => (
                <Pill key={a} label={a} bg={colors.rose50} color={colors.rose700} />
              ))}
            </View>
          </View>
        </View>
      </View>

      <Text style={styles.sectionLabel}>LIVE VITALS</Text>
      <View style={styles.vitalsGrid}>
        <VitalCard
          icon="❤️"
          name="Heart rate"
          value={vitals.pulse_rate.value}
          unit={vitals.pulse_rate.unit}
          status={vitals.pulse_rate.status}
          range={vitals.pulse_rate.range}
        />
        <VitalCard
          icon="🩸"
          name="Blood pressure"
          value={vitals.blood_pressure.value}
          unit={vitals.blood_pressure.unit}
          status={vitals.blood_pressure.status}
          range={vitals.blood_pressure.range}
        />
        <VitalCard
          icon="🫁"
          name="Oxygen"
          value={vitals.spo2.value}
          unit={vitals.spo2.unit}
          status={vitals.spo2.status}
          range={vitals.spo2.range}
        />
        <VitalCard
          icon="💨"
          name="Breathing"
          value={vitals.respiratory_rate.value}
          unit={vitals.respiratory_rate.unit}
          status={vitals.respiratory_rate.status}
          range={vitals.respiratory_rate.range}
        />
        <VitalCard
          icon="🌡️"
          name="Temperature"
          value={vitals.temperature.value}
          unit={vitals.temperature.unit}
          status={vitals.temperature.status}
          range={vitals.temperature.range}
        />
        <VitalCard
          icon="🩹"
          name="Pain level"
          value={vitals.pain_score.value}
          unit={vitals.pain_score.unit}
          status={vitals.pain_score.status}
          range={vitals.pain_score.range}
        />
      </View>

      <SectionCard
        eyebrow="MEDICATIONS"
        title="What you’re taking today"
        style={{ marginTop: 16 }}
      >
        <View style={{ gap: 12 }}>
          {medications.map((m) => (
            <MedRow key={m.id} med={m} />
          ))}
        </View>
      </SectionCard>

      <SectionCard
        eyebrow="DAILY OVERVIEW"
        title="Recovery progress"
        style={{ marginTop: 14 }}
      >
        <View style={styles.recoveryRow}>
          <View style={styles.recoveryBox}>
            <Text style={styles.recoveryLabel}>DAY OF STAY</Text>
            <Text style={styles.recoveryValue}>{recoveryOverview.day_of_stay}</Text>
          </View>
          <View style={styles.recoveryBox}>
            <Text style={styles.recoveryLabel}>EXPECTED HOME</Text>
            <Text style={[styles.recoveryValue, { fontSize: 14 }]}>
              {recoveryOverview.expected_discharge}
            </Text>
          </View>
        </View>
        <Text style={styles.recoveryNote}>{recoveryOverview.progress_note}</Text>
      </SectionCard>

      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 14, paddingBottom: 120 },

  header: { marginBottom: 14 },
  headerEyebrow: {
    color: colors.muted,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  headerTitle: {
    marginTop: 2,
    fontSize: 22,
    fontWeight: '800',
    color: colors.slate900,
  },
  headerHint: {
    marginTop: 4,
    color: colors.muted,
    fontSize: 12,
  },

  profileCard: {
    backgroundColor: '#fff',
    padding: 14,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    ...shadow.sm,
  },
  profileRow: { flexDirection: 'row', gap: 12 },
  profileItem: { flex: 1 },
  profileLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
    color: colors.muted,
  },
  profileValue: {
    marginTop: 4,
    fontSize: 13,
    fontWeight: '700',
    color: colors.slate900,
  },

  sectionLabel: {
    marginTop: 18,
    marginBottom: 10,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
    color: colors.muted,
  },
  vitalsGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  vitalCard: {
    width: '48.5%',
    borderRadius: radius.lg,
    padding: 12,
    borderWidth: 1,
    ...shadow.sm,
  },
  vitalHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  vitalIcon: {
    fontSize: 20,
  },
  vitalName: {
    marginTop: 6,
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 1,
    textTransform: 'uppercase',
  },
  vitalValueRow: {
    marginTop: 2,
    flexDirection: 'row',
    alignItems: 'flex-end',
    gap: 4,
  },
  vitalValue: {
    color: colors.slate900,
    fontSize: 26,
    fontWeight: '800',
    lineHeight: 30,
  },
  vitalUnit: {
    color: colors.muted,
    fontSize: 11,
    fontWeight: '700',
    marginBottom: 4,
  },
  vitalRange: {
    marginTop: 4,
    fontSize: 11,
    color: colors.muted,
  },

  medRow: {
    flexDirection: 'row',
    gap: 12,
  },
  medBubble: {
    width: 44,
    height: 44,
    borderRadius: 999,
    backgroundColor: colors.brand50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  medEmoji: { fontSize: 22 },
  medName: {
    fontSize: 14,
    fontWeight: '800',
    color: colors.slate900,
  },
  medPlain: {
    marginTop: 2,
    fontSize: 12,
    color: colors.slate600,
    lineHeight: 18,
  },
  medMeta: {
    marginTop: 6,
    flexDirection: 'row',
    gap: 6,
    flexWrap: 'wrap',
  },

  recoveryRow: { flexDirection: 'row', gap: 10 },
  recoveryBox: {
    flex: 1,
    padding: 12,
    backgroundColor: colors.slate50,
    borderRadius: radius.md,
  },
  recoveryLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
    color: colors.muted,
  },
  recoveryValue: {
    marginTop: 4,
    fontSize: 22,
    fontWeight: '800',
    color: colors.slate900,
  },
  recoveryNote: {
    marginTop: 12,
    color: colors.slate700,
    fontSize: 12,
    lineHeight: 18,
  },
});
