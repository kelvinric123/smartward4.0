import React, { useEffect, useMemo, useState } from 'react';
import {
  Modal,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  TextInput,
  Keyboard,
  Platform,
  ActivityIndicator,
  useWindowDimensions,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import Pill from './Pill';
import {
  CONDITION_STATUSES,
  getHandoverState,
  loadHandovers,
  submitHandovers,
  receiveHandovers,
  subscribe,
  latestOutgoingByPatient,
  pendingIncomingCount,
  formatTime,
} from '../data/handoverStore';

const CONDITION_TONES = {
  stable: { bg: colors.emerald100, color: colors.emerald700 },
  improving: { bg: colors.cyan50, color: colors.cyan700 },
  deteriorating: { bg: colors.amber100, color: colors.amber700 },
  critical: { bg: colors.rose100, color: colors.rose700 },
};

function conditionLabel(key) {
  return CONDITION_STATUSES.find((c) => c.key === key)?.label ?? null;
}

function ewsTone(ews) {
  if (ews == null) return { bg: colors.slate100, color: colors.slate600 };
  if (ews >= 5) return { bg: colors.rose500, color: '#fff' };
  if (ews >= 3) return { bg: colors.amber500, color: '#fff' };
  return { bg: colors.emerald500, color: '#fff' };
}

function useKeyboardHeight() {
  const [height, setHeight] = useState(0);
  useEffect(() => {
    const showEvt = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvt = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';
    const showSub = Keyboard.addListener(showEvt, (e) => {
      setHeight(e?.endCoordinates?.height ?? 0);
    });
    const hideSub = Keyboard.addListener(hideEvt, () => setHeight(0));
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);
  return height;
}

// One-line summary of the bed's latest observations, for quick insertion
// into the "patient condition" field.
function vitalsSummary(bed) {
  const parts = [];
  if (bed.ews_has_vitals && bed.ews != null) parts.push(`EWS ${bed.ews}`);
  const v = bed.vitals;
  if (v) {
    const bits = [];
    if (v.pulse_rate != null) bits.push(`PR ${v.pulse_rate}`);
    if (v.systolic_bp != null || v.diastolic_bp != null) {
      bits.push(`BP ${v.systolic_bp ?? '--'}/${v.diastolic_bp ?? '--'}`);
    }
    if (v.spo2 != null) bits.push(`SpO2 ${v.spo2}%`);
    if (v.respiratory_rate != null) bits.push(`RR ${v.respiratory_rate}`);
    if (v.temperature != null) bits.push(`T ${Number(v.temperature).toFixed(1)}°C`);
    if (bits.length) {
      parts.push(`Vitals${v.recorded_at_label ? ` ${v.recorded_at_label}` : ''}: ${bits.join(', ')}`);
    }
  }
  if (bed.last_hgt?.value) parts.push(`HGT ${bed.last_hgt.value}`);
  const infusions = (bed.infusions ?? []).map((i) =>
    `${i.medication_name}${i.flow_rate ? ` @ ${Number(i.flow_rate).toFixed(1)} mL/hr` : ''}` +
    `${i.status && i.status !== 'running' ? ` (${i.status})` : ''}`
  );
  if (infusions.length) parts.push(`Infusions: ${infusions.join('; ')}`);
  return parts.length ? `${parts.join('. ')}.` : '';
}

function emptyDraft(h) {
  return {
    condition_status: h?.condition_status ?? null,
    patient_condition: h?.patient_condition ?? '',
    nursing_plan: h?.nursing_plan ?? '',
  };
}

function isDirty(draft, base) {
  if (!draft) return false;
  const condition = draft.patient_condition.trim();
  const plan = draft.nursing_plan.trim();
  if (!condition && !plan) return false;
  return (
    draft.condition_status !== base.condition_status ||
    condition !== base.patient_condition.trim() ||
    plan !== base.nursing_plan.trim()
  );
}

function OutgoingStatus({ handover }) {
  if (!handover) {
    return <Text style={styles.statusMuted}>Not handed over yet</Text>;
  }
  if (handover.status === 'received') {
    return (
      <Text style={[styles.statusText, { color: colors.emerald700 }]}>
        ✓ Received by {handover.received_by?.name ?? 'next shift'} · {formatTime(handover.received_at)}
      </Text>
    );
  }
  return (
    <Text style={[styles.statusText, { color: colors.amber700 }]}>
      Sent to {handover.to_nurse?.name ?? 'next shift'} · awaiting receipt
    </Text>
  );
}

function GiveCard({ bed, draft, base, latest, suggested, autoReceiver, onChange }) {
  const ews = bed.ews_has_vitals ? bed.ews : null;
  const tone = ewsTone(ews);
  const dirty = isDirty(draft, base);

  function insertVitals() {
    const summary = vitalsSummary(bed);
    if (!summary) return;
    const current = draft.patient_condition.trim();
    onChange({ patient_condition: current ? `${current}\n${summary}` : summary });
  }

  return (
    <View style={[styles.card, dirty && styles.cardDirty]}>
      <View style={styles.cardHead}>
        <View style={{ flex: 1, paddingRight: 8 }}>
          <View style={styles.pillRow}>
            <Pill label={`BED ${bed.number}`} bg={colors.slate900} color="#fff" />
            {dirty ? <Pill label="READY TO SEND" bg={colors.cyan50} color={colors.cyan700} /> : null}
          </View>
          <Text style={styles.cardName} numberOfLines={1}>{bed.patient_name}</Text>
          <OutgoingStatus handover={latest} />
          {autoReceiver && !latest ? (
            <Text style={styles.cardMeta} numberOfLines={1}>
              → {suggested?.name ?? 'Any nurse taking over this patient'}
            </Text>
          ) : null}
        </View>
        <Pill label={ews != null ? `EWS ${ews}` : 'No vitals'} bg={tone.bg} color={tone.color} />
      </View>

      <Text style={styles.fieldLabel}>CONDITION</Text>
      <View style={styles.chipRow}>
        {CONDITION_STATUSES.map((c) => {
          const active = draft.condition_status === c.key;
          const t = CONDITION_TONES[c.key];
          return (
            <TouchableOpacity
              key={c.key}
              activeOpacity={0.85}
              onPress={() => onChange({ condition_status: active ? null : c.key })}
              style={[
                styles.chip,
                active && { backgroundColor: t.bg, borderColor: t.color },
              ]}
            >
              <Text style={[styles.chipText, active && { color: t.color }]}>{c.label}</Text>
            </TouchableOpacity>
          );
        })}
      </View>

      <View style={styles.fieldHead}>
        <Text style={styles.fieldLabel}>PATIENT CONDITION</Text>
        <TouchableOpacity onPress={insertVitals} activeOpacity={0.7} hitSlop={8}>
          <Text style={styles.linkText}>+ Latest vitals</Text>
        </TouchableOpacity>
      </View>
      <TextInput
        style={styles.textarea}
        value={draft.patient_condition}
        onChangeText={(t) => onChange({ patient_condition: t })}
        multiline
        placeholder="e.g. Alert, afebrile overnight. Pain controlled. Tolerating diet."
        placeholderTextColor={colors.mutedSoft}
        textAlignVertical="top"
        underlineColorAndroid="transparent"
      />

      <Text style={[styles.fieldLabel, { marginTop: 10 }]}>NURSING PLAN</Text>
      <TextInput
        style={styles.textarea}
        value={draft.nursing_plan}
        onChangeText={(t) => onChange({ nursing_plan: t })}
        multiline
        placeholder="e.g. 4-hourly vitals. Repeat HGT at 16:00. Turn 2-hourly. Await CT report."
        placeholderTextColor={colors.mutedSoft}
        textAlignVertical="top"
        underlineColorAndroid="transparent"
      />
    </View>
  );
}

function ReceiveCard({ handover, busy, onReceive, onOpenBed }) {
  const tone = ewsTone(handover.ews);
  const cond = conditionLabel(handover.condition_status);
  const condTone = CONDITION_TONES[handover.condition_status];
  const received = handover.status === 'received';

  return (
    <View style={[styles.card, !received && styles.cardPending]}>
      <View style={styles.cardHead}>
        <TouchableOpacity
          style={{ flex: 1, paddingRight: 8 }}
          activeOpacity={onOpenBed ? 0.7 : 1}
          disabled={!onOpenBed}
          onPress={() => onOpenBed?.(handover.patient_id)}
        >
          <View style={styles.pillRow}>
            <Pill label={`BED ${handover.bed_number ?? '--'}`} bg={colors.slate900} color="#fff" />
            {cond ? <Pill label={cond.toUpperCase()} bg={condTone.bg} color={condTone.color} /> : null}
          </View>
          <Text style={styles.cardName} numberOfLines={1}>
            {handover.patient_name ?? 'Patient'}{onOpenBed ? '  ›' : ''}
          </Text>
          <Text style={styles.cardMeta} numberOfLines={2}>
            From {handover.from_nurse?.name ?? 'previous shift'}
            {handover.from_shift || handover.to_shift
              ? `  ·  ${handover.from_shift ?? '?'} → ${handover.to_shift ?? '?'}`
              : ''}
            {`  ·  ${formatTime(handover.updated_at ?? handover.created_at)}`}
          </Text>
        </TouchableOpacity>
        {handover.ews != null ? (
          <Pill label={`EWS ${handover.ews}`} bg={tone.bg} color={tone.color} />
        ) : null}
      </View>

      <Text style={styles.fieldLabel}>PATIENT CONDITION</Text>
      <Text style={styles.bodyText}>{handover.patient_condition || '—'}</Text>

      <Text style={[styles.fieldLabel, { marginTop: 10 }]}>NURSING PLAN</Text>
      <Text style={styles.bodyText}>{handover.nursing_plan || '—'}</Text>

      {received ? (
        <View style={styles.receivedRow}>
          <Text style={styles.receivedText}>
            ✓ Received{handover.received_by ? ` by ${handover.received_by.name}` : ''} · {formatTime(handover.received_at)}
          </Text>
        </View>
      ) : (
        <TouchableOpacity
          style={[styles.receiveBtn, busy && { opacity: 0.6 }]}
          disabled={busy}
          activeOpacity={0.85}
          onPress={() => onReceive(handover.id)}
        >
          <Text style={styles.receiveBtnText}>{busy ? 'Receiving...' : 'Receive handover'}</Text>
        </TouchableOpacity>
      )}
    </View>
  );
}

export default function HandoverModal({ visible, onClose, initialTab = 'give', beds, onOpenBed }) {
  const insets = useSafeAreaInsets();
  const { height: winHeight } = useWindowDimensions();
  const kbHeight = useKeyboardHeight();

  const [tab, setTab] = useState(initialTab);
  const [hs, setHs] = useState(getHandoverState);
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState(null);

  const [receiverId, setReceiverId] = useState(null); // null = auto (next-shift nurse per bed)
  const [drafts, setDrafts] = useState({});
  const [submitting, setSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState(null);
  const [submitMessage, setSubmitMessage] = useState(null);

  const [receivingId, setReceivingId] = useState(null);
  const [receiveError, setReceiveError] = useState(null);

  useEffect(() => subscribe(() => setHs(getHandoverState())), []);

  useEffect(() => {
    if (!visible) return;
    setTab(initialTab);
    setDrafts({});
    setReceiverId(null);
    setSubmitError(null);
    setSubmitMessage(null);
    setReceiveError(null);
    setHs(getHandoverState());
    refresh();
  }, [visible, initialTab]);

  async function refresh() {
    setLoading(true);
    setLoadError(null);
    try {
      await loadHandovers();
    } catch (e) {
      setLoadError(e?.message ?? 'Could not load handovers.');
    } finally {
      setLoading(false);
    }
  }

  const patients = useMemo(() => (beds ?? []).filter((b) => b.patient_id != null), [beds]);
  const latestOutgoing = useMemo(() => latestOutgoingByPatient(hs.outgoing), [hs.outgoing]);
  const suggestedByPatient = useMemo(() => {
    const map = new Map();
    (hs.suggested_receivers ?? []).forEach((s) => map.set(s.patient_id, s.nurse));
    return map;
  }, [hs.suggested_receivers]);

  const baseFor = (patientId) => emptyDraft(latestOutgoing.get(patientId));
  const draftFor = (patientId) => drafts[patientId] ?? baseFor(patientId);

  function updateDraft(patientId, patch) {
    setSubmitMessage(null);
    setDrafts((d) => ({ ...d, [patientId]: { ...(d[patientId] ?? baseFor(patientId)), ...patch } }));
  }

  const readyBeds = patients.filter((b) => isDirty(drafts[b.patient_id], baseFor(b.patient_id)));
  const handedOverCount = patients.filter((b) => latestOutgoing.has(b.patient_id)).length;
  const pendingCount = pendingIncomingCount(hs);
  const pendingIds = (hs.incoming ?? []).filter((h) => h.status === 'pending').map((h) => h.id);
  // Pending first, then already-received (each newest first, as served)
  const incoming = useMemo(
    () => [...(hs.incoming ?? [])].sort((a, b) => Number(a.status !== 'pending') - Number(b.status !== 'pending')),
    [hs.incoming]
  );

  const nurses = useMemo(
    () => [...(hs.nurses ?? [])].sort((a, b) => Number(!!b.on_next_shift) - Number(!!a.on_next_shift)),
    [hs.nurses]
  );
  const selectedReceiver = nurses.find((n) => n.id === receiverId) ?? null;

  async function submit() {
    if (submitting || readyBeds.length === 0) return;
    Keyboard.dismiss();
    setSubmitting(true);
    setSubmitError(null);
    setSubmitMessage(null);
    try {
      await submitHandovers({
        toNurseId: receiverId,
        items: readyBeds.map((b) => ({
          ...draftFor(b.patient_id),
          patient_id: b.patient_id,
          patient_name: b.patient_name,
          mrn: b.mrn,
          bed_number: b.number,
          ward_name: b.ward_name,
          ews: b.ews_has_vitals ? b.ews : null,
        })),
      });
      setDrafts({});
      setSubmitMessage(
        `Handed over ${readyBeds.length} patient${readyBeds.length === 1 ? '' : 's'}` +
        `${selectedReceiver ? ` to ${selectedReceiver.name}` : ' to the next shift'}.`
      );
    } catch (e) {
      setSubmitError(e?.message ?? 'Could not send the handover. Please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  async function receive(ids, key) {
    if (receivingId) return;
    setReceivingId(key);
    setReceiveError(null);
    try {
      await receiveHandovers(ids);
    } catch (e) {
      setReceiveError(e?.message ?? 'Could not receive the handover. Please try again.');
    } finally {
      setReceivingId(null);
    }
  }

  const current = hs.current_shift;
  const next = hs.next_shift;
  const shiftLine = current && next
    ? `${current.shift_name ?? current.shift_code} → ${next.shift_name ?? next.shift_code}`
    : next
      ? `To ${next.shift_name ?? next.shift_code} shift`
      : 'Next shift';

  const sheetHeight = kbHeight
    ? Math.max(260, winHeight - kbHeight - insets.top - 8)
    : winHeight * 0.92;

  return (
    <Modal
      visible={visible}
      animationType="slide"
      transparent
      statusBarTranslucent
      onRequestClose={onClose}
    >
      <View style={styles.backdrop}>
        <View style={[styles.sheet, { height: sheetHeight, marginBottom: kbHeight }]}>
          <View style={styles.sheetHandle} />
          <View style={styles.sheetHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.sheetEyebrow}>SHIFT HANDOVER</Text>
              <Text style={styles.sheetTitle} numberOfLines={1}>{shiftLine}</Text>
              <Text style={styles.sheetMeta} numberOfLines={1}>
                {next?.starts_at_label ? `Next shift starts ${next.starts_at_label}` : 'Pass over condition & nursing plan'}
              </Text>
            </View>
            {loading ? <ActivityIndicator color={colors.cyan700} style={{ marginRight: 10, marginTop: 6 }} /> : null}
            <TouchableOpacity onPress={onClose} style={styles.closeBtn} activeOpacity={0.85}>
              <Text style={styles.closeBtnText}>Close</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.segment}>
            <TouchableOpacity
              activeOpacity={0.85}
              onPress={() => setTab('give')}
              style={[styles.segmentBtn, tab === 'give' && styles.segmentBtnActive]}
            >
              <Text style={[styles.segmentText, tab === 'give' && styles.segmentTextActive]}>
                Hand over · {handedOverCount}/{patients.length}
              </Text>
            </TouchableOpacity>
            <TouchableOpacity
              activeOpacity={0.85}
              onPress={() => setTab('receive')}
              style={[styles.segmentBtn, tab === 'receive' && styles.segmentBtnActive]}
            >
              <Text style={[styles.segmentText, tab === 'receive' && styles.segmentTextActive]}>
                Receive
              </Text>
              {pendingCount > 0 ? (
                <View style={styles.badge}>
                  <Text style={styles.badgeText}>{pendingCount}</Text>
                </View>
              ) : null}
            </TouchableOpacity>
          </View>

          {loadError ? (
            <TouchableOpacity style={styles.errorBanner} onPress={refresh} activeOpacity={0.85}>
              <Text style={styles.errorBannerText}>{loadError} · Tap to retry</Text>
            </TouchableOpacity>
          ) : null}

          {tab === 'give' ? (
            <>
              <ScrollView
                style={{ flex: 1 }}
                contentContainerStyle={styles.scroll}
                keyboardShouldPersistTaps="handled"
                showsVerticalScrollIndicator={false}
              >
                <View style={styles.receiverCard}>
                  <Text style={styles.fieldLabel}>RECEIVING NURSE</Text>
                  <ScrollView
                    horizontal
                    showsHorizontalScrollIndicator={false}
                    contentContainerStyle={styles.receiverRow}
                    keyboardShouldPersistTaps="handled"
                  >
                    <TouchableOpacity
                      activeOpacity={0.85}
                      onPress={() => setReceiverId(null)}
                      style={[styles.receiverChip, receiverId == null && styles.receiverChipActive]}
                    >
                      <Text style={[styles.receiverName, receiverId == null && styles.receiverNameActive]}>
                        Auto
                      </Text>
                      <Text style={[styles.receiverSub, receiverId == null && styles.receiverSubActive]}>
                        Next-shift roster
                      </Text>
                    </TouchableOpacity>
                    {nurses.map((n) => {
                      const active = n.id === receiverId;
                      return (
                        <TouchableOpacity
                          key={n.id}
                          activeOpacity={0.85}
                          onPress={() => setReceiverId(n.id)}
                          style={[styles.receiverChip, active && styles.receiverChipActive]}
                        >
                          <Text
                            style={[styles.receiverName, active && styles.receiverNameActive]}
                            numberOfLines={1}
                          >
                            {n.name}
                          </Text>
                          <Text
                            style={[styles.receiverSub, active && styles.receiverSubActive]}
                            numberOfLines={1}
                          >
                            {n.on_next_shift ? 'On next shift' : (n.designation ?? 'Nurse')}
                          </Text>
                        </TouchableOpacity>
                      );
                    })}
                  </ScrollView>
                  <Text style={styles.receiverHint}>
                    {receiverId == null
                      ? 'Each patient goes to the nurse rostered on that bed next shift. Unrostered beds can be received by whoever takes the patient over.'
                      : `All patients you send now go to ${selectedReceiver?.name ?? 'this nurse'}.`}
                  </Text>
                </View>

                {patients.length === 0 ? (
                  <View style={styles.empty}>
                    <Text style={styles.emptyTitle}>No patients to hand over</Text>
                    <Text style={styles.emptyMeta}>
                      Patients assigned to you this shift will appear here.
                    </Text>
                  </View>
                ) : (
                  patients.map((b) => (
                    <GiveCard
                      key={b.patient_id}
                      bed={b}
                      draft={draftFor(b.patient_id)}
                      base={baseFor(b.patient_id)}
                      latest={latestOutgoing.get(b.patient_id)}
                      suggested={suggestedByPatient.get(b.patient_id)}
                      autoReceiver={receiverId == null}
                      onChange={(patch) => updateDraft(b.patient_id, patch)}
                    />
                  ))
                )}
              </ScrollView>

              <View style={[styles.footer, { paddingBottom: kbHeight ? 10 : Math.max(insets.bottom, 10) }]}>
                {submitError ? <Text style={styles.footerError}>{submitError}</Text> : null}
                {submitMessage ? <Text style={styles.footerSuccess}>✓ {submitMessage}</Text> : null}
                <TouchableOpacity
                  style={[styles.submitBtn, (readyBeds.length === 0 || submitting) && { opacity: 0.45 }]}
                  disabled={readyBeds.length === 0 || submitting}
                  activeOpacity={0.85}
                  onPress={submit}
                >
                  <Text style={styles.submitBtnText}>
                    {submitting
                      ? 'Sending...'
                      : readyBeds.length === 0
                        ? 'Write a condition or plan to hand over'
                        : `Hand over ${readyBeds.length} patient${readyBeds.length === 1 ? '' : 's'}`}
                  </Text>
                </TouchableOpacity>
              </View>
            </>
          ) : (
            <ScrollView
              style={{ flex: 1 }}
              contentContainerStyle={[styles.scroll, { paddingBottom: Math.max(insets.bottom, 10) + 16 }]}
              showsVerticalScrollIndicator={false}
            >
              {receiveError ? <Text style={styles.footerError}>{receiveError}</Text> : null}

              {pendingIds.length > 1 ? (
                <TouchableOpacity
                  style={[styles.receiveAllBtn, receivingId && { opacity: 0.6 }]}
                  disabled={!!receivingId}
                  activeOpacity={0.85}
                  onPress={() => receive(pendingIds, 'all')}
                >
                  <Text style={styles.receiveAllText}>
                    {receivingId === 'all' ? 'Receiving...' : `Receive all ${pendingIds.length} handovers`}
                  </Text>
                </TouchableOpacity>
              ) : null}

              {incoming.length === 0 ? (
                <View style={styles.empty}>
                  <Text style={styles.emptyTitle}>Nothing to receive</Text>
                  <Text style={styles.emptyMeta}>
                    Handovers from the previous shift for your patients will appear here.
                  </Text>
                </View>
              ) : (
                incoming.map((h) => (
                  <ReceiveCard
                    key={h.id}
                    handover={h}
                    busy={receivingId === h.id || receivingId === 'all'}
                    onReceive={(id) => receive([id], id)}
                    onOpenBed={
                      onOpenBed && patients.some((b) => b.patient_id === h.patient_id)
                        ? onOpenBed
                        : null
                    }
                  />
                ))
              )}
            </ScrollView>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(2,6,23,0.55)',
    justifyContent: 'flex-end',
  },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    overflow: 'hidden',
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 44,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.slate300,
    marginTop: 8,
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingHorizontal: 18,
    paddingTop: 12,
    paddingBottom: 8,
  },
  sheetEyebrow: {
    color: colors.cyan700,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  sheetTitle: {
    marginTop: 2,
    color: colors.slate900,
    fontSize: 18,
    fontWeight: '800',
  },
  sheetMeta: {
    marginTop: 2,
    color: colors.muted,
    fontSize: 11,
  },
  closeBtn: {
    backgroundColor: colors.slate900,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: radius.md,
  },
  closeBtnText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
  },

  segment: {
    flexDirection: 'row',
    marginHorizontal: 14,
    marginBottom: 8,
    padding: 4,
    borderRadius: radius.md,
    backgroundColor: colors.slate100,
    gap: 4,
  },
  segmentBtn: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 9,
    borderRadius: radius.sm,
    gap: 6,
  },
  segmentBtnActive: {
    backgroundColor: colors.slate900,
  },
  segmentText: {
    fontSize: 12,
    fontWeight: '800',
    color: colors.slate600,
  },
  segmentTextActive: {
    color: '#fff',
  },
  badge: {
    minWidth: 18,
    height: 18,
    paddingHorizontal: 5,
    borderRadius: 9,
    backgroundColor: colors.rose500,
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    color: '#fff',
    fontSize: 10,
    fontWeight: '800',
  },

  errorBanner: {
    marginHorizontal: 14,
    marginBottom: 8,
    backgroundColor: colors.rose50,
    borderColor: colors.rose100,
    borderWidth: 1,
    borderRadius: radius.md,
    padding: 10,
  },
  errorBannerText: {
    color: colors.rose700,
    fontSize: 11,
    fontWeight: '700',
  },

  scroll: {
    paddingHorizontal: 14,
    paddingBottom: 16,
    gap: 12,
  },

  receiverCard: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    padding: 12,
  },
  receiverRow: {
    gap: 6,
    paddingTop: 8,
  },
  receiverChip: {
    maxWidth: 170,
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: radius.md,
    backgroundColor: colors.slate50,
    borderWidth: 1,
    borderColor: colors.slate200,
  },
  receiverChipActive: {
    backgroundColor: colors.cyan700,
    borderColor: colors.cyan700,
  },
  receiverName: {
    fontSize: 12,
    fontWeight: '800',
    color: colors.slate900,
  },
  receiverNameActive: {
    color: '#fff',
  },
  receiverSub: {
    marginTop: 1,
    fontSize: 10,
    color: colors.muted,
  },
  receiverSubActive: {
    color: colors.cyan100,
  },
  receiverHint: {
    marginTop: 8,
    fontSize: 11,
    lineHeight: 15,
    color: colors.muted,
  },

  card: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    padding: 12,
  },
  cardDirty: {
    borderColor: colors.cyan700,
  },
  cardPending: {
    borderColor: colors.amber500,
    borderLeftWidth: 4,
  },
  cardHead: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    marginBottom: 10,
  },
  pillRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
  },
  cardName: {
    marginTop: 6,
    fontSize: 15,
    fontWeight: '800',
    color: colors.slate900,
  },
  cardMeta: {
    marginTop: 2,
    fontSize: 11,
    color: colors.muted,
  },
  statusText: {
    marginTop: 2,
    fontSize: 11,
    fontWeight: '700',
  },
  statusMuted: {
    marginTop: 2,
    fontSize: 11,
    color: colors.mutedSoft,
  },

  fieldHead: {
    marginTop: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  fieldLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.8,
    color: colors.muted,
  },
  linkText: {
    fontSize: 11,
    fontWeight: '800',
    color: colors.cyan700,
  },
  chipRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    marginTop: 6,
  },
  chip: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
  },
  chipText: {
    fontSize: 11,
    fontWeight: '700',
    color: colors.slate600,
  },
  textarea: {
    marginTop: 6,
    minHeight: 72,
    maxHeight: 160,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: 10,
    fontSize: 13,
    color: colors.slate900,
  },
  bodyText: {
    marginTop: 4,
    fontSize: 13,
    lineHeight: 19,
    color: colors.slate900,
  },

  receivedRow: {
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
  },
  receivedText: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.emerald700,
  },
  receiveBtn: {
    marginTop: 12,
    paddingVertical: 12,
    borderRadius: radius.md,
    backgroundColor: colors.cyan700,
    alignItems: 'center',
  },
  receiveBtnText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '800',
  },
  receiveAllBtn: {
    paddingVertical: 12,
    borderRadius: radius.md,
    backgroundColor: colors.slate900,
    alignItems: 'center',
  },
  receiveAllText: {
    color: '#fff',
    fontSize: 13,
    fontWeight: '800',
  },

  footer: {
    paddingHorizontal: 14,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    backgroundColor: colors.surface,
  },
  footerError: {
    marginBottom: 8,
    color: colors.rose600,
    fontSize: 12,
    fontWeight: '600',
  },
  footerSuccess: {
    marginBottom: 8,
    color: colors.emerald700,
    fontSize: 12,
    fontWeight: '700',
  },
  submitBtn: {
    paddingVertical: 14,
    borderRadius: radius.lg,
    backgroundColor: colors.slate900,
    alignItems: 'center',
  },
  submitBtnText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },

  empty: {
    padding: 24,
    alignItems: 'center',
  },
  emptyTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.slate900,
  },
  emptyMeta: {
    marginTop: 6,
    fontSize: 12,
    color: colors.muted,
    textAlign: 'center',
  },
});
