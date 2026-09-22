// Orders tab: write an order for the ward (optionally with a fluid
// restriction, which becomes the I/O fluid plan), see where every order
// stands, and cancel one of your own that is still open.

import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TextInput } from 'react-native';
import { colors, radius } from '../../theme';
import { Badge, Button, Chip, Empty, Section } from './parts';

const URGENCIES = [
  { key: 'stat', label: 'STAT', tone: 'critical' },
  { key: 'urgent', label: 'Urgent', tone: 'warning' },
  { key: 'routine', label: 'Routine', tone: 'info' },
];
const URGENCY_TONES = { stat: 'critical', urgent: 'warning', routine: 'info' };
const STATUS_TONES = { done: 'good', cancelled: 'muted', open: 'info' };

const LIMIT_PRESETS = [1000, 1200, 1500, 2000];
const URINE_PRESETS = [20, 30, 40];

/** Whole numbers only; empty stays empty. */
function numberOrNull(text) {
  const digits = String(text ?? '').replace(/[^0-9]/g, '');
  return digits === '' ? null : parseInt(digits, 10);
}

function OrderCard({ order, onCancel }) {
  const [cancelling, setCancelling] = useState(false);
  const [reason, setReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  async function confirm() {
    setBusy(true);
    setError(null);
    try {
      await onCancel(order.id, reason);
    } catch (e) {
      setError(e?.message ?? 'Could not cancel the order.');
      setBusy(false);
    }
  }

  return (
    <Section style={order.status !== 'open' && { backgroundColor: colors.slate50 }}>
      <View style={styles.orderHead}>
        <Badge label={order.urgency_label.toUpperCase()} toneName={URGENCY_TONES[order.urgency] ?? 'info'} solid={order.urgency === 'stat' && order.status === 'open'} />
        {order.status !== 'open' ? <Badge label={order.status_label.toUpperCase()} toneName={STATUS_TONES[order.status] ?? 'muted'} /> : null}
        <Text style={styles.orderWhen}>{order.ordered_label}</Text>
      </View>
      <Text style={styles.instruction}>{order.instruction}</Text>
      {order.fluid_restriction ? <Text style={styles.restriction}>{order.fluid_restriction}</Text> : null}
      <Text style={styles.meta}>
        {order.is_mine ? 'You' : order.consultant ?? 'Consultant not recorded'}
        {order.status === 'open'
          ? `  ·  ${order.assigned_nurse ? `with ${order.assigned_nurse}` : 'no nurse assigned yet'}`
          : ''}
      </Text>
      {order.status !== 'open' ? (
        <Text style={styles.meta}>
          {order.status_label} {order.closed_label}{order.closed_by ? ` by ${order.closed_by}` : ''}
          {order.outcome_note ? `: ${order.outcome_note}` : ''}
        </Text>
      ) : null}

      {order.can_cancel && !cancelling ? (
        <Button label="Cancel order" kind="secondary" small onPress={() => setCancelling(true)} style={styles.cancelBtn} />
      ) : null}
      {cancelling ? (
        <View style={styles.cancelBox}>
          <TextInput
            value={reason}
            onChangeText={setReason}
            placeholder="Why is it being cancelled?"
            placeholderTextColor={colors.mutedSoft}
            style={styles.input}
            maxLength={900}
          />
          {error ? <Text style={styles.error}>{error}</Text> : null}
          <View style={styles.row}>
            <Button label="Keep it" kind="secondary" small onPress={() => { setCancelling(false); setError(null); }} disabled={busy} />
            <Button label="Cancel the order" kind="danger" small onPress={confirm} busy={busy} disabled={!reason.trim()} />
          </View>
        </View>
      ) : null}
    </Section>
  );
}

export default function OrdersTab({ orders, onCreate, onCancel, composeRequest }) {
  const [composing, setComposing] = useState(false);
  const [instruction, setInstruction] = useState('');
  const [urgency, setUrgency] = useState('routine');
  const [fluid, setFluid] = useState(false);
  const [limit, setLimit] = useState('');
  const [urineMin, setUrineMin] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const [showClosed, setShowClosed] = useState(false);

  // "Order a fluid restriction" on the I/O tab opens the form ready for one
  useEffect(() => {
    if (!composeRequest) return;
    setComposing(true);
    setFluid(true);
    setInstruction((text) => text || 'Fluid restriction. Strict input/output chart.');
  }, [composeRequest]);

  function reset() {
    setComposing(false);
    setInstruction('');
    setUrgency('routine');
    setFluid(false);
    setLimit('');
    setUrineMin('');
    setError(null);
  }

  async function submit() {
    const fluidLimit = fluid ? numberOrNull(limit) : null;
    const urine = fluid ? numberOrNull(urineMin) : null;
    if (!instruction.trim()) {
      setError('Write the order.');
      return;
    }
    if (fluid && fluidLimit == null && urine == null) {
      setError('Enter an intake limit or a urine target, or untick the fluid restriction.');
      return;
    }

    setBusy(true);
    setError(null);
    try {
      await onCreate({
        instruction: instruction.trim(),
        urgency,
        fluid_limit_ml: fluidLimit,
        urine_min_ml_per_hour: urine,
      });
      reset();
    } catch (e) {
      setError(e?.message ?? 'Could not send the order.');
    } finally {
      setBusy(false);
    }
  }

  return (
    <View>
      {composing ? (
        <Section title="NEW ORDER">
          <TextInput
            value={instruction}
            onChangeText={setInstruction}
            placeholder="e.g. Repeat FBC and renal profile at 06:00"
            placeholderTextColor={colors.mutedSoft}
            multiline
            maxLength={2000}
            style={[styles.input, styles.inputMultiline]}
          />

          <Text style={styles.label}>URGENCY</Text>
          <View style={styles.chips}>
            {URGENCIES.map((u) => (
              <Chip key={u.key} label={u.label} toneName={u.tone} selected={urgency === u.key} onPress={() => setUrgency(u.key)} />
            ))}
          </View>

          <View style={[styles.fluidBox, fluid && styles.fluidBoxOn]}>
            <Chip
              label={fluid ? '✓ Includes a fluid restriction' : 'Add a fluid restriction'}
              toneName="indigo"
              selected={fluid}
              onPress={() => setFluid((f) => !f)}
            />
            {fluid ? (
              <>
                <Text style={styles.hint}>Becomes the patient's fluid plan on the I/O chart as soon as it is sent.</Text>
                <Text style={styles.label}>INTAKE UP TO (mL PER DAY)</Text>
                <View style={styles.chips}>
                  {LIMIT_PRESETS.map((v) => (
                    <Chip key={v} label={v.toLocaleString('en-US')} toneName="indigo" selected={numberOrNull(limit) === v} onPress={() => setLimit(String(v))} />
                  ))}
                </View>
                <TextInput
                  value={limit}
                  onChangeText={setLimit}
                  keyboardType="number-pad"
                  placeholder="Or type the limit"
                  placeholderTextColor={colors.mutedSoft}
                  style={[styles.input, styles.inputShort]}
                />
                <Text style={styles.label}>URINE AT LEAST (mL/h)</Text>
                <View style={styles.chips}>
                  {URINE_PRESETS.map((v) => (
                    <Chip key={v} label={String(v)} toneName="indigo" selected={numberOrNull(urineMin) === v} onPress={() => setUrineMin(String(v))} />
                  ))}
                </View>
                <TextInput
                  value={urineMin}
                  onChangeText={setUrineMin}
                  keyboardType="number-pad"
                  placeholder="Optional"
                  placeholderTextColor={colors.mutedSoft}
                  style={[styles.input, styles.inputShort]}
                />
              </>
            ) : null}
          </View>

          {error ? <Text style={styles.error}>{error}</Text> : null}
          <View style={styles.row}>
            <Button label="Discard" kind="secondary" onPress={reset} disabled={busy} />
            <Button label="Send to the ward" onPress={submit} busy={busy} style={{ flex: 1 }} />
          </View>
        </Section>
      ) : (
        <Button label="+ New order" onPress={() => setComposing(true)} style={{ marginBottom: 12 }} />
      )}

      <Text style={styles.groupTitle}>OPEN ({orders.open.length})</Text>
      {orders.open.length === 0 ? (
        <Section>
          <Empty>No open orders for this patient.</Empty>
        </Section>
      ) : (
        orders.open.map((order) => <OrderCard key={order.id} order={order} onCancel={onCancel} />)
      )}

      {orders.closed.length ? (
        <Button
          label={`${showClosed ? 'Hide' : 'Show'} closed orders (${orders.closed.length})`}
          kind="ghost"
          small
          onPress={() => setShowClosed((s) => !s)}
          style={{ alignSelf: 'center', marginBottom: 8 }}
        />
      ) : null}
      {showClosed ? orders.closed.map((order) => <OrderCard key={order.id} order={order} onCancel={onCancel} />) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  orderHead: { flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' },
  orderWhen: { marginLeft: 'auto', fontSize: 11, color: colors.muted },
  instruction: { marginTop: 8, fontSize: 14, fontWeight: '600', color: colors.slate900, lineHeight: 20 },
  restriction: {
    marginTop: 6,
    alignSelf: 'flex-start',
    fontSize: 12,
    fontWeight: '700',
    color: colors.indigo700,
    backgroundColor: colors.indigo50,
    borderRadius: radius.sm,
    paddingHorizontal: 8,
    paddingVertical: 3,
    overflow: 'hidden',
  },
  meta: { marginTop: 6, fontSize: 11, color: colors.muted },
  cancelBtn: { marginTop: 10, alignSelf: 'flex-start' },
  cancelBox: { marginTop: 10, gap: 8 },
  label: { marginTop: 12, marginBottom: 6, fontSize: 9, fontWeight: '800', letterSpacing: 1.4, color: colors.mutedSoft },
  hint: { marginTop: 8, fontSize: 12, color: colors.indigo700 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  input: {
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    backgroundColor: '#fff',
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: colors.slate900,
  },
  inputMultiline: { minHeight: 90, textAlignVertical: 'top' },
  inputShort: { marginTop: 8, maxWidth: 200 },
  fluidBox: {
    marginTop: 14,
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    padding: 10,
  },
  fluidBoxOn: { borderColor: colors.indigo100, backgroundColor: colors.indigo50 },
  error: { marginTop: 10, fontSize: 12, fontWeight: '600', color: colors.rose700 },
  row: { flexDirection: 'row', gap: 8, marginTop: 12 },
  groupTitle: { fontSize: 10, fontWeight: '800', letterSpacing: 2, color: colors.muted, marginBottom: 8 },
});
