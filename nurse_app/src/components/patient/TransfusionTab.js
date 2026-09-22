// Blood transfusion, step by step - the ward dashboard's Blood Transfusion
// tab on the phone. Units are shown by where they are in their life:
//
//   Pre-start  registered, waiting for the four bedside checks (strictly in
//              order: only the next can be confirmed, only the last undone),
//              then an explicit start once every check passes and nothing
//              critical is flagged;
//   Running    time-based monitoring (predicted end, the 4 hour limit), then
//              complete, or stop early with a reason;
//   Finished   this stay's completed and stopped units.
//
// Each unit's problems sit on its own card. After an action the tab moves to
// the stage the unit is now in. The unit number and crossmatch reference can
// be scanned. The server applies the same rules as the ward dashboard; this
// screen only offers what they allow.

import React, { useEffect, useMemo, useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import Sheet from '../Sheet';
import BarcodeScanner from '../BarcodeScanner';
import { readCrossmatch, readUnitNumber } from '../../utils/bloodLabels';
import { Banner, Button, Card, Chip, EmptyNote, Progress, SectionTitle, Tag, shared, tone } from '../ui';

const LEVEL_TONE = { critical: 'critical', warning: 'warning', info: 'info' };
const RED_CELL_PRODUCTS = ['Packed Red Cells', 'Whole Blood'];
const RED_CELL_COMPATIBILITY = {
  'O-': ['O-'],
  'O+': ['O-', 'O+'],
  'A-': ['O-', 'A-'],
  'A+': ['O-', 'O+', 'A-', 'A+'],
  'B-': ['O-', 'B-'],
  'B+': ['O-', 'O+', 'B-', 'B+'],
  'AB-': ['O-', 'A-', 'B-', 'AB-'],
  'AB+': ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'],
};
const STOP_REASONS = ['Suspected reaction', 'Patient refused', 'Cannula / line problem', "Doctor's instruction"];

const STAGES = [
  { key: 'pending', label: 'Pre-start', hint: 'Registered units waiting for the four bedside checks. A unit can start once all four are confirmed and nothing critical is flagged.' },
  { key: 'running', label: 'Running', hint: 'Units being transfused: the predicted end and the four hour limit.' },
  { key: 'finished', label: 'Finished', hint: 'Units completed or stopped early during this stay.' },
];

function hhmm(total) {
  const t = Math.max(0, Math.round(total));
  const h = Math.floor(t / 60);
  const m = t % 60;
  return h > 0 ? `${h}h ${String(m).padStart(2, '0')}m` : `${m}m`;
}

/** Clock time on the server, ticking: device time corrected by the skew seen at load. */
function useServerNow(generatedAt) {
  const skew = useMemo(() => {
    const server = Date.parse(generatedAt);
    return Number.isNaN(server) ? 0 : server - Date.now();
  }, [generatedAt]);
  const [tick, setTick] = useState(Date.now());
  useEffect(() => {
    const timer = setInterval(() => setTick(Date.now()), 30000);
    return () => clearInterval(timer);
  }, []);
  return tick + skew;
}

/** Open on what matters most: a running unit, then one in its checks. */
function firstStage(t) {
  if (t.running.length) return 'running';
  if (t.pending.length) return 'pending';
  if (t.finished.length) return 'finished';
  return 'pending';
}

export default function TransfusionTab({ chart, perform }) {
  const t = chart.transfusions;
  const now = useServerNow(chart.generated_at);
  const [stage, setStage] = useState(() => firstStage(t));
  const [adding, setAdding] = useState(false);
  const [finishing, setFinishing] = useState(null);
  const [starting, setStarting] = useState(null);
  const [busyStep, setBusyStep] = useState(null);
  const [error, setError] = useState(null);

  const problemsOf = (unit) => t.exceptions.filter((e) => e.unit_id === unit.id);
  const units = { pending: t.pending, running: t.running, finished: t.finished };
  const critical = {
    pending: t.pending.some((u) => problemsOf(u).some((e) => e.level === 'critical')),
    running: t.running.some((u) => problemsOf(u).some((e) => e.level === 'critical')),
    finished: false,
  };

  function show(key) {
    setStage(key);
    setError(null);
  }

  async function check(unit, step, action) {
    setBusyStep(`${unit.id}:${step.key}:${action}`);
    setError(null);
    const result = await perform((c) => c.transfusionCheck(unit.id, { step: step.key, action }));
    setBusyStep(null);
    if (!result.ok) setError(result.error);
  }

  const summary = [
    t.running.length ? `${t.running.length} running` : null,
    t.pending.length ? `${t.pending.length} pre-start` : null,
  ].filter(Boolean).join(' · ') || 'No unit in progress';
  const completed = t.finished.filter((u) => u.status === 'completed').length;
  const stopped = t.finished.length - completed;

  return (
    <View>
      <SectionTitle
        eyebrow="BLOOD TRANSFUSION"
        title={summary}
        right={<Button label="+ Register unit" small onPress={() => setAdding(true)} />}
      />

      {/* The unit's life, left to right: tap a stage to see its units */}
      <View style={styles.stages}>
        {STAGES.map((s, i) => {
          const active = stage === s.key;
          const count = units[s.key].length;
          return (
            <React.Fragment key={s.key}>
              {i > 0 ? <Text style={styles.stageArrow}>›</Text> : null}
              <TouchableOpacity
                activeOpacity={0.85}
                onPress={() => show(s.key)}
                style={[styles.stage, active && styles.stageActive]}
              >
                <Text style={[styles.stageLabel, active && styles.stageLabelActive]} numberOfLines={1}>
                  {s.label}
                </Text>
                <View
                  style={[
                    styles.stageCount,
                    count > 0 && s.key === 'running' && { backgroundColor: colors.rose600 },
                    count > 0 && s.key === 'pending' && { backgroundColor: '#2563eb' },
                    count > 0 && s.key === 'finished' && { backgroundColor: colors.slate500 },
                  ]}
                >
                  <Text style={[styles.stageCountText, count > 0 && { color: '#fff' }]}>{count}</Text>
                </View>
                {critical[s.key] ? <View style={styles.stageAlarm} /> : null}
              </TouchableOpacity>
            </React.Fragment>
          );
        })}
      </View>
      <Text style={styles.stageHint}>{STAGES.find((s) => s.key === stage).hint}</Text>

      {error ? <Banner toneName="critical" title="Not done" text={error} /> : null}

      {stage === 'pending' ? (
        t.pending.length === 0 ? (
          <Card>
            <EmptyNote text="No unit waiting for its checks. Register a unit when it arrives from the blood bank." />
            <Button label="+ Register a unit" style={{ marginTop: 10 }} onPress={() => setAdding(true)} />
          </Card>
        ) : (
          t.pending.map((unit) => (
            <PendingCard
              key={unit.id}
              unit={unit}
              problems={problemsOf(unit)}
              busyStep={busyStep}
              onCheck={check}
              onStart={() => setStarting(unit)}
            />
          ))
        )
      ) : null}

      {stage === 'running' ? (
        t.running.length === 0 ? (
          <Card>
            <EmptyNote text="No unit running. A unit starts from Pre-start once its four checks are confirmed." />
            {t.pending.length ? (
              <Button
                label={`Go to Pre-start (${t.pending.length})`}
                kind="outline"
                style={{ marginTop: 10 }}
                onPress={() => show('pending')}
              />
            ) : null}
          </Card>
        ) : (
          t.running.map((unit) => (
            <RunningCard key={unit.id} unit={unit} problems={problemsOf(unit)} now={now} onFinish={() => setFinishing(unit)} />
          ))
        )
      ) : null}

      {stage === 'finished' ? (
        t.finished.length === 0 ? (
          <Card>
            <EmptyNote text="No unit finished yet this stay." />
          </Card>
        ) : (
          <>
            <Text style={[shared.meta, { marginBottom: 8 }]}>
              {[completed ? `${completed} completed` : null, stopped ? `${stopped} stopped early` : null].filter(Boolean).join(' · ')}
              {' · latest finished first'}
            </Text>
            {t.finished.map((unit) => (
              <FinishedCard key={unit.id} unit={unit} />
            ))}
          </>
        )
      ) : null}

      <Text style={[shared.meta, { marginTop: 4, marginBottom: 8 }]}>
        The checks here support the bedside procedure, they do not replace it. Group compatibility is only asserted for
        red cell products; everything else is left for manual confirmation.
      </Text>

      <RegisterSheet
        visible={adding}
        options={t.options}
        onClose={() => setAdding(false)}
        onDone={() => show('pending')}
        perform={perform}
      />
      <StartSheet unit={starting} onClose={() => setStarting(null)} onDone={() => show('running')} perform={perform} />
      <FinishSheet unit={finishing} onClose={() => setFinishing(null)} onDone={() => show('finished')} perform={perform} />
    </View>
  );
}

/** The unit's own flagged problems, worst first (the server sorts them). */
function Problems({ problems }) {
  if (!problems.length) return null;
  return (
    <View style={{ marginTop: 10 }}>
      {problems.map((e, i) => {
        const tn = tone(LEVEL_TONE[e.level] ?? 'info');
        return (
          <View key={i} style={[styles.problem, { backgroundColor: tn.bg, borderColor: tn.border }]}>
            <View style={[styles.problemBar, { backgroundColor: tn.solid }]} />
            <Text style={[styles.problemTitle, { color: tn.text }]}>{e.title}</Text>
            {e.detail ? <Text style={[styles.problemDetail, { color: tn.text }]}>{e.detail}</Text> : null}
          </View>
        );
      })}
    </View>
  );
}

function PendingCard({ unit, problems, busyStep, onCheck, onStart }) {
  const blocked = problems.some((e) => e.level === 'critical');
  return (
    <Card style={blocked ? { borderColor: colors.rose100 } : null}>
      <View style={styles.unitHead}>
        <Tag label="PRE-START" toneName={blocked ? 'critical' : 'info'} />
        <Text style={styles.unitNumber}>{unit.unit_number}</Text>
        <Text style={[shared.meta, { marginLeft: 'auto' }]}>{unit.steps_done} of 4 checks</Text>
      </View>
      <Text style={styles.unitMeta}>
        {unit.product} · {unit.groups}
        {unit.volume_ml ? ` · ${unit.volume_ml} mL` : ''}
        {unit.prescribed_minutes ? ` over ${hhmm(unit.prescribed_minutes)}` : ''}
      </Text>
      <Text style={shared.meta}>
        Registered {unit.registered_label}
        {unit.registered_by ? ` by ${unit.registered_by}` : ''}
        {unit.crossmatch_reference ? ` · crossmatch ${unit.crossmatch_reference}` : ''}
      </Text>

      <Problems problems={problems} />

      <View style={{ marginTop: 10, gap: 8 }}>
        {unit.steps.map((step) => {
          const state = step.done ? 'done' : step.is_next ? 'next' : 'locked';
          return (
            <View key={step.key} style={[styles.step, styles[`step_${state}`]]}>
              <View style={[styles.stepDot, styles[`dot_${state}`]]}>
                <Text style={styles.stepDotText}>{step.done ? '✓' : step.number}</Text>
              </View>
              <View style={{ flex: 1 }}>
                <Text style={styles.stepLabel}>{step.label}</Text>
                <Text style={styles.stepDetail}>{step.detail}</Text>
                {step.problem ? <Text style={styles.stepProblem}>{step.problem}</Text> : null}
              </View>
              {step.is_next ? (
                <Button label="Confirm" small busy={busyStep === `${unit.id}:${step.key}:confirm`} onPress={() => onCheck(unit, step, 'confirm')} />
              ) : step.can_undo ? (
                <Button label="Undo" kind="outline" small busy={busyStep === `${unit.id}:${step.key}:undo`} onPress={() => onCheck(unit, step, 'undo')} />
              ) : (
                <Text style={[styles.stepState, step.done && { color: colors.emerald700 }]}>{step.done ? 'Done' : 'Locked'}</Text>
              )}
            </View>
          );
        })}
      </View>

      <View style={styles.startRow}>
        <Text style={[styles.startNote, unit.can_start && { color: colors.emerald700, fontWeight: '800' }, blocked && { color: colors.rose700, fontWeight: '700' }]}>
          {unit.start_note}
          {unit.last_action_label ? <Text style={shared.meta}>{`  ·  last check ${unit.last_action_label}`}</Text> : null}
        </Text>
        <Button label="Start transfusion" kind="danger" disabled={!unit.can_start} onPress={onStart} />
      </View>
    </Card>
  );
}

function RunningCard({ unit, problems, now, onFinish }) {
  const started = Date.parse(unit.started_at);
  const end = unit.end_at ? Date.parse(unit.end_at) : null;
  const limit = unit.limit_at ? Date.parse(unit.limit_at) : null;
  const elapsed = Math.max(0, Math.floor((now - started) / 60000));
  const overdue = end !== null && now > end;
  const breached = limit !== null && now > limit;
  const percent = unit.prescribed_minutes ? Math.min(100, Math.round((elapsed / unit.prescribed_minutes) * 100)) : null;

  return (
    <Card style={{ borderColor: colors.rose100, backgroundColor: '#fffafb' }}>
      <View style={styles.unitHead}>
        <Tag label="RUNNING" toneName="critical" solid />
        <Text style={styles.unitNumber}>{unit.unit_number}</Text>
        {unit.unit_group ? <Tag label={unit.unit_group} toneName="critical" /> : null}
        <Text style={[shared.meta, { marginLeft: 'auto' }]}>{unit.volume_ml ? `${unit.volume_ml} mL` : 'Volume not recorded'}</Text>
      </View>
      <Text style={styles.unitMeta}>{unit.product} · {unit.groups}</Text>

      <View style={styles.grid}>
        <Cell label="STARTED" value={unit.started_label} />
        <Cell label="RATE" value={unit.rate ? `${unit.rate} mL/h` : '—'} sub="prescribed" />
      </View>
      <View style={styles.grid}>
        <Cell
          label="PREDICTED END"
          value={unit.end_label ?? '—'}
          sub={end === null ? null : overdue ? `${hhmm((now - end) / 60000)} overdue` : `${hhmm((end - now) / 60000)} remaining`}
          alarm={overdue}
        />
        <Cell
          label="ELAPSED"
          value={hhmm(elapsed)}
          sub={breached ? 'past the 4 hour limit' : `limit ${unit.limit_label ?? '—'}`}
          alarm={breached}
        />
      </View>

      {percent !== null ? (
        <View style={{ marginTop: 10 }}>
          <Progress percent={percent} toneName={overdue ? 'critical' : 'good'} />
          <Text style={[shared.meta, { marginTop: 4 }]}>
            {percent}% of the planned {hhmm(unit.prescribed_minutes)}
          </Text>
        </View>
      ) : null}

      <Problems problems={problems} />

      <View style={{ flexDirection: 'row', gap: 8, marginTop: 12 }}>
        <Button label="Complete / Stop" kind="dark" flex onPress={onFinish} />
      </View>
    </Card>
  );
}

function FinishedCard({ unit }) {
  const isCompleted = unit.status === 'completed';
  return (
    <Card>
      <View style={styles.unitHead}>
        <Tag label={isCompleted ? 'COMPLETED' : 'STOPPED EARLY'} toneName={isCompleted ? 'good' : 'critical'} solid={!isCompleted} />
        <Text style={styles.unitNumber}>{unit.unit_number}</Text>
        {unit.unit_group ? <Tag label={unit.unit_group} toneName="muted" /> : null}
      </View>
      <Text style={styles.unitMeta}>
        {unit.product}
        {unit.volume_ml ? ` · ${unit.volume_ml} mL` : ''}
        {unit.prescribed_minutes ? ` planned over ${hhmm(unit.prescribed_minutes)}` : ''}
      </Text>
      <View style={styles.grid}>
        <Cell label="STARTED" value={unit.started_label ?? '—'} />
        <Cell label={isCompleted ? 'COMPLETED' : 'STOPPED'} value={unit.completed_label ?? '—'} />
        <Cell label="TOOK" value={unit.took_minutes != null ? hhmm(unit.took_minutes) : '—'} />
      </View>
      {unit.stop_reason ? (
        <View style={[styles.problem, { marginTop: 10, backgroundColor: colors.rose50, borderColor: colors.rose100 }]}>
          <View style={[styles.problemBar, { backgroundColor: colors.rose600 }]} />
          <Text style={[styles.problemTitle, { color: colors.rose700 }]}>Reason stopped</Text>
          <Text style={[styles.problemDetail, { color: colors.rose700 }]}>{unit.stop_reason}</Text>
        </View>
      ) : null}
      {unit.crossmatch_reference ? <Text style={[shared.meta, { marginTop: 8 }]}>Crossmatch {unit.crossmatch_reference}</Text> : null}
    </Card>
  );
}

function Cell({ label, value, sub, alarm }) {
  return (
    <View style={{ flex: 1 }}>
      <Text style={styles.cellLabel}>{label}</Text>
      <Text style={[styles.cellValue, alarm && { color: colors.rose700 }]}>{value ?? '—'}</Text>
      {sub ? <Text style={[styles.cellSub, alarm && { color: colors.rose600, fontWeight: '700' }]}>{sub}</Text> : null}
    </View>
  );
}

// ------------------------------------------------------------- register

function compatibilityOf(product, unitGroup, patientGroup) {
  if (!unitGroup || !patientGroup || !RED_CELL_PRODUCTS.includes(product)) return 'manual';
  return (RED_CELL_COMPATIBILITY[patientGroup] ?? []).includes(unitGroup) ? 'compatible' : 'incompatible';
}

function pad2(n) {
  return String(n).padStart(2, '0');
}

function dateAfter(days) {
  const d = new Date();
  d.setDate(d.getDate() + days);
  return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

const SCAN = {
  unit: {
    title: 'Scan the unit number',
    hint: 'Point at the donation number barcode, top left of the bag label. Hold steady until it reads.',
    read: readUnitNumber,
  },
  crossmatch: {
    title: 'Scan the crossmatch reference',
    hint: 'Point at the barcode on the crossmatch or compatibility label.',
    read: readCrossmatch,
  },
};

function RegisterSheet({ visible, options, onClose, onDone, perform }) {
  const products = options.product_types;
  const [unitNumber, setUnitNumber] = useState('');
  const [product, setProduct] = useState(products[0]);
  const [volume, setVolume] = useState(options.presets[products[0]]?.volume_ml ?? 300);
  const [minutes, setMinutes] = useState(options.presets[products[0]]?.minutes ?? 120);
  const [unitGroup, setUnitGroup] = useState(null);
  const [patientGroup, setPatientGroup] = useState(null);
  const [crossmatch, setCrossmatch] = useState('');
  const [expiryDate, setExpiryDate] = useState('');
  const [expiryTime, setExpiryTime] = useState('23:59');
  const [notes, setNotes] = useState('');
  const [scanning, setScanning] = useState(null); // 'unit' | 'crossmatch' | null
  const [scanned, setScanned] = useState({ unit: null, crossmatch: null }); // what the scanner read, until edited
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  // Fresh form each time, with the patient's group from an earlier unit
  useEffect(() => {
    if (!visible) return;
    const first = products[0];
    setUnitNumber('');
    setProduct(first);
    setVolume(options.presets[first]?.volume_ml ?? 300);
    setMinutes(options.presets[first]?.minutes ?? 120);
    setUnitGroup(null);
    setPatientGroup(options.patient_blood_group ?? null);
    setCrossmatch('');
    setExpiryDate('');
    setExpiryTime('23:59');
    setNotes('');
    setScanning(null);
    setScanned({ unit: null, crossmatch: null });
    setError(null);
  }, [visible]);

  const preset = options.presets[product];
  const rate = minutes > 0 ? Math.round((volume / (minutes / 60)) * 10) / 10 : null;
  const durationMessage = !preset
    ? null
    : minutes < preset.min_minutes
      ? `${product} is normally given over at least ${preset.min_minutes} min. ${minutes} min is faster than usual.`
      : minutes > preset.max_minutes
        ? `${product} is normally finished within ${preset.max_minutes} min. ${minutes} min is longer than usual.`
        : null;
  const match = compatibilityOf(product, unitGroup, patientGroup);

  function choose(p) {
    setProduct(p);
    setVolume(options.presets[p]?.volume_ml ?? volume);
    setMinutes(options.presets[p]?.minutes ?? minutes);
  }

  function applyScan(field, value, result) {
    if (field === 'unit') setUnitNumber(value);
    else setCrossmatch(value);
    setScanned((s) => ({ ...s, [field]: result?.note ?? 'Scanned' }));
    setScanning(null);
    setError(null);
  }

  const clamp = (n, min, max) => Math.min(max, Math.max(min, n));

  async function save() {
    if (!unitNumber.trim()) {
      setError('Enter or scan the unit number from the bag.');
      return;
    }
    let expiresAt = null;
    if (expiryDate.trim()) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(expiryDate.trim()) || !/^\d{2}:\d{2}$/.test(expiryTime.trim())) {
        setError('Enter the expiry as YYYY-MM-DD and HH:MM, e.g. 2026-09-30 and 23:59.');
        return;
      }
      expiresAt = `${expiryDate.trim()} ${expiryTime.trim()}`;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.addTransfusion({
        unit_number: unitNumber.trim(),
        product_type: product,
        unit_blood_group: unitGroup,
        patient_blood_group: patientGroup,
        crossmatch_reference: crossmatch.trim() || null,
        unit_expires_at: expiresAt,
        volume_ml: volume,
        prescribed_minutes: minutes,
        notes: notes.trim() || null,
      })
    );
    setBusy(false);
    if (result.ok) {
      onClose();
      onDone?.();
    } else {
      setError(result.error);
    }
  }

  return (
    <Sheet
      visible={visible}
      busy={busy}
      onClose={onClose}
      eyebrow="BLOOD TRANSFUSION"
      title="Register a unit"
      subtitle="Nothing runs yet: the unit goes to Pre-start for the four bedside checks and an explicit start."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label="Register unit" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>UNIT NUMBER</Text>
      <View style={styles.scanRow}>
        <TextInput
          style={[shared.input, styles.scanInput]}
          value={unitNumber}
          onChangeText={(v) => {
            setUnitNumber(v);
            setScanned((s) => ({ ...s, unit: null }));
          }}
          autoCapitalize="characters"
          autoCorrect={false}
          maxLength={64}
          placeholder="Scan, or type from the bag"
          placeholderTextColor={colors.mutedSoft}
        />
        <ScanButton onPress={() => setScanning('unit')} disabled={busy} />
      </View>
      <ScanNote note={scanned.unit} idle="Scan the donation number barcode on the bag, or type it." />

      <Text style={shared.label}>PRODUCT</Text>
      <View style={shared.chipWrap}>
        {products.map((p) => (
          <Chip key={p} small label={p} selected={product === p} onPress={() => choose(p)} toneName="critical" />
        ))}
      </View>
      {preset?.note ? <Text style={shared.hint}>{preset.note}</Text> : null}

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <View style={{ flex: 1 }}>
          <Text style={shared.label}>VOLUME (mL)</Text>
          <Stepper
            value={volume}
            onChange={(v) => setVolume(clamp(v, options.volume_min, options.volume_max))}
            step={options.volume_step}
          />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={shared.label}>DURATION (min)</Text>
          <Stepper
            value={minutes}
            onChange={(v) => setMinutes(clamp(v, options.minutes_min, options.minutes_max))}
            step={options.minutes_step}
            warn={!!durationMessage}
          />
        </View>
      </View>
      <Text style={shared.hint}>
        Rate {rate ?? '—'} mL/h · {volume} mL over {hhmm(minutes)} · limit {options.minutes_max} min
      </Text>
      {durationMessage ? (
        <Banner
          toneName="warning"
          title="Check the duration"
          text={durationMessage}
          right={<Button label="Usual figures" kind="outline" small onPress={() => choose(product)} />}
        />
      ) : null}

      <Text style={shared.label}>UNIT BLOOD GROUP</Text>
      <GroupPicker groups={options.blood_groups} value={unitGroup} onChange={setUnitGroup} />
      <Text style={shared.label}>PATIENT BLOOD GROUP</Text>
      <GroupPicker groups={options.blood_groups} value={patientGroup} onChange={setPatientGroup} />
      {match === 'incompatible' ? (
        <Banner toneName="critical" title="Not compatible" text={`Unit ${unitGroup} to patient ${patientGroup} is not a compatible red cell pairing. The unit cannot be started.`} />
      ) : match === 'compatible' ? (
        <Text style={[shared.hint, { color: colors.emerald700, fontWeight: '700' }]}>Unit {unitGroup} to patient {patientGroup}: compatible red cell pairing.</Text>
      ) : (
        <Text style={shared.hint}>
          {RED_CELL_PRODUCTS.includes(product) ? 'Record both groups so the pairing can be checked.' : `${product} does not follow the red cell rule: confirm compatibility at the bedside.`}
        </Text>
      )}

      <Text style={shared.label}>CROSSMATCH REFERENCE</Text>
      <View style={styles.scanRow}>
        <TextInput
          style={[shared.input, styles.scanInput]}
          value={crossmatch}
          onChangeText={(v) => {
            setCrossmatch(v);
            setScanned((s) => ({ ...s, crossmatch: null }));
          }}
          autoCapitalize="characters"
          autoCorrect={false}
          maxLength={64}
          placeholder="e.g. XM-24-1187"
          placeholderTextColor={colors.mutedSoft}
        />
        <ScanButton onPress={() => setScanning('crossmatch')} disabled={busy} />
      </View>
      <ScanNote note={scanned.crossmatch} idle="Scan the crossmatch or compatibility label, or type the reference." />

      <Text style={shared.label}>UNIT EXPIRES</Text>
      <View style={shared.chipWrap}>
        {[['Today', 0], ['Tomorrow', 1], ['+3 days', 3], ['+7 days', 7], ['+35 days', 35]].map(([label, days]) => (
          <Chip key={label} small label={label} selected={expiryDate === dateAfter(days)} onPress={() => setExpiryDate(dateAfter(days))} />
        ))}
      </View>
      <View style={{ flexDirection: 'row', gap: 8, marginTop: 8 }}>
        {/* minWidth 0: a browser's text box would not shrink below its default width */}
        <TextInput
          style={[shared.input, { flex: 3, minWidth: 0 }]}
          value={expiryDate}
          onChangeText={setExpiryDate}
          placeholder="YYYY-MM-DD"
          placeholderTextColor={colors.mutedSoft}
          maxLength={10}
          autoCorrect={false}
        />
        <TextInput
          style={[shared.input, { flex: 2, minWidth: 0 }]}
          value={expiryTime}
          onChangeText={setExpiryTime}
          placeholder="HH:MM"
          placeholderTextColor={colors.mutedSoft}
          maxLength={5}
          autoCorrect={false}
        />
      </View>
      <Text style={shared.hint}>As printed on the bag. Leave the date blank if it is not recorded.</Text>

      <Text style={shared.label}>NOTES (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={notes}
        onChangeText={setNotes}
        maxLength={1000}
        placeholder="e.g. Premedicate with paracetamol"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}

      {/* Inside the sheet: a modal opened from a modal */}
      <BarcodeScanner
        visible={scanning !== null}
        title={scanning ? SCAN[scanning].title : ''}
        hint={scanning ? SCAN[scanning].hint : ''}
        read={scanning ? SCAN[scanning].read : readUnitNumber}
        onScanned={(value, result) => applyScan(scanning, value, result)}
        onClose={() => setScanning(null)}
      />
    </Sheet>
  );
}

/** Square button with a small barcode drawn in bars. */
function ScanButton({ onPress, disabled }) {
  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      disabled={disabled}
      style={[styles.scanBtn, disabled && { opacity: 0.5 }]}
    >
      <View style={styles.glyph}>
        {[3, 1, 2, 1, 3, 1, 1, 2].map((w, i) => (
          <View key={i} style={{ width: w, height: 14, backgroundColor: i % 2 ? 'transparent' : colors.cyan700 }} />
        ))}
      </View>
      <Text style={styles.scanBtnText}>Scan</Text>
    </TouchableOpacity>
  );
}

function ScanNote({ note, idle }) {
  return note ? (
    <Text style={[shared.hint, { color: colors.emerald700, fontWeight: '700' }]}>
      ✓ Scanned{note !== 'Scanned' ? ` · ${note}` : ''}. Check it against the label.
    </Text>
  ) : (
    <Text style={shared.hint}>{idle}</Text>
  );
}

function Stepper({ value, onChange, step, warn }) {
  const [text, setText] = useState(String(value));
  useEffect(() => setText(String(value)), [value]);

  return (
    <View style={[styles.stepper, warn && { borderColor: colors.amber500 }]}>
      <TouchableOpacity style={styles.stepperBtn} onPress={() => onChange(value - step)} activeOpacity={0.7}>
        <Text style={styles.stepperBtnText}>−</Text>
      </TouchableOpacity>
      <TextInput
        style={styles.stepperInput}
        value={text}
        onChangeText={(t) => setText(t.replace(/[^0-9]/g, ''))}
        onBlur={() => onChange(parseInt(text, 10) || 0)}
        onSubmitEditing={() => onChange(parseInt(text, 10) || 0)}
        keyboardType="number-pad"
        maxLength={4}
      />
      <TouchableOpacity style={styles.stepperBtn} onPress={() => onChange(value + step)} activeOpacity={0.7}>
        <Text style={styles.stepperBtnText}>+</Text>
      </TouchableOpacity>
    </View>
  );
}

function GroupPicker({ groups, value, onChange }) {
  return (
    <View style={shared.chipWrap}>
      {groups.map((g) => (
        <Chip key={g} small label={g} selected={value === g} onPress={() => onChange(value === g ? null : g)} toneName="critical" />
      ))}
      <Chip small label="Not recorded" selected={!value} onPress={() => onChange(null)} toneName="muted" />
    </View>
  );
}

// ---------------------------------------------------------- start / finish

function StartSheet({ unit, onClose, onDone, perform }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  async function start() {
    setBusy(true);
    setError(null);
    const result = await perform((c) => c.startTransfusion(unit.id));
    setBusy(false);
    if (result.ok) {
      onClose();
      onDone?.();
    } else {
      setError(result.error);
    }
  }

  return (
    <Sheet
      visible={!!unit}
      busy={busy}
      onClose={() => { setError(null); onClose(); }}
      eyebrow="BLOOD TRANSFUSION"
      title={unit ? `Start unit ${unit.unit_number}?` : ''}
      subtitle="All four bedside checks are confirmed. Starting now begins the timing: the unit must be finished within four hours."
      footer={
        <>
          <Button label="Not yet" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label="Start now" kind="danger" flex busy={busy} onPress={start} />
        </>
      }
    >
      {unit ? (
        <View style={{ marginTop: 12 }}>
          <Text style={styles.confirmLine}>{unit.product} · {unit.groups}</Text>
          <Text style={styles.confirmLine}>
            {unit.volume_ml ? `${unit.volume_ml} mL` : 'Volume not recorded'}
            {unit.prescribed_minutes ? ` over ${hhmm(unit.prescribed_minutes)}` : ''}
            {unit.rate ? ` · ${unit.rate} mL/h` : ''}
          </Text>
          {unit.crossmatch_reference ? <Text style={styles.confirmLine}>Crossmatch {unit.crossmatch_reference}</Text> : null}
        </View>
      ) : null}
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function FinishSheet({ unit, onClose, onDone, perform }) {
  const [outcome, setOutcome] = useState('completed');
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (unit) {
      setOutcome('completed');
      setReason('');
      setError(null);
    }
  }, [unit]);

  async function save() {
    if (outcome === 'stopped' && !reason.trim()) {
      setError('Give a reason when stopping a unit early.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.finishTransfusion(unit.id, { outcome, stop_reason: outcome === 'stopped' ? reason.trim() : null })
    );
    setBusy(false);
    if (result.ok) {
      onClose();
      onDone?.();
    } else {
      setError(result.error);
    }
  }

  return (
    <Sheet
      visible={!!unit}
      busy={busy}
      onClose={onClose}
      eyebrow="BLOOD TRANSFUSION"
      title={unit ? `Finish unit ${unit.unit_number}` : ''}
      footer={
        <>
          <Button label="Back" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button
            label={outcome === 'completed' ? 'Complete' : 'Stop unit'}
            kind={outcome === 'completed' ? 'success' : 'danger'}
            flex
            busy={busy}
            onPress={save}
          />
        </>
      }
    >
      <Text style={shared.label}>OUTCOME</Text>
      <View style={shared.chipWrap}>
        <Chip label="Completed" selected={outcome === 'completed'} onPress={() => setOutcome('completed')} toneName="good" />
        <Chip label="Stopped early" selected={outcome === 'stopped'} onPress={() => setOutcome('stopped')} toneName="critical" />
      </View>
      {outcome === 'stopped' ? (
        <>
          <Text style={shared.label}>REASON (REQUIRED)</Text>
          <View style={[shared.chipWrap, { marginBottom: 8 }]}>
            {STOP_REASONS.map((r) => (
              <Chip key={r} small label={r} selected={reason === r} onPress={() => setReason(r)} toneName="warning" />
            ))}
          </View>
          <TextInput
            style={shared.input}
            value={reason}
            onChangeText={setReason}
            maxLength={255}
            placeholder="e.g. Suspected reaction: rigors, temp 38.6"
            placeholderTextColor={colors.mutedSoft}
          />
          {reason === 'Suspected reaction' ? (
            <Text style={[shared.hint, { color: colors.rose700, fontWeight: '700' }]}>
              Keep the line open with saline, take observations and inform the doctor and the blood bank.
            </Text>
          ) : null}
        </>
      ) : (
        <Text style={shared.hint}>The unit is recorded as completed now. It moves to Finished.</Text>
      )}
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  stages: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    padding: 4,
    gap: 2,
  },
  stage: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 6,
    borderRadius: 10,
    paddingVertical: 9,
    paddingHorizontal: 4,
  },
  stageActive: { backgroundColor: colors.slate900 },
  stageLabel: { flexShrink: 1, color: colors.slate600, fontSize: 13, fontWeight: '800' },
  stageLabelActive: { color: '#fff' },
  stageCount: {
    minWidth: 20,
    height: 20,
    paddingHorizontal: 5,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate100,
  },
  stageCountText: { color: colors.slate500, fontSize: 11, fontWeight: '800' },
  stageAlarm: {
    position: 'absolute',
    top: 4,
    right: 6,
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: colors.rose600,
    borderWidth: 1,
    borderColor: '#fff',
  },
  stageArrow: { color: colors.slate300, fontSize: 16, fontWeight: '800' },
  stageHint: { marginTop: 6, marginBottom: 10, color: colors.muted, fontSize: 11, lineHeight: 15 },
  unitHead: { flexDirection: 'row', alignItems: 'center', gap: 8, flexWrap: 'wrap' },
  unitNumber: { color: colors.slate900, fontSize: 15, fontWeight: '800' },
  unitMeta: { marginTop: 4, color: colors.slate600, fontSize: 12 },
  problem: {
    borderWidth: 1,
    borderRadius: radius.sm,
    paddingVertical: 8,
    paddingLeft: 12,
    paddingRight: 10,
    marginBottom: 6,
    overflow: 'hidden',
  },
  problemBar: { position: 'absolute', left: 0, top: 0, bottom: 0, width: 4 },
  problemTitle: { fontSize: 12, fontWeight: '800' },
  problemDetail: { marginTop: 2, fontSize: 11, lineHeight: 15 },
  step: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: 10,
    paddingVertical: 9,
  },
  step_done: { borderColor: colors.emerald100, backgroundColor: colors.emerald50 },
  step_next: { borderColor: '#93c5fd', backgroundColor: '#eff6ff' },
  step_locked: { borderColor: colors.slate200, backgroundColor: colors.slate50, opacity: 0.6 },
  stepDot: { width: 26, height: 26, borderRadius: 13, alignItems: 'center', justifyContent: 'center' },
  dot_done: { backgroundColor: colors.emerald600 },
  dot_next: { backgroundColor: '#2563eb' },
  dot_locked: { backgroundColor: colors.slate300 },
  stepDotText: { color: '#fff', fontSize: 12, fontWeight: '800' },
  stepLabel: { color: colors.slate900, fontSize: 13, fontWeight: '700' },
  stepDetail: { marginTop: 1, color: colors.slate500, fontSize: 11 },
  stepProblem: {
    marginTop: 4,
    alignSelf: 'flex-start',
    color: colors.rose700,
    backgroundColor: colors.rose100,
    borderRadius: 6,
    paddingHorizontal: 6,
    paddingVertical: 2,
    fontSize: 11,
    fontWeight: '700',
    overflow: 'hidden',
  },
  stepState: { color: colors.slate500, fontSize: 11, fontWeight: '700' },
  startRow: { marginTop: 12, paddingTop: 12, borderTopWidth: 1, borderTopColor: colors.slate100, gap: 10 },
  startNote: { color: colors.slate600, fontSize: 12 },
  grid: { flexDirection: 'row', gap: 10, marginTop: 10 },
  cellLabel: { color: colors.muted, fontSize: 9, fontWeight: '800', letterSpacing: 1.2 },
  cellValue: { marginTop: 2, color: colors.slate900, fontSize: 14, fontWeight: '800' },
  cellSub: { marginTop: 1, color: colors.slate500, fontSize: 11 },
  scanRow: { flexDirection: 'row', alignItems: 'stretch', gap: 8 },
  // flexBasis 0 + minWidth 0: the text box's natural width must not push the Scan button out
  scanInput: { flexGrow: 1, flexShrink: 1, flexBasis: 0, minWidth: 0 },
  scanBtn: {
    minWidth: 64,
    borderWidth: 1,
    borderColor: colors.cyan700,
    backgroundColor: colors.cyan50,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 10,
    gap: 3,
  },
  glyph: { flexDirection: 'row', gap: 1 },
  scanBtnText: { color: colors.cyan700, fontSize: 11, fontWeight: '800' },
  stepper: {
    flexDirection: 'row',
    alignItems: 'stretch',
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    backgroundColor: '#fff',
    overflow: 'hidden',
  },
  stepperBtn: { width: 44, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.slate50 },
  stepperBtnText: { color: colors.slate700, fontSize: 20, fontWeight: '800' },
  // flexBasis 0 + minWidth 0: a text box's natural width must not push the + button out
  stepperInput: {
    flexGrow: 1,
    flexShrink: 1,
    flexBasis: 0,
    minWidth: 0,
    textAlign: 'center',
    fontSize: 15,
    fontWeight: '800',
    color: colors.slate900,
    paddingVertical: 10,
  },
  confirmLine: { color: colors.slate700, fontSize: 13, marginTop: 4 },
});
