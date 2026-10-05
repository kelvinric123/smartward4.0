// Oxygen therapy: what the patient is on now and since when, the SpO2 target
// and the latest SpO2 against it, and every change this admission - the ward
// dashboard's Oxygen Therapy tab. Change the oxygen (or to room air) or strike
// out a change made in error.

import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TextInput } from 'react-native';
import { colors, radius } from '../../theme';
import Sheet from '../Sheet';
import { Banner, Button, Card, Chip, EmptyNote, SectionTitle, Tag, TimeOffsetPicker, shared } from '../ui';

const SPO2_TONE = { below: 'critical', above: 'warning', in: 'good' };
const SPO2_TEXT = { below: 'below the target', above: 'above the target', in: 'within the target' };
const VOID_REASONS = ['Recorded on the wrong patient', 'Wrong device or setting', 'Duplicate entry'];

export default function OxygenTab({ chart, perform }) {
  const ox = chart.oxygen;
  const now = ox.current;
  const spo2 = ox.latest_spo2;
  const [changing, setChanging] = useState(false);
  const [voiding, setVoiding] = useState(null);

  return (
    <View>
      <View style={styles.hero}>
        <View style={styles.heroHead}>
          <Text style={styles.heroEyebrow}>OXYGEN NOW</Text>
          {ox.target ? <Tag label={`TARGET ${ox.target.label}`} toneName="info" solid /> : null}
        </View>
        {now ? (
          <>
            <Text style={styles.heroValue}>
              {now.label}
              {now.settings ? ` · ${now.settings}` : ''}
            </Text>
            <Text style={styles.heroMeta}>
              Since {now.since_label} ({now.duration_label})
              {now.source === 'vitals' ? ' · with vital signs' : ''}
              {now.by ? ` · ${now.by}` : ''}
            </Text>
            {ox.on_oxygen_since_label && now.on_oxygen ? (
              <Text style={styles.heroMeta}>On oxygen since {ox.on_oxygen_since_label} ({ox.on_oxygen_duration_label})</Text>
            ) : null}
            {ox.target?.note ? <Text style={styles.heroMeta}>Target: {ox.target.note}</Text> : null}
          </>
        ) : (
          <Text style={styles.heroMeta}>No oxygen recorded this admission.</Text>
        )}
        {spo2 ? (
          <View style={styles.spo2Row}>
            <Text style={styles.spo2Value}>SpO₂ {spo2.value}%</Text>
            <Text style={styles.heroMeta}>
              {spo2.time_label}
              {spo2.on ? ` on ${spo2.on}` : ''}
              {spo2.stale ? ' · taken before the last change' : ''}
            </Text>
          </View>
        ) : null}
      </View>

      {spo2?.state && spo2.state !== 'in' ? (
        <Banner
          toneName={SPO2_TONE[spo2.state]}
          title={`SpO₂ ${spo2.value}% is ${SPO2_TEXT[spo2.state]} ${ox.target?.label ?? ''}`}
          text={spo2.state === 'below' ? 'Check the patient and the oxygen, and escalate as the ward protocol says.' : 'Above the target on supplemental oxygen: consider weaning, as prescribed.'}
        />
      ) : null}

      <Button label={now?.on_oxygen ? 'Change oxygen' : 'Start or record oxygen'} onPress={() => setChanging(true)} style={{ marginBottom: 12 }} />

      <SectionTitle eyebrow="THIS ADMISSION" title={`Oxygen history (${ox.history.length})`} />
      {ox.history.length === 0 ? (
        <Card>
          <EmptyNote text="Nothing recorded yet. Oxygen recorded with the vital signs shows here too." />
        </Card>
      ) : (
        <Card>
          {ox.history.map((h) => (
            <View key={h.key} style={[styles.historyRow, h.voided && { opacity: 0.55 }]}>
              <Text style={styles.historyTime}>{h.time_label}</Text>
              <View style={{ flex: 1 }}>
                <Text style={[styles.historyLabel, h.voided && styles.struck]}>
                  {h.label}
                  {h.settings ? ` · ${h.settings}` : ''}
                </Text>
                <Text style={shared.meta}>
                  {[h.target_label ? `Target ${h.target_label}` : null, h.duration_label, h.source === 'vitals' ? 'with vital signs' : null, h.by]
                    .filter(Boolean)
                    .join(' · ')}
                </Text>
                {h.notes ? <Text style={shared.meta}>{h.notes}</Text> : null}
                {h.voided ? <Text style={[shared.meta, { color: colors.rose700 }]}>Struck out: {h.void_reason}</Text> : null}
              </View>
              {h.current ? <Tag label="NOW" toneName="good" /> : null}
              {h.change_id && !h.voided ? (
                <Button label="Strike out" kind="outline" small onPress={() => setVoiding(h)} />
              ) : null}
            </View>
          ))}
        </Card>
      )}

      <ChangeSheet visible={changing} ox={ox} onClose={() => setChanging(false)} perform={perform} />
      <VoidSheet entry={voiding} onClose={() => setVoiding(null)} perform={perform} />
    </View>
  );
}

function ChangeSheet({ visible, ox, onClose, perform }) {
  const options = ox.options;
  const currentTarget = ox.target ? [ox.target.min, ox.target.max] : null;
  const [device, setDevice] = useState(null);
  const [flow, setFlow] = useState('');
  const [fio2, setFio2] = useState('');
  const [target, setTarget] = useState(currentTarget);
  const [notes, setNotes] = useState('');
  const [minutesAgo, setMinutesAgo] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  // Each time it opens: the device and target in force now, a fresh setting
  useEffect(() => {
    if (!visible) return;
    setDevice(ox.current?.device ?? null);
    setFlow('');
    setFio2('');
    setTarget(currentTarget);
    setNotes('');
    setMinutesAgo(0);
    setError(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visible]);

  const d = options.devices.find((x) => x.key === device);
  const targets = [...options.targets.map((t) => [t.min, t.max, t.label])];
  if (currentTarget && !targets.some(([a, b]) => a === currentTarget[0] && b === currentTarget[1])) {
    targets.push([currentTarget[0], currentTarget[1], 'Current']);
  }

  function close() {
    setError(null);
    onClose();
  }

  async function save() {
    if (!device) {
      setError('Choose how the oxygen is given, or Room Air.');
      return;
    }
    if (!d.room_air && !flow && !fio2) {
      setError(`Enter the flow rate or the FiO₂ for ${d.label}.`);
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.changeOxygen({
        oxygen_delivery: device,
        oxygen_flow_rate: d.room_air || !flow ? null : Number(flow),
        fio2_percent: d.room_air || !fio2 ? null : Number(fio2),
        target_spo2_min: target ? target[0] : null,
        target_spo2_max: target ? target[1] : null,
        notes: notes.trim() || null,
        minutes_ago: minutesAgo || null,
      })
    );
    setBusy(false);
    if (result.ok) close();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={visible}
      busy={busy}
      onClose={close}
      eyebrow="OXYGEN THERAPY"
      title="Change oxygen"
      subtitle={ox.current ? `Now: ${ox.current.label}${ox.current.settings ? ` · ${ox.current.settings}` : ''}` : 'No oxygen recorded yet this admission.'}
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label={d?.room_air ? 'Change to room air' : 'Save change'} flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>DEVICE</Text>
      <View style={shared.chipWrap}>
        {options.devices.map((x) => (
          <Chip key={x.key} small label={x.label} selected={device === x.key} toneName={x.room_air ? 'good' : 'info'} onPress={() => { setDevice(x.key); setFlow(''); setFio2(''); setError(null); }} />
        ))}
      </View>
      {d?.hint ? <Text style={shared.hint}>{d.hint}</Text> : null}

      {d && !d.room_air ? (
        <>
          <Text style={shared.label}>FLOW RATE (L/MIN)</Text>
          {d.flow.length ? (
            <View style={[shared.chipWrap, { marginBottom: 6 }]}>
              {d.flow.map((v) => (
                <Chip key={v} small label={`${v}`} selected={flow === String(v)} onPress={() => setFlow(String(v))} />
              ))}
            </View>
          ) : null}
          <TextInput
            style={shared.input}
            value={flow}
            onChangeText={(t) => setFlow(t.replace(/[^0-9.]/g, ''))}
            keyboardType="decimal-pad"
            placeholder={`${options.flow_min} to ${options.flow_max}`}
            placeholderTextColor={colors.mutedSoft}
          />

          <Text style={shared.label}>FIO₂ (%)</Text>
          {d.fio2.length ? (
            <View style={[shared.chipWrap, { marginBottom: 6 }]}>
              {d.fio2.map((v) => (
                <Chip key={v} small label={`${v}%`} selected={fio2 === String(v)} onPress={() => setFio2(String(v))} />
              ))}
            </View>
          ) : null}
          <TextInput
            style={shared.input}
            value={fio2}
            onChangeText={(t) => setFio2(t.replace(/[^0-9]/g, ''))}
            keyboardType="number-pad"
            placeholder={`${options.fio2_min} to ${options.fio2_max}`}
            placeholderTextColor={colors.mutedSoft}
          />
        </>
      ) : null}

      <Text style={shared.label}>SPO₂ TARGET</Text>
      <View style={shared.chipWrap}>
        {targets.map(([min, max, label]) => (
          <Chip
            key={`${min}-${max}`}
            small
            label={`${min}–${max}% · ${label}`}
            selected={!!target && target[0] === min && target[1] === max}
            onPress={() => setTarget([min, max])}
          />
        ))}
        <Chip small label="No target" selected={!target} toneName="muted" onPress={() => setTarget(null)} />
      </View>

      <Text style={shared.label}>WHEN</Text>
      <TimeOffsetPicker value={minutesAgo} onChange={setMinutesAgo} />

      <Text style={shared.label}>NOTE (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={notes}
        onChangeText={setNotes}
        maxLength={255}
        placeholder="e.g. Weaned as SpO₂ stable, per Dr. Lim"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function VoidSheet({ entry, onClose, perform }) {
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  function close() {
    setReason('');
    setError(null);
    onClose();
  }

  async function save() {
    if (!reason.trim()) {
      setError('Give a reason for striking out this change.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) => c.voidOxygen(entry.change_id, { void_reason: reason.trim() }));
    setBusy(false);
    if (result.ok) close();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={!!entry}
      busy={busy}
      onClose={close}
      eyebrow="STRIKE OUT"
      title={entry ? `${entry.label}${entry.settings ? ` · ${entry.settings}` : ''}` : ''}
      subtitle="It stays in the history, crossed through with the reason, and the oxygen goes back to what was in force before it."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label="Strike out" kind="danger" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>REASON</Text>
      <View style={[shared.chipWrap, { marginBottom: 8 }]}>
        {VOID_REASONS.map((r) => (
          <Chip key={r} small label={r} selected={reason === r} toneName="warning" onPress={() => setReason(r)} />
        ))}
      </View>
      <TextInput
        style={shared.input}
        value={reason}
        onChangeText={setReason}
        maxLength={255}
        placeholder="Why is this change wrong?"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  hero: { backgroundColor: colors.slate950, borderRadius: radius.lg, padding: 12, marginBottom: 12 },
  heroHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 },
  heroEyebrow: { color: colors.cyan100, fontSize: 10, fontWeight: '800', letterSpacing: 2 },
  heroValue: { color: '#fff', fontSize: 16, fontWeight: '800' },
  heroMeta: { marginTop: 3, color: '#cbd5e1', fontSize: 11 },
  spo2Row: { marginTop: 10, borderTopWidth: 1, borderTopColor: 'rgba(255,255,255,0.1)', paddingTop: 8 },
  spo2Value: { color: '#fff', fontSize: 15, fontWeight: '800' },
  historyRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, paddingVertical: 7, borderBottomWidth: 1, borderBottomColor: colors.slate100 },
  historyTime: { width: 74, color: colors.slate500, fontSize: 11, fontWeight: '700' },
  historyLabel: { color: colors.slate900, fontSize: 13, fontWeight: '700' },
  struck: { textDecorationLine: 'line-through' },
});
