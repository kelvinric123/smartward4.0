// Medications tab: the active orders, most urgent first, with where each dose
// stands and what the nurses recorded; stopped and completed ones below.

import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import { Badge, Empty, Section } from './parts';

const STATE_TONES = {
  overdue: 'critical',
  due_soon: 'warning',
  scheduled: 'muted',
  prn: 'info',
};

const DOSE_TONES = {
  given: 'good',
  held: 'warning',
  refused: 'critical',
};

function Doses({ doses }) {
  if (!doses?.length) return <Text style={styles.meta}>No doses recorded yet.</Text>;
  return doses.map((dose, i) => (
    <View key={`${dose.time_label}-${i}`} style={styles.dose}>
      <Badge label={dose.status_label.toUpperCase()} toneName={DOSE_TONES[dose.status] ?? 'muted'} />
      <View style={{ flex: 1 }}>
        <Text style={styles.doseText}>
          {dose.time_label}{dose.by ? ` · ${dose.by}` : ''}
        </Text>
        {dose.notes ? <Text style={styles.doseNotes}>{dose.notes}</Text> : null}
      </View>
    </View>
  ));
}

export default function MedsTab({ medications }) {
  const [showStopped, setShowStopped] = useState(false);
  const { active, inactive, counts } = medications;

  return (
    <View>
      <View style={styles.countRow}>
        <View style={styles.count}>
          <Text style={styles.countLabel}>ACTIVE</Text>
          <Text style={styles.countValue}>{counts.active}</Text>
        </View>
        <View style={[styles.count, counts.overdue > 0 && { borderColor: colors.rose100, backgroundColor: colors.rose50 }]}>
          <Text style={styles.countLabel}>OVERDUE</Text>
          <Text style={[styles.countValue, counts.overdue > 0 && { color: colors.rose700 }]}>{counts.overdue}</Text>
        </View>
        <View style={[styles.count, counts.due_soon > 0 && { borderColor: colors.amber100, backgroundColor: colors.amber50 }]}>
          <Text style={styles.countLabel}>DUE SOON</Text>
          <Text style={[styles.countValue, counts.due_soon > 0 && { color: colors.amber700 }]}>{counts.due_soon}</Text>
        </View>
      </View>

      {active.length === 0 ? (
        <Section>
          <Empty>No active medication orders.</Empty>
        </Section>
      ) : (
        active.map((med) => (
          <Section key={med.id}>
            <View style={styles.medHead}>
              <View style={{ flex: 1, paddingRight: 8 }}>
                <Text style={styles.medName}>{med.name}</Text>
                <Text style={styles.medLine}>{med.dose} · {med.route}</Text>
                <Text style={styles.medLine}>{med.frequency}</Text>
              </View>
              <View style={{ alignItems: 'flex-end', gap: 4 }}>
                <Badge label={med.due_label} toneName={STATE_TONES[med.state] ?? 'muted'} solid={med.state === 'overdue'} />
                {med.high_alert ? <Badge label="HIGH-ALERT" toneName="critical" /> : null}
              </View>
            </View>
            {med.instructions ? <Text style={styles.instructions}>{med.instructions}</Text> : null}
            {med.io_volume_ml ? (
              <Text style={styles.ioNote}>Each dose given adds {med.io_volume_ml} mL to the I/O chart</Text>
            ) : null}
            <Text style={styles.dosesTitle}>
              RECENT DOSES{med.last_given_label ? `  ·  last given ${med.last_given_label}` : ''}
            </Text>
            <Doses doses={med.doses} />
          </Section>
        ))
      )}

      {inactive.length ? (
        <TouchableOpacity onPress={() => setShowStopped((s) => !s)} activeOpacity={0.8} style={styles.toggle}>
          <Text style={styles.toggleText}>
            {showStopped ? 'Hide' : 'Show'} stopped and completed ({inactive.length})
          </Text>
        </TouchableOpacity>
      ) : null}

      {showStopped
        ? inactive.map((med) => (
            <Section key={med.id} style={{ backgroundColor: colors.slate50 }}>
              <View style={styles.medHead}>
                <View style={{ flex: 1, paddingRight: 8 }}>
                  <Text style={[styles.medName, { color: colors.slate600 }]}>{med.name}</Text>
                  <Text style={styles.medLine}>{med.summary}</Text>
                </View>
                <Badge label={med.status_label.toUpperCase()} toneName="muted" />
              </View>
              <Text style={styles.meta}>
                {med.status_label} {med.closed_label}{med.stop_reason ? ` · ${med.stop_reason}` : ''}
              </Text>
            </Section>
          ))
        : null}
    </View>
  );
}

const styles = StyleSheet.create({
  countRow: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  count: {
    flex: 1,
    backgroundColor: colors.card,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingVertical: 10,
    paddingHorizontal: 10,
  },
  countLabel: { fontSize: 9, fontWeight: '800', letterSpacing: 1.4, color: colors.mutedSoft },
  countValue: { marginTop: 4, fontSize: 18, fontWeight: '800', color: colors.slate900 },
  medHead: { flexDirection: 'row', alignItems: 'flex-start' },
  medName: { fontSize: 15, fontWeight: '800', color: colors.slate900 },
  medLine: { marginTop: 2, fontSize: 12, color: colors.slate700 },
  instructions: { marginTop: 6, fontSize: 12, color: colors.slate600, fontStyle: 'italic' },
  ioNote: { marginTop: 6, fontSize: 12, fontWeight: '600', color: colors.blue700 },
  dosesTitle: { marginTop: 10, marginBottom: 4, fontSize: 9, fontWeight: '800', letterSpacing: 1.4, color: colors.mutedSoft },
  dose: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, paddingVertical: 4 },
  doseText: { fontSize: 12, color: colors.slate800 },
  doseNotes: { fontSize: 11, color: colors.slate600 },
  meta: { marginTop: 4, fontSize: 11, color: colors.muted },
  toggle: { alignSelf: 'center', paddingVertical: 10, paddingHorizontal: 14, marginBottom: 8 },
  toggleText: { fontSize: 13, fontWeight: '700', color: colors.blue700 },
});
