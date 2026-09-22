// Consultant orders: what the doctors asked for, who has it this shift, and
// done / cancel / hand over - the ward dashboard's Consultant Orders tab.

import React, { useEffect, useMemo, useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import Sheet from '../Sheet';
import { Banner, Button, Card, Chip, EmptyNote, SectionTitle, Tag, shared } from '../ui';

const URGENCY_TONE = { stat: 'critical', urgent: 'warning', routine: 'default' };

export default function OrdersTab({ chart, perform }) {
  const orders = chart.orders;
  const [sheet, setSheet] = useState(null); // { kind: 'done'|'cancel'|'new'|'handover', order? }
  const [showClosed, setShowClosed] = useState(false);

  const current = orders.slots?.current;
  const next = orders.slots?.next;

  return (
    <View>
      <Card title="SHIFT">
        <View style={styles.slotRow}>
          <View style={styles.slot}>
            <Text style={styles.slotLabel}>NOW · {current?.label ?? '—'}</Text>
            <Text style={styles.slotNurse}>{current?.nurse ?? 'No nurse rostered'}</Text>
            <Text style={shared.meta}>{current?.time ?? ''}</Text>
          </View>
          <View style={[styles.slot, { borderLeftWidth: 1, borderLeftColor: colors.slate200, paddingLeft: 12 }]}>
            <Text style={styles.slotLabel}>NEXT · {next?.label ?? '—'}</Text>
            <Text style={styles.slotNurse}>{next?.nurse ?? 'No nurse rostered'}</Text>
            <Text style={shared.meta}>{next?.time ?? ''}</Text>
          </View>
        </View>
        <View style={styles.actionsRow}>
          <Button label="+ New order" small flex onPress={() => setSheet({ kind: 'new' })} />
          <Button
            label="Hand over"
            kind="outline"
            small
            flex
            disabled={orders.open.length === 0}
            onPress={() => setSheet({ kind: 'handover' })}
          />
        </View>
      </Card>

      <SectionTitle eyebrow="CONSULTANT ORDERS" title={`Open (${orders.open.length})`} />

      {orders.open.length === 0 ? (
        <Card>
          <EmptyNote text="No open orders for this patient." />
        </Card>
      ) : (
        orders.open.map((order) => (
          <Card key={order.id} style={order.urgency === 'stat' && styles.statCard}>
            <View style={styles.orderHead}>
              <Tag label={order.urgency_label.toUpperCase()} toneName={URGENCY_TONE[order.urgency]} solid={order.urgency === 'stat'} />
              {order.is_mine ? <Tag label="YOURS" toneName="info" /> : null}
              <Text style={[shared.meta, { marginLeft: 'auto' }]}>{order.ordered_label}</Text>
            </View>
            <Text style={styles.instruction}>{order.instruction}</Text>
            <Text style={styles.orderMeta}>
              {order.consultant ?? 'Consultant'}
              {order.entered_by ? ` · entered by ${order.entered_by}` : ''}
            </Text>
            <Text style={styles.orderMeta}>
              With {order.assigned_nurse ?? 'no nurse yet'}
              {order.slot_label ? ` (${order.slot_label})` : ''}
              {order.last_handover_label ? ` · passed ${order.last_handover_label}` : ''}
            </Text>
            <View style={styles.actionsRow}>
              <Button label="Done" kind="success" small flex onPress={() => setSheet({ kind: 'done', order })} />
              <Button label="Cancel order" kind="outline" small flex onPress={() => setSheet({ kind: 'cancel', order })} />
            </View>
          </Card>
        ))
      )}

      {orders.closed.length > 0 ? (
        <>
          <TouchableOpacity activeOpacity={0.7} onPress={() => setShowClosed((v) => !v)}>
            <SectionTitle
              eyebrow="HISTORY"
              title={`Closed (${orders.closed.length})`}
              right={<Text style={styles.toggle}>{showClosed ? 'Hide' : 'Show'}</Text>}
            />
          </TouchableOpacity>
          {showClosed
            ? orders.closed.map((order) => (
                <Card key={order.id} style={{ opacity: 0.9 }}>
                  <View style={styles.orderHead}>
                    <Tag label={order.status_label.toUpperCase()} toneName={order.status === 'done' ? 'good' : 'muted'} />
                    <Text style={[shared.meta, { marginLeft: 'auto' }]}>{order.closed_label}</Text>
                  </View>
                  <Text style={[styles.instruction, order.status !== 'done' && styles.struck]}>{order.instruction}</Text>
                  <Text style={styles.orderMeta}>
                    {order.closed_by ? `By ${order.closed_by}` : ''}
                    {order.outcome_note ? ` · ${order.outcome_note}` : ''}
                  </Text>
                </Card>
              ))
            : null}
        </>
      ) : null}

      <CloseOrderSheet sheet={sheet} onClose={() => setSheet(null)} perform={perform} />
      <NewOrderSheet visible={sheet?.kind === 'new'} orders={orders} onClose={() => setSheet(null)} perform={perform} />
      <HandoverSheet visible={sheet?.kind === 'handover'} orders={orders} onClose={() => setSheet(null)} perform={perform} />
    </View>
  );
}

function CloseOrderSheet({ sheet, onClose, perform }) {
  const visible = sheet?.kind === 'done' || sheet?.kind === 'cancel';
  const cancelling = sheet?.kind === 'cancel';
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  function close() {
    setNote('');
    setError(null);
    onClose();
  }

  async function save() {
    if (cancelling && !note.trim()) {
      setError('Give a reason for cancelling the order.');
      return;
    }
    setBusy(true);
    setError(null);
    const body = { outcome_note: note.trim() || null };
    const result = await perform((c) =>
      cancelling ? c.cancelOrder(sheet.order.id, body) : c.completeOrder(sheet.order.id, body)
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
      eyebrow="CONSULTANT ORDER"
      title={cancelling ? 'Cancel this order' : 'Mark order done'}
      subtitle={sheet?.order?.instruction}
      footer={
        <>
          <Button label="Back" kind="secondary" flex onPress={close} disabled={busy} />
          <Button
            label={cancelling ? 'Cancel order' : 'Mark done'}
            kind={cancelling ? 'danger' : 'success'}
            flex
            busy={busy}
            onPress={save}
          />
        </>
      }
    >
      <Text style={shared.label}>{cancelling ? 'REASON (REQUIRED)' : 'OUTCOME (OPTIONAL)'}</Text>
      <TextInput
        style={[shared.input, shared.textarea]}
        value={note}
        onChangeText={setNote}
        multiline
        maxLength={1000}
        placeholder={cancelling ? 'e.g. Consultant withdrew the order' : 'e.g. Bloods sent, Hb 11.2'}
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function NewOrderSheet({ visible, orders, onClose, perform }) {
  const [instruction, setInstruction] = useState('');
  const [urgency, setUrgency] = useState('routine');
  const [consultantId, setConsultantId] = useState(orders.default_consultant_id ?? null);
  const [search, setSearch] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  // The patient's own consultant is picked each time the sheet opens
  useEffect(() => {
    if (visible) setConsultantId(orders.default_consultant_id ?? null);
  }, [visible]);

  const consultants = useMemo(() => {
    const q = search.trim().toLowerCase();
    const list = orders.consultants ?? [];
    const filtered = q ? list.filter((c) => c.name.toLowerCase().includes(q)) : list.filter((c) => c.is_patients);
    return (filtered.length ? filtered : list).slice(0, 12);
  }, [orders.consultants, search]);

  function close() {
    setInstruction('');
    setUrgency('routine');
    setSearch('');
    setError(null);
    onClose();
  }

  async function save() {
    if (!instruction.trim()) {
      setError('Write down what the consultant ordered.');
      return;
    }
    if (!consultantId) {
      setError('Choose the consultant who gave the order.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.addOrder({ instruction: instruction.trim(), urgency, consultant_id: consultantId })
    );
    setBusy(false);
    if (result.ok) close();
    else setError(result.error);
  }

  const selected = (orders.consultants ?? []).find((c) => c.id === consultantId);

  return (
    <Sheet
      visible={visible}
      busy={busy}
      onClose={close}
      eyebrow="CONSULTANT ORDER"
      title="Write down an order"
      subtitle="For a verbal or phone order. It goes to the nurse rostered to this bed for the shift on now."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label="Add order" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>ORDER</Text>
      <TextInput
        style={[shared.input, shared.textarea]}
        value={instruction}
        onChangeText={setInstruction}
        multiline
        maxLength={2000}
        placeholder="e.g. Repeat FBC and U/E this afternoon"
        placeholderTextColor={colors.mutedSoft}
      />

      <Text style={shared.label}>URGENCY</Text>
      <View style={shared.chipWrap}>
        {Object.entries(orders.urgencies ?? { stat: 'STAT', urgent: 'Urgent', routine: 'Routine' }).map(([key, label]) => (
          <Chip
            key={key}
            label={label}
            selected={urgency === key}
            toneName={URGENCY_TONE[key] === 'default' ? 'info' : URGENCY_TONE[key]}
            onPress={() => setUrgency(key)}
          />
        ))}
      </View>

      <Text style={shared.label}>CONSULTANT{selected ? ` · ${selected.name}` : ''}</Text>
      <TextInput
        style={shared.input}
        value={search}
        onChangeText={setSearch}
        placeholder="Search all consultants"
        placeholderTextColor={colors.mutedSoft}
        autoCorrect={false}
      />
      <View style={[shared.chipWrap, { marginTop: 8 }]}>
        {consultants.map((c) => (
          <Chip key={c.id} small label={c.name} selected={consultantId === c.id} onPress={() => setConsultantId(c.id)} />
        ))}
      </View>
      {!search && (orders.consultants ?? []).some((c) => c.is_patients) ? (
        <Text style={shared.hint}>Showing this patient's doctors. Search to find anyone else.</Text>
      ) : null}
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function HandoverSheet({ visible, orders, onClose, perform }) {
  const [to, setTo] = useState('next');
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);
  const target = orders.slots?.[to];

  function close() {
    setTo('next');
    setNote('');
    setError(null);
    onClose();
  }

  async function save() {
    setBusy(true);
    setError(null);
    const result = await perform((c) => c.handoverOrders({ to, note: note.trim() || null }));
    setBusy(false);
    if (result.ok) close();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={visible}
      busy={busy}
      onClose={close}
      eyebrow="HANDOVER"
      title={`Pass ${orders.open.length} open ${orders.open.length === 1 ? 'order' : 'orders'} on`}
      subtitle="Each order goes to the nurse rostered to this bed for that shift, and the handover is logged."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={close} disabled={busy} />
          <Button label="Hand over" kind="dark" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>TO</Text>
      <View style={shared.chipWrap}>
        <Chip label={`Next shift (${orders.slots?.next?.label ?? '—'})`} selected={to === 'next'} onPress={() => setTo('next')} />
        <Chip label={`Shift on now (${orders.slots?.current?.label ?? '—'})`} selected={to === 'current'} onPress={() => setTo('current')} />
      </View>
      <Banner
        toneName="info"
        text={target ? `${target.nurse ?? 'No nurse rostered yet'} · ${target.name ?? target.label} ${target.time ?? ''}` : 'No shift found.'}
      />
      <Text style={shared.label}>NOTE (OPTIONAL)</Text>
      <TextInput
        style={[shared.input, shared.textarea]}
        value={note}
        onChangeText={setNote}
        multiline
        maxLength={1000}
        placeholder="e.g. Bloods due at 15:00"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  slotRow: { flexDirection: 'row', gap: 12 },
  slot: { flex: 1 },
  slotLabel: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 1.4 },
  slotNurse: { marginTop: 3, color: colors.slate900, fontSize: 14, fontWeight: '800' },
  actionsRow: { flexDirection: 'row', gap: 8, marginTop: 12 },
  statCard: { borderColor: colors.rose100, backgroundColor: '#fffafb' },
  orderHead: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  instruction: { marginTop: 8, color: colors.slate900, fontSize: 15, fontWeight: '700', lineHeight: 21 },
  struck: { textDecorationLine: 'line-through', color: colors.slate500 },
  orderMeta: { marginTop: 4, color: colors.muted, fontSize: 12 },
  toggle: { color: colors.cyan700, fontSize: 12, fontWeight: '800' },
});
