// Medication monitoring: what is due, overdue or PRN, and recording a dose
// as given, held or refused - the ward dashboard's Medications tab.

import React, { useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity } from 'react-native';
import { colors } from '../../theme';
import Sheet from '../Sheet';
import { Button, Card, Chip, EmptyNote, SectionTitle, Tag, TimeOffsetPicker, shared, tone } from '../ui';

const DUE_TONE = { overdue: 'critical', due_soon: 'warning', scheduled: 'info', prn: 'muted' };
const DOSE_TONE = { given: 'good', held: 'warning', refused: 'critical' };
const REASONS = {
  held: ['Patient asleep', 'Nil by mouth', 'Withheld per doctor', 'BP / pulse too low', 'Out of the ward'],
  refused: ['Patient refused', 'Nausea / vomiting', 'Unable to swallow'],
};

export default function MedsTab({ chart, perform }) {
  const meds = chart.medications;
  const [dosing, setDosing] = useState(null);
  const [showClosed, setShowClosed] = useState(false);

  return (
    <View>
      <SectionTitle
        eyebrow="MEDICATION MONITORING"
        title={`Active (${meds.active.length})`}
        right={
          meds.counts.overdue ? (
            <Tag label={`${meds.counts.overdue} OVERDUE`} toneName="critical" solid />
          ) : meds.counts.due_soon ? (
            <Tag label={`${meds.counts.due_soon} DUE SOON`} toneName="warning" />
          ) : null
        }
      />

      {meds.active.length === 0 ? (
        <Card>
          <EmptyNote text="No active medication orders. Orders are charted on the ward dashboard." />
        </Card>
      ) : (
        meds.active.map((med) => {
          const t = tone(DUE_TONE[med.due_state] ?? 'info');
          return (
            <Card key={med.id} style={med.due_state === 'overdue' && { borderColor: colors.rose100 }}>
              <View style={styles.head}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.name}>{med.name}</Text>
                  <Text style={styles.summary}>
                    {med.summary} · {med.frequency_label}
                  </Text>
                </View>
                {med.is_high_alert ? <Tag label="HIGH ALERT" toneName="critical" solid /> : null}
              </View>

              <View style={[styles.due, { backgroundColor: t.bg, borderColor: t.border }]}>
                <Text style={[styles.dueText, { color: t.text }]}>{med.due_label}</Text>
                {med.next_allowed_label ? (
                  <Text style={[shared.meta, { color: t.text }]}>Not before {med.next_allowed_label}</Text>
                ) : null}
              </View>

              {med.instructions ? <Text style={styles.instructions}>{med.instructions}</Text> : null}

              <View style={styles.lastRow}>
                <Text style={shared.meta}>
                  {med.last_given_label ? `Last given ${med.last_given_label}` : 'Not given yet'}
                </Text>
                <Button label="Record dose" small onPress={() => setDosing(med)} />
              </View>

              {med.recent_doses.length ? (
                <View style={styles.doses}>
                  {med.recent_doses.slice(0, 3).map((dose, i) => (
                    <View key={i} style={styles.doseRow}>
                      <Tag label={dose.status_label.toUpperCase()} toneName={DOSE_TONE[dose.status] ?? 'default'} />
                      <Text style={styles.doseText} numberOfLines={2}>
                        {dose.time_label}
                        {dose.by ? ` · ${dose.by}` : ''}
                        {dose.notes ? ` · ${dose.notes}` : ''}
                      </Text>
                    </View>
                  ))}
                </View>
              ) : null}
            </Card>
          );
        })
      )}

      {meds.closed.length > 0 ? (
        <>
          <TouchableOpacity activeOpacity={0.7} onPress={() => setShowClosed((v) => !v)}>
            <SectionTitle
              eyebrow="HISTORY"
              title={`Stopped or completed (${meds.closed.length})`}
              right={<Text style={styles.toggle}>{showClosed ? 'Hide' : 'Show'}</Text>}
            />
          </TouchableOpacity>
          {showClosed
            ? meds.closed.map((med) => (
                <Card key={med.id}>
                  <Text style={[styles.name, { color: colors.slate600 }]}>{med.name}</Text>
                  <Text style={styles.summary}>{med.summary}</Text>
                  <Text style={shared.meta}>
                    {med.status === 'completed' ? 'Completed' : `Stopped ${med.stopped_label ?? ''}`}
                    {med.stop_reason ? ` · ${med.stop_reason}` : ''}
                  </Text>
                </Card>
              ))
            : null}
        </>
      ) : null}

      <DoseSheet med={dosing} statuses={meds.statuses} onClose={() => setDosing(null)} perform={perform} />
    </View>
  );
}

function DoseSheet({ med, statuses, onClose, perform }) {
  const [status, setStatus] = useState('given');
  const [notes, setNotes] = useState('');
  const [minutesAgo, setMinutesAgo] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  function close() {
    setStatus('given');
    setNotes('');
    setMinutesAgo(0);
    setError(null);
    onClose();
  }

  async function save() {
    if (status !== 'given' && !notes.trim()) {
      setError('Give a reason when a dose is held or refused.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.recordDose(med.id, { status, notes: notes.trim() || null, minutes_ago: minutesAgo || null })
    );
    setBusy(false);
    if (result.ok) close();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={!!med}
      busy={busy}
      onClose={close}
      eyebrow="RECORD DOSE"
      title={med?.name}
      subtitle={med ? `${med.summary} · ${med.route_label} · ${med.due_label}` : null}
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button
            label={status === 'given' ? 'Dose given' : status === 'held' ? 'Dose held' : 'Dose refused'}
            kind={status === 'given' ? 'success' : 'dark'}
            flex
            busy={busy}
            onPress={save}
          />
        </>
      }
    >
      <Text style={shared.label}>DOSE</Text>
      <View style={shared.chipWrap}>
        {Object.entries(statuses ?? { given: 'Given', held: 'Held', refused: 'Refused' }).map(([key, label]) => (
          <Chip
            key={key}
            label={label}
            selected={status === key}
            toneName={DOSE_TONE[key] ?? 'info'}
            onPress={() => { setStatus(key); setNotes(''); }}
          />
        ))}
      </View>

      <Text style={shared.label}>WHEN</Text>
      <TimeOffsetPicker value={minutesAgo} onChange={setMinutesAgo} />

      <Text style={shared.label}>{status === 'given' ? 'NOTE (OPTIONAL)' : 'REASON (REQUIRED)'}</Text>
      {REASONS[status] ? (
        <View style={[shared.chipWrap, { marginBottom: 8 }]}>
          {REASONS[status].map((r) => (
            <Chip key={r} small label={r} selected={notes === r} onPress={() => setNotes(r)} toneName="warning" />
          ))}
        </View>
      ) : null}
      <TextInput
        style={shared.input}
        value={notes}
        onChangeText={setNotes}
        maxLength={255}
        placeholder={status === 'given' ? 'Anything to note' : 'Why was it not given?'}
        placeholderTextColor={colors.mutedSoft}
      />
      {med?.is_high_alert ? (
        <Text style={[shared.hint, { color: colors.rose700, fontWeight: '700' }]}>
          High-alert medicine: follow the ward's double-check procedure.
        </Text>
      ) : null}
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  name: { color: colors.slate900, fontSize: 15, fontWeight: '800' },
  summary: { marginTop: 2, color: colors.slate600, fontSize: 12, fontWeight: '600' },
  due: { marginTop: 10, borderWidth: 1, borderRadius: 10, paddingHorizontal: 10, paddingVertical: 7 },
  dueText: { fontSize: 13, fontWeight: '800' },
  instructions: { marginTop: 8, color: colors.slate600, fontSize: 12, fontStyle: 'italic' },
  lastRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 10, gap: 8 },
  doses: { marginTop: 10, borderTopWidth: 1, borderTopColor: colors.slate100, paddingTop: 8, gap: 6 },
  doseRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  doseText: { flex: 1, color: colors.slate600, fontSize: 11 },
  toggle: { color: colors.cyan700, fontSize: 12, fontWeight: '800' },
});
