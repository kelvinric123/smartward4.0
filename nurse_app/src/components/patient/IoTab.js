// I/O chart: one chart day of intake and output, the fluid plan, overload
// checks - the ward dashboard's I/O Chart tab. Entries are grouped by shift;
// tap one to strike it out (it stays on the chart, no longer counted).

import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import Sheet from '../Sheet';
import {
  Banner,
  Button,
  Card,
  Chip,
  EmptyNote,
  Progress,
  Row,
  SectionTitle,
  Tag,
  TimeOffsetPicker,
  shared,
} from '../ui';

const LEVEL_TONE = { critical: 'critical', warning: 'warning', info: 'info' };

function balanceTone(ml) {
  if (ml > 0) return colors.cyan700;
  if (ml < 0) return colors.amber700;
  return colors.slate700;
}

export default function IoTab({ chart, perform, loadIoDay, loadingDay }) {
  const io = chart.io;
  const [sheet, setSheet] = useState(null); // { kind: 'entry', direction } | { kind: 'void', entry } | { kind: 'plan' } | { kind: 'assessment' }
  const limit = io.status.limit;
  const urine = io.status.urine;
  const entryCount = io.shifts.reduce((n, s) => n + s.entries.length, 0);

  return (
    <View>
      <View style={styles.dayNav}>
        <TouchableOpacity
          style={[styles.dayBtn, !io.day.previous && styles.dayBtnOff]}
          disabled={!io.day.previous || loadingDay}
          onPress={() => loadIoDay(io.day.previous)}
        >
          <Text style={styles.dayBtnText}>‹</Text>
        </TouchableOpacity>
        <View style={{ flex: 1, alignItems: 'center' }}>
          <Text style={styles.dayLabel}>
            {io.day.label}
            {io.day.is_current ? '  ·  today' : ''}
          </Text>
          <Text style={shared.meta}>{io.day.range}</Text>
        </View>
        <TouchableOpacity
          style={[styles.dayBtn, io.day.is_current && styles.dayBtnOff]}
          disabled={io.day.is_current || loadingDay}
          onPress={() => loadIoDay(io.day.next)}
        >
          <Text style={styles.dayBtnText}>›</Text>
        </TouchableOpacity>
      </View>

      {io.status.alerts.map((alert, i) => (
        <Banner key={i} toneName={LEVEL_TONE[alert.level] ?? 'info'} title={alert.title} text={alert.detail} />
      ))}

      <View style={styles.totals}>
        <Total label="INTAKE" value={io.totals.intake_label} color={colors.cyan700} />
        <Total label="OUTPUT" value={io.totals.output_label} color={colors.amber700} />
        <Total label="BALANCE" value={io.totals.balance_label} color={balanceTone(io.totals.balance)} />
        <Total label="URINE" value={io.totals.urine_label} color={colors.slate700} />
      </View>

      {limit ? (
        <Card>
          <View style={styles.limitHead}>
            <Text style={styles.limitTitle}>Intake {limit.taken} / {limit.limit} mL</Text>
            <Tag
              label={limit.state === 'over' ? `${limit.over_by} mL OVER` : `${limit.remaining} mL LEFT`}
              toneName={limit.state === 'over' ? 'critical' : limit.state === 'near' ? 'warning' : 'good'}
            />
          </View>
          <Progress percent={limit.percent} toneName={limit.state === 'over' ? 'critical' : limit.state === 'near' ? 'warning' : 'good'} />
          {urine ? (
            <Text style={[shared.meta, { marginTop: 8 }]}>
              Urine target {urine.min} mL/h ·{' '}
              {urine.average != null ? `averaging ${urine.average} mL/h over ${urine.hours} h` : 'not enough hours charted yet'}
            </Text>
          ) : null}
        </Card>
      ) : urine ? (
        <Card>
          <Text style={shared.meta}>
            Urine target {urine.min} mL/h ·{' '}
            {urine.average != null ? `averaging ${urine.average} mL/h over ${urine.hours} h` : 'not enough hours charted yet'}
          </Text>
        </Card>
      ) : null}

      <View style={styles.addRow}>
        <Button label="+ Intake" flex onPress={() => setSheet({ kind: 'entry', direction: 'intake' })} />
        <Button label="+ Output" kind="dark" flex onPress={() => setSheet({ kind: 'entry', direction: 'output' })} />
      </View>

      <SectionTitle eyebrow="CHART" title={`${entryCount} ${entryCount === 1 ? 'entry' : 'entries'}`} />
      {io.shifts.length === 0 || entryCount === 0 ? (
        <Card>
          <EmptyNote text="Nothing charted on this day yet." />
        </Card>
      ) : null}
      {io.shifts
        .filter((shift) => shift.entries.length > 0)
        .map((shift) => (
          <Card key={`${shift.code}-${shift.time}`} padded={false}>
            <View style={[styles.shiftHead, shift.current && styles.shiftHeadCurrent]}>
              <Text style={styles.shiftName}>
                {shift.name}
                {shift.time ? `  ${shift.time}` : ''}
              </Text>
              <Text style={styles.shiftTotals}>
                in {shift.intake_label} · out {shift.output_label} · {shift.balance_label}
              </Text>
            </View>
            {shift.entries.map((entry) => (
              <TouchableOpacity
                key={entry.id}
                activeOpacity={0.7}
                disabled={entry.voided}
                onPress={() => setSheet({ kind: 'void', entry })}
                style={styles.entry}
              >
                <Text style={styles.entryTime}>{entry.time_label}</Text>
                <View style={{ flex: 1 }}>
                  <Text style={[styles.entryLabel, entry.voided && styles.struck]} numberOfLines={2}>
                    {entry.label}
                  </Text>
                  {entry.voided ? (
                    <Text style={styles.voidNote}>
                      Struck out{entry.voided_by ? ` by ${entry.voided_by}` : ''}: {entry.void_reason}
                    </Text>
                  ) : entry.by || entry.auto_label ? (
                    <Text style={shared.meta}>
                      {/* Charted by SmartWard itself: a finished blood unit, a dose, a pump */}
                      {[entry.by, entry.auto_label ? `charted from the ${entry.auto_label}` : null].filter(Boolean).join(' · ')}
                    </Text>
                  ) : null}
                </View>
                <Text
                  style={[
                    styles.entryVolume,
                    { color: entry.direction === 'intake' ? colors.cyan700 : colors.amber700 },
                    entry.voided && styles.struck,
                  ]}
                >
                  {entry.direction === 'intake' ? '+' : '−'}
                  {entry.volume_label}
                </Text>
              </TouchableOpacity>
            ))}
          </Card>
        ))}
      {entryCount > 0 ? <Text style={[shared.meta, { marginBottom: 10 }]}>Tap an entry made in error to strike it out.</Text> : null}

      <Card
        title="FLUID PLAN"
        right={<Button label={io.plan ? 'Change' : 'Set plan'} kind="outline" small onPress={() => setSheet({ kind: 'plan' })} />}
      >
        {io.plan ? (
          <>
            <Text style={styles.planText}>{io.plan.summary}</Text>
            {io.plan.notes ? <Text style={shared.meta}>{io.plan.notes}</Text> : null}
            <Text style={[shared.meta, { marginTop: 4 }]}>Set {io.plan.set_label}</Text>
          </>
        ) : (
          <EmptyNote text="No intake limit or urine target set." />
        )}
      </Card>

      <Card
        title="FLUID OVERLOAD"
        right={<Button label="+ Check" kind="outline" small onPress={() => setSheet({ kind: 'assessment' })} />}
      >
        {io.latest_assessment ? (
          <>
            <Text
              style={[
                styles.planText,
                io.latest_assessment.urgent && { color: colors.rose700 },
                !io.latest_assessment.urgent && io.latest_assessment.concern && { color: colors.amber700 },
              ]}
            >
              {io.latest_assessment.edema}
              {io.latest_assessment.signs.length ? ` · ${io.latest_assessment.signs.join(', ')}` : ''}
            </Text>
            <Text style={shared.meta}>
              {io.latest_assessment.time_label}
              {io.latest_assessment.by ? ` · ${io.latest_assessment.by}` : ''}
            </Text>
          </>
        ) : (
          <EmptyNote text="No overload check recorded this stay." />
        )}
        {io.weight ? (
          <Row
            label="Weight"
            value={`${io.weight.kg} kg (${io.weight.time_label})${io.weight.change != null ? `, ${io.weight.change > 0 ? '+' : ''}${io.weight.change} kg` : ''}`}
            valueTone={io.weight.gain ? 'warning' : undefined}
          />
        ) : null}
      </Card>

      {io.days.length > 0 ? (
        <Card title="LAST DAYS">
          {io.days.map((day) => (
            <TouchableOpacity key={day.key} activeOpacity={0.7} onPress={() => loadIoDay(day.is_current ? null : day.key)}>
              <View style={styles.dayRow}>
                <Text style={[styles.dayRowLabel, day.key === io.day.key && { color: colors.cyan700 }]}>{day.label}</Text>
                <Text style={styles.dayRowValue}>
                  in {day.intake_label} · out {day.output_label}
                </Text>
                <Text style={[styles.dayRowBalance, { color: balanceTone(day.balance) }]}>{day.balance_label}</Text>
              </View>
            </TouchableOpacity>
          ))}
          {io.stay_balance_label ? <Row label="Whole stay so far" value={io.stay_balance_label} bold /> : null}
        </Card>
      ) : null}

      <EntrySheet sheet={sheet} io={io} onClose={() => setSheet(null)} perform={perform} />
      <VoidSheet sheet={sheet} onClose={() => setSheet(null)} perform={perform} />
      <PlanSheet visible={sheet?.kind === 'plan'} io={io} onClose={() => setSheet(null)} perform={perform} />
      <AssessmentSheet visible={sheet?.kind === 'assessment'} io={io} onClose={() => setSheet(null)} perform={perform} />
    </View>
  );
}

function Total({ label, value, color }) {
  return (
    <View style={styles.total}>
      <Text style={styles.totalLabel}>{label}</Text>
      <Text style={[styles.totalValue, { color }]} numberOfLines={1}>
        {value}
      </Text>
    </View>
  );
}

function EntrySheet({ sheet, io, onClose, perform }) {
  const visible = sheet?.kind === 'entry';
  const direction = sheet?.direction ?? 'intake';
  const types = direction === 'intake' ? io.options.intake_types : io.options.output_types;
  const [category, setCategory] = useState(null);
  const [volume, setVolume] = useState('');
  const [description, setDescription] = useState('');
  const [minutesAgo, setMinutesAgo] = useState(0);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const chosen = category ?? Object.keys(types)[0];
  const suggestions = io.options.suggestions?.[direction]?.[chosen] ?? [];

  function close() {
    setCategory(null);
    setVolume('');
    setDescription('');
    setMinutesAgo(0);
    setError(null);
    onClose();
  }

  async function save() {
    const ml = parseInt(volume, 10);
    if (!ml || ml < 1) {
      setError('Enter the volume in mL.');
      return;
    }
    if (ml > io.options.volume_max) {
      setError(`One entry can be at most ${io.options.volume_max} mL. Record larger volumes as separate entries.`);
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.addIoEntry({
        direction,
        category: chosen,
        volume_ml: ml,
        description: description.trim() || null,
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
      eyebrow="I/O CHART"
      title={direction === 'intake' ? 'Record intake' : 'Record output'}
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label="Save" kind={direction === 'intake' ? 'primary' : 'dark'} flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>{direction === 'intake' ? 'TAKEN IN AS' : 'PASSED AS'}</Text>
      <View style={shared.chipWrap}>
        {Object.entries(types).map(([key, label]) => (
          <Chip key={key} label={label} selected={chosen === key} onPress={() => { setCategory(key); setDescription(''); }} />
        ))}
      </View>

      <Text style={shared.label}>VOLUME (mL)</Text>
      <View style={shared.chipWrap}>
        {io.options.quick_volumes.map((v) => (
          <Chip key={v} small label={`${v}`} selected={String(v) === volume} onPress={() => setVolume(String(v))} />
        ))}
      </View>
      <TextInput
        style={[shared.input, { marginTop: 8 }]}
        value={volume}
        onChangeText={(t) => setVolume(t.replace(/[^0-9]/g, ''))}
        keyboardType="number-pad"
        placeholder="Or type the volume"
        placeholderTextColor={colors.mutedSoft}
        maxLength={5}
      />

      <Text style={shared.label}>WHAT (OPTIONAL)</Text>
      {suggestions.length ? (
        <View style={[shared.chipWrap, { marginBottom: 8 }]}>
          {suggestions.map((s) => (
            <Chip key={s} small label={s} selected={description === s} onPress={() => setDescription(description === s ? '' : s)} />
          ))}
        </View>
      ) : null}
      <TextInput
        style={shared.input}
        value={description}
        onChangeText={setDescription}
        maxLength={120}
        placeholder="e.g. Water"
        placeholderTextColor={colors.mutedSoft}
      />

      <Text style={shared.label}>WHEN</Text>
      <TimeOffsetPicker value={minutesAgo} onChange={setMinutesAgo} />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const VOID_REASONS = ['Wrong patient', 'Wrong volume', 'Entered twice', 'Wrong type'];

function VoidSheet({ sheet, onClose, perform }) {
  const visible = sheet?.kind === 'void';
  const entry = sheet?.entry;
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
      setError('Give a reason for striking out this entry.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) => c.voidIoEntry(entry.id, { void_reason: reason.trim() }));
    setBusy(false);
    if (result.ok) close();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={visible}
      busy={busy}
      onClose={close}
      eyebrow="I/O CHART"
      title="Strike out this entry?"
      subtitle={entry ? `${entry.time_label} · ${entry.label} · ${entry.volume_label}. It stays on the chart, crossed through, and stops counting.` : null}
      footer={
        <>
          <Button label="Keep it" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label="Strike out" kind="danger" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>REASON</Text>
      <View style={[shared.chipWrap, { marginBottom: 8 }]}>
        {VOID_REASONS.map((r) => (
          <Chip key={r} small label={r} selected={reason === r} onPress={() => setReason(r)} toneName="warning" />
        ))}
      </View>
      <TextInput
        style={shared.input}
        value={reason}
        onChangeText={setReason}
        maxLength={255}
        placeholder="Why is it wrong?"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function PlanSheet({ visible, io, onClose, perform }) {
  const [limit, setLimit] = useState('');
  const [urine, setUrine] = useState('');
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const o = io.options;

  // Start from the plan in force each time the sheet opens
  useEffect(() => {
    if (visible) {
      setLimit(io.plan?.intake_limit_ml ? String(io.plan.intake_limit_ml) : '');
      setUrine(io.plan?.urine_min_ml_per_hour ? String(io.plan.urine_min_ml_per_hour) : '');
      setNotes(io.plan?.notes ?? '');
    }
  }, [visible]);

  function close() {
    setError(null);
    onClose();
  }

  async function save() {
    const l = limit ? parseInt(limit, 10) : null;
    const u = urine ? parseInt(urine, 10) : null;
    if (l !== null && (l < o.limit_min || l > o.limit_max)) {
      setError(`The intake limit must be between ${o.limit_min} and ${o.limit_max} mL.`);
      return;
    }
    if (u !== null && (u < o.urine_min || u > o.urine_max)) {
      setError(`The urine target must be between ${o.urine_min} and ${o.urine_max} mL/h.`);
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.saveIoPlan({ intake_limit_ml: l, urine_min_ml_per_hour: u, notes: notes.trim() || null })
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
      eyebrow="I/O CHART"
      title="Fluid plan"
      subtitle="The most the patient may take in per chart day, and the least urine expected per hour. Leave both blank to lift the limits."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label="Save plan" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>INTAKE LIMIT (mL PER DAY)</Text>
      <View style={shared.chipWrap}>
        {o.limit_presets.map((v) => (
          <Chip key={v} small label={`${v}`} selected={String(v) === limit} onPress={() => setLimit(String(v))} />
        ))}
        <Chip small label="No limit" selected={limit === ''} onPress={() => setLimit('')} toneName="muted" />
      </View>
      <TextInput
        style={[shared.input, { marginTop: 8 }]}
        value={limit}
        onChangeText={(t) => setLimit(t.replace(/[^0-9]/g, ''))}
        keyboardType="number-pad"
        placeholder="e.g. 1500"
        placeholderTextColor={colors.mutedSoft}
        maxLength={5}
      />

      <Text style={shared.label}>URINE TARGET (mL PER HOUR)</Text>
      <View style={shared.chipWrap}>
        {o.urine_presets.map((v) => (
          <Chip key={v} small label={`${v}`} selected={String(v) === urine} onPress={() => setUrine(String(v))} />
        ))}
        <Chip small label="No target" selected={urine === ''} onPress={() => setUrine('')} toneName="muted" />
      </View>
      <TextInput
        style={[shared.input, { marginTop: 8 }]}
        value={urine}
        onChangeText={(t) => setUrine(t.replace(/[^0-9]/g, ''))}
        keyboardType="number-pad"
        placeholder="e.g. 30"
        placeholderTextColor={colors.mutedSoft}
        maxLength={3}
      />

      <Text style={shared.label}>NOTES (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={notes}
        onChangeText={setNotes}
        maxLength={255}
        placeholder="e.g. Per Dr Tan, heart failure"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function AssessmentSheet({ visible, io, onClose, perform }) {
  const o = io.options;
  const [grade, setGrade] = useState(0);
  const [sites, setSites] = useState([]);
  const [signs, setSigns] = useState([]);
  const [weight, setWeight] = useState('');
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  const toggle = (list, setList, key) =>
    setList(list.includes(key) ? list.filter((k) => k !== key) : [...list, key]);

  function close() {
    setGrade(0);
    setSites([]);
    setSigns([]);
    setWeight('');
    setNotes('');
    setError(null);
    onClose();
  }

  async function save() {
    const kg = weight ? parseFloat(weight.replace(',', '.')) : null;
    if (kg !== null && (Number.isNaN(kg) || kg < 1 || kg > 400)) {
      setError('Enter a weight between 1 and 400 kg, or leave it blank.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.addIoAssessment({
        edema_grade: grade,
        edema_sites: grade > 0 ? sites : [],
        signs,
        weight_kg: kg,
        notes: notes.trim() || null,
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
      eyebrow="I/O CHART"
      title="Fluid overload check"
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label="Save check" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>EDEMA</Text>
      <View style={shared.chipWrap}>
        {o.edema_grades.map((g) => (
          <Chip key={g.value} label={g.short} selected={grade === g.value} onPress={() => setGrade(g.value)} toneName={g.value >= 3 ? 'critical' : g.value > 0 ? 'warning' : 'good'} />
        ))}
      </View>
      <Text style={shared.hint}>{o.edema_grades.find((g) => g.value === grade)?.label}</Text>

      {grade > 0 ? (
        <>
          <Text style={shared.label}>WHERE</Text>
          <View style={shared.chipWrap}>
            {Object.entries(o.edema_sites).map(([key, label]) => (
              <Chip key={key} small label={label} selected={sites.includes(key)} onPress={() => toggle(sites, setSites, key)} toneName="warning" />
            ))}
          </View>
        </>
      ) : null}

      <Text style={shared.label}>SIGNS</Text>
      <View style={shared.chipWrap}>
        {Object.entries(o.signs).map(([key, label]) => (
          <Chip key={key} small label={label} selected={signs.includes(key)} onPress={() => toggle(signs, setSigns, key)} toneName={key === 'frothy_sputum' ? 'critical' : 'warning'} />
        ))}
      </View>

      <Text style={shared.label}>WEIGHT (kg, OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={weight}
        onChangeText={(t) => setWeight(t.replace(/[^0-9.,]/g, ''))}
        keyboardType="decimal-pad"
        placeholder="e.g. 71.5"
        placeholderTextColor={colors.mutedSoft}
        maxLength={6}
      />

      <Text style={shared.label}>NOTES (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={notes}
        onChangeText={setNotes}
        maxLength={255}
        placeholder="Anything else you noticed"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  dayNav: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.07)',
    padding: 8,
    marginBottom: 10,
  },
  dayBtn: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: colors.slate100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dayBtnOff: { opacity: 0.3 },
  dayBtnText: { fontSize: 22, fontWeight: '800', color: colors.slate700, lineHeight: 24 },
  dayLabel: { color: colors.slate900, fontSize: 15, fontWeight: '800' },
  totals: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginBottom: 10 },
  total: {
    flexGrow: 1,
    flexBasis: '46%',
    backgroundColor: '#fff',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.07)',
    padding: 10,
  },
  totalLabel: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 1.6 },
  totalValue: { marginTop: 4, fontSize: 20, fontWeight: '800' },
  limitHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 },
  limitTitle: { color: colors.slate900, fontSize: 13, fontWeight: '800' },
  addRow: { flexDirection: 'row', gap: 8, marginBottom: 6 },
  shiftHead: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    gap: 4,
    paddingHorizontal: 12,
    paddingVertical: 8,
    backgroundColor: colors.slate50,
    borderTopLeftRadius: radius.lg,
    borderTopRightRadius: radius.lg,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
  },
  shiftHeadCurrent: { backgroundColor: colors.cyan50 },
  shiftName: { color: colors.slate900, fontSize: 12, fontWeight: '800' },
  shiftTotals: { color: colors.muted, fontSize: 11, fontWeight: '600' },
  entry: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: 10,
    paddingHorizontal: 12,
    paddingVertical: 9,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
  },
  entryTime: { width: 42, color: colors.slate500, fontSize: 12, fontWeight: '700', fontVariant: ['tabular-nums'] },
  entryLabel: { color: colors.slate900, fontSize: 13, fontWeight: '600' },
  entryVolume: { fontSize: 13, fontWeight: '800', fontVariant: ['tabular-nums'] },
  struck: { textDecorationLine: 'line-through', color: colors.slate500 },
  voidNote: { marginTop: 2, color: colors.rose600, fontSize: 11 },
  planText: { color: colors.slate900, fontSize: 13, fontWeight: '700' },
  dayRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 6 },
  dayRowLabel: { width: 84, color: colors.slate700, fontSize: 12, fontWeight: '700' },
  dayRowValue: { flex: 1, color: colors.muted, fontSize: 11 },
  dayRowBalance: { fontSize: 12, fontWeight: '800' },
});
