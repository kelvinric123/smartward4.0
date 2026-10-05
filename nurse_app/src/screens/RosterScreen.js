// My roster: the nurse's shifts from the AI Nurse Schedule, week by week, with
// leave, public holidays and special duties (Team Leader, ...). Acknowledge a
// week once seen, ask for leave or to swap a shift, and answer a colleague's
// swap. Leave and swaps are approved by the nurse manager on the ward
// dashboard's AI Nurse Schedule.

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
  BackHandler,
  StatusBar,
  Animated,
  TextInput,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import { createScheduleClient } from '../api/schedule';
import Sheet from '../components/Sheet';
import DateRangePicker from '../components/DateRangePicker';
import { Banner, Button, Card, Chip, EmptyNote, SectionTitle, Tag, shared } from '../components/ui';

const SHIFT_STYLE = {
  AM: { bg: colors.cyan50, border: colors.cyan100, text: colors.cyan900 },
  PM: { bg: colors.amber50, border: colors.amber100, text: colors.amber700 },
  ON: { bg: colors.slate900, border: colors.slate900, text: '#fff' },
  OFF: { bg: colors.slate100, border: colors.slate200, text: colors.slate500 },
};
const ACK = {
  seen: { label: 'Seen', tone: 'good' },
  changed: { label: 'Changed since seen', tone: 'warning' },
  new: { label: 'Not acknowledged', tone: 'muted' },
};
const REQUEST_TONE = { awaiting_colleague: 'info', pending: 'warning', approved: 'good', declined: 'critical', cancelled: 'muted' };

export default function RosterScreen({ session, onClose, onSessionExpired, onChanged }) {
  const insets = useSafeAreaInsets();
  const client = useMemo(() => createScheduleClient(session), [session]);

  const [roster, setRoster] = useState(null);
  const [week, setWeek] = useState(null); // null = this week
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);
  const [leaveOpen, setLeaveOpen] = useState(false);
  const [swapDay, setSwapDay] = useState(null);
  const [declining, setDeclining] = useState(null);
  const [busyId, setBusyId] = useState(null);
  const [toast, setToast] = useState(null);
  const toastAnim = useRef(new Animated.Value(0)).current;
  const toastTimer = useRef(null);
  const toastId = useRef(0);
  const mounted = useRef(true);

  const handleError = useCallback((e) => {
    if (e?.status === 401) {
      onSessionExpired?.();
      return 'Your session has expired. Please log in again.';
    }
    return e?.message ?? 'Something went wrong.';
  }, [onSessionExpired]);

  const load = useCallback(async ({ start = week, refresh = false } = {}) => {
    if (refresh) setRefreshing(true);
    try {
      const res = await client.loadRoster({ week: start });
      if (!mounted.current) return;
      setRoster(res.roster);
      setError(null);
    } catch (e) {
      if (mounted.current) setError(handleError(e));
    } finally {
      if (mounted.current) {
        setLoading(false);
        setRefreshing(false);
      }
    }
  }, [client, week, handleError]);

  useEffect(() => {
    mounted.current = true;
    load();
    return () => {
      mounted.current = false;
      if (toastTimer.current) clearTimeout(toastTimer.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onClose();
      return true;
    });
    return () => sub.remove();
  }, [onClose]);

  function showToast(message) {
    if (!message) return;
    // A newer toast must not be cleared by an older one still fading out
    const id = ++toastId.current;
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastAnim.stopAnimation();
    setToast(message);
    Animated.timing(toastAnim, { toValue: 1, duration: 180, useNativeDriver: true }).start();
    toastTimer.current = setTimeout(() => {
      Animated.timing(toastAnim, { toValue: 0, duration: 220, useNativeDriver: true }).start(() => {
        if (toastId.current === id) setToast(null);
      });
    }, 3200);
  }

  /** Run an action; the answer carries the refreshed roster. Errors go back to the caller. */
  const perform = useCallback(async (action) => {
    try {
      const res = await action(client);
      if (!mounted.current) return { ok: true };
      setRoster(res.roster);
      showToast(res.message);
      onChanged?.();
      return { ok: true };
    } catch (e) {
      return { ok: false, error: handleError(e) };
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [client, handleError, onChanged]);

  async function quick(id, action) {
    setBusyId(id);
    const result = await perform(action);
    setBusyId(null);
    if (!result.ok) showToast(result.error);
  }

  function goWeek(start) {
    setWeek(start);
    setLoading(true);
    load({ start });
  }

  const incoming = roster?.requests.incoming ?? [];
  const mine = roster?.requests.mine ?? [];

  return (
    <View style={styles.root}>
      <StatusBar barStyle="light-content" backgroundColor={colors.headerStart} />
      <SafeAreaView edges={['top']} style={styles.headerArea}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={onClose}>
            <Text style={styles.iconBtnText}>‹</Text>
          </TouchableOpacity>
          <View style={{ flex: 1, paddingHorizontal: 10 }}>
            <Text style={styles.eyebrow}>MY ROSTER</Text>
            <Text style={styles.name} numberOfLines={1}>{roster?.nurse.name ?? session?.nurse?.name ?? 'Nurse'}</Text>
            {roster?.nurse.ward ? <Text style={styles.meta}>{roster.nurse.ward}</Text> : null}
          </View>
          <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={() => load({ refresh: true })}>
            {refreshing ? <ActivityIndicator size="small" color="#fff" /> : <Text style={[styles.iconBtnText, { fontSize: 18 }]}>⟳</Text>}
          </TouchableOpacity>
        </View>
      </SafeAreaView>

      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{ padding: 14, paddingBottom: insets.bottom + 32 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load({ refresh: true })} />}
      >
        {error ? <Banner toneName="critical" title="Could not load your roster" text={`${error} Tap to retry.`} onPress={() => load({ refresh: true })} /> : null}

        {loading && !roster ? (
          <View style={styles.loading}>
            <ActivityIndicator size="large" color={colors.cyan700} />
            <Text style={styles.loadingText}>Loading your roster...</Text>
          </View>
        ) : null}

        {roster ? (
          <>
            {incoming.map((r) => (
              <Card key={r.id} style={{ borderColor: colors.cyan100 }}>
                <Text style={styles.askTitle}>{r.from} asks you</Text>
                <Text style={styles.askText}>{r.summary}</Text>
                {r.note ? <Text style={shared.meta}>&ldquo;{r.note}&rdquo; · {r.created_label}</Text> : <Text style={shared.meta}>{r.created_label}</Text>}
                <View style={styles.askButtons}>
                  <Button label="Decline" kind="outline" small flex onPress={() => setDeclining(r)} />
                  <Button label="Accept" kind="success" small flex busy={busyId === r.id} onPress={() => quick(r.id, (c) => c.respondRequest(r.id, { accept: true }))} />
                </View>
                <Text style={[shared.hint, { marginTop: 6 }]}>Once you accept, it goes to the nurse manager for approval.</Text>
              </Card>
            ))}

            <View style={styles.weekNav}>
              <TouchableOpacity activeOpacity={0.7} onPress={() => goWeek(roster.previous_week)}>
                <Text style={styles.weekNavText}>‹ Earlier</Text>
              </TouchableOpacity>
              {week ? (
                <TouchableOpacity activeOpacity={0.7} onPress={() => goWeek(null)}>
                  <Text style={styles.weekNavText}>This week</Text>
                </TouchableOpacity>
              ) : null}
              <TouchableOpacity activeOpacity={0.7} onPress={() => goWeek(roster.next_week)}>
                <Text style={styles.weekNavText}>Later ›</Text>
              </TouchableOpacity>
            </View>

            {roster.weeks.map((w) => (
              <WeekCard
                key={w.start}
                week={w}
                busy={busyId === w.start}
                onAcknowledge={() => quick(w.start, (c) => c.acknowledge(w.start))}
                onSwap={setSwapDay}
              />
            ))}

            <SectionTitle
              eyebrow="REQUESTS"
              title="Leave and swaps"
              right={<Button label="Request leave" small onPress={() => setLeaveOpen(true)} />}
            />
            <Card>
              {mine.length === 0 ? (
                <EmptyNote text="No requests. To swap a shift, tap Swap on that day above." />
              ) : (
                mine.map((r) => (
                  <View key={r.id} style={styles.requestRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.requestText}>{r.summary}</Text>
                      <Text style={shared.meta}>
                        Asked {r.created_label}
                        {r.decided_label ? ` · ${r.status_label.toLowerCase()} ${r.decided_label}${r.decided_by ? ` by ${r.decided_by}` : ''}` : ''}
                      </Text>
                      {r.decision_note ? <Text style={[shared.meta, { color: colors.slate700 }]}>&ldquo;{r.decision_note}&rdquo;</Text> : null}
                    </View>
                    <View style={{ alignItems: 'flex-end', gap: 6 }}>
                      <Tag label={r.status_label} toneName={REQUEST_TONE[r.status] ?? 'muted'} />
                      {r.can_cancel ? (
                        <Button label="Cancel" kind="outline" small busy={busyId === `c${r.id}`} onPress={() => quick(`c${r.id}`, (c) => c.cancelRequest(r.id))} />
                      ) : null}
                    </View>
                  </View>
                ))
              )}
            </Card>
            <Text style={styles.footnote}>Leave and swaps are approved by the nurse manager on the AI Nurse Schedule.</Text>
          </>
        ) : null}
      </ScrollView>

      {roster ? (
        <>
          <LeaveSheet visible={leaveOpen} types={roster.options.leave_types} onClose={() => setLeaveOpen(false)} perform={perform} />
          <SwapSheet day={swapDay} client={client} onClose={() => setSwapDay(null)} perform={perform} handleError={handleError} />
          <DeclineSheet request={declining} onClose={() => setDeclining(null)} perform={perform} />
        </>
      ) : null}

      {toast ? (
        <Animated.View style={[styles.toast, { pointerEvents: 'none', bottom: insets.bottom + 18, opacity: toastAnim }]}>
          <Text style={styles.toastText}>{toast}</Text>
        </Animated.View>
      ) : null}
    </View>
  );
}

function WeekCard({ week, busy, onAcknowledge, onSwap }) {
  const ack = ACK[week.acknowledgement.state] ?? ACK.new;
  const t = week.totals;

  return (
    <Card>
      <View style={styles.weekHead}>
        <View style={{ flex: 1 }}>
          <Text style={styles.weekLabel}>{week.label}</Text>
          <Text style={shared.meta}>
            {t.shifts} {t.shifts === 1 ? 'shift' : 'shifts'} · {t.hours} h
            {t.nights ? ` · ${t.nights} ${t.nights === 1 ? 'night' : 'nights'}` : ''}
            {t.leave_days ? ` · ${t.leave_days} leave` : ''}
          </Text>
        </View>
        {t.shifts > 0 ? <Tag label={`${ack.label}${week.acknowledgement.at_label && week.acknowledgement.state === 'seen' ? ` ${week.acknowledgement.at_label}` : ''}`} toneName={ack.tone} /> : null}
      </View>

      {week.days.map((d) => (
        <DayRow key={d.date} day={d} onSwap={() => onSwap(d)} />
      ))}

      {t.shifts > 0 && week.acknowledgement.state !== 'seen' ? (
        <Button
          label={week.acknowledgement.state === 'changed' ? 'I have seen the changes' : 'I have seen this week'}
          kind="dark"
          busy={busy}
          onPress={onAcknowledge}
          style={{ marginTop: 10 }}
        />
      ) : null}
    </Card>
  );
}

function DayRow({ day, onSwap }) {
  const s = SHIFT_STYLE[day.shift];
  return (
    <View style={[styles.dayRow, day.is_today && styles.today, day.is_past && { opacity: 0.55 }]}>
      <View style={styles.dayDate}>
        <Text style={[styles.dayWeekday, day.is_weekend && { color: colors.rose600 }]}>{day.weekday.toUpperCase()}</Text>
        <Text style={styles.dayNumber}>{day.day}</Text>
      </View>
      <View style={{ flex: 1, gap: 3 }}>
        <View style={[shared.chipWrap, { alignItems: 'center' }]}>
          {day.leave ? (
            <Tag label={`${day.leave.code} · ${day.leave.label}`} toneName="warning" solid />
          ) : day.shift && s ? (
            <View style={[styles.shiftChip, { backgroundColor: s.bg, borderColor: s.border }]}>
              <Text style={[styles.shiftChipText, { color: s.text }]}>{day.shift}</Text>
            </View>
          ) : (
            <Text style={shared.meta}>Not rostered</Text>
          )}
          {day.time ? <Text style={styles.dayTime}>{day.time}</Text> : null}
          {day.is_today ? <Tag label="TODAY" toneName="info" /> : null}
        </View>
        {day.ward || day.beds.length || day.duties.length ? (
          <Text style={shared.meta} numberOfLines={2}>
            {[day.other_ward ? day.ward : null, day.beds.length ? `${day.beds.length} ${day.beds.length === 1 ? 'bed' : 'beds'} (${day.beds.join(', ')})` : null]
              .filter(Boolean)
              .join(' · ')}
          </Text>
        ) : null}
        {day.duties.length || day.holiday ? (
          <View style={shared.chipWrap}>
            {day.duties.map((duty) => (
              <Tag key={duty} label={duty} toneName={duty === 'Team Leader' ? 'info' : 'muted'} solid={duty === 'Team Leader'} />
            ))}
            {day.holiday ? <Tag label={`PH · ${day.holiday}`} toneName="critical" /> : null}
          </View>
        ) : null}
      </View>
      {day.swappable ? <Button label="Swap" kind="outline" small onPress={onSwap} /> : null}
    </View>
  );
}

function LeaveSheet({ visible, types, onClose, perform }) {
  const [type, setType] = useState('annual');
  const [start, setStart] = useState(null);
  const [end, setEnd] = useState(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!visible) return;
    setType('annual');
    setStart(null);
    setEnd(null);
    setNote('');
    setError(null);
  }, [visible]);

  const days = start ? Math.round((new Date(`${end ?? start}T00:00:00`) - new Date(`${start}T00:00:00`)) / 86400000) + 1 : 0;

  async function save() {
    if (!start) {
      setError('Choose the first day of leave on the calendar.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) => c.requestLeave({ leave_type: type, start_date: start, end_date: end ?? start, note: note.trim() || null }));
    setBusy(false);
    if (result.ok) onClose();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={visible}
      busy={busy}
      onClose={onClose}
      eyebrow="REQUEST LEAVE"
      title="Ask for leave"
      subtitle="It goes to the nurse manager, who approves it on the AI Nurse Schedule. The roster never books you on an approved leave day."
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label={days ? `Send (${days} ${days === 1 ? 'day' : 'days'})` : 'Send request'} flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>TYPE</Text>
      <View style={shared.chipWrap}>
        {types.map((t) => (
          <Chip key={t.key} small label={t.label} selected={type === t.key} onPress={() => setType(t.key)} />
        ))}
      </View>

      <Text style={shared.label}>DAYS</Text>
      <DateRangePicker start={start} end={end} onChange={(a, b) => { setStart(a); setEnd(b); }} />
      <Text style={shared.hint}>
        {start ? `From ${start}${end && end !== start ? ` to ${end}` : ''}. Tap another day to set the last day.` : 'Tap the first day, then the last day.'}
      </Text>

      <Text style={shared.label}>NOTE (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={note}
        onChangeText={setNote}
        maxLength={255}
        placeholder="e.g. Family wedding"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function SwapSheet({ day, client, onClose, perform, handleError }) {
  const [colleagues, setColleagues] = useState(null);
  const [loadError, setLoadError] = useState(null);
  const [chosen, setChosen] = useState(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!day) return;
    setColleagues(null);
    setLoadError(null);
    setChosen(null);
    setNote('');
    setError(null);
    let live = true;
    client
      .colleagues(day.date)
      .then((res) => live && setColleagues(res.colleagues))
      .catch((e) => live && setLoadError(handleError(e)));
    return () => {
      live = false;
    };
  }, [day, client, handleError]);

  const working = (c) => c.shift && c.shift !== 'OFF';
  const why = (c) => {
    if (c.on_leave) return `On ${c.on_leave.toLowerCase()}`;
    if (working(c) && c.shift === day?.shift) return `Also on ${c.shift}`;
    return null;
  };

  async function save() {
    if (!chosen) {
      setError('Choose the colleague to swap with.');
      return;
    }
    setBusy(true);
    setError(null);
    const result = await perform((c) =>
      c.requestSwap({
        shift_date: day.date,
        colleague_id: chosen.id,
        colleague_shift: working(chosen) ? chosen.shift : null,
        note: note.trim() || null,
      })
    );
    setBusy(false);
    if (result.ok) onClose();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={!!day}
      busy={busy}
      onClose={onClose}
      eyebrow="SWAP A SHIFT"
      title={day ? `${day.shift} on ${day.label}` : ''}
      subtitle={day ? `${day.time ?? ''}. The colleague is asked first; once they accept, the nurse manager approves it.` : null}
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label={chosen ? (working(chosen) ? `Ask to swap for their ${chosen.shift}` : 'Ask to take my shift') : 'Send request'} flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>COLLEAGUE</Text>
      {loadError ? <Text style={shared.error}>{loadError}</Text> : null}
      {!colleagues && !loadError ? <ActivityIndicator color={colors.cyan700} style={{ marginVertical: 12 }} /> : null}
      {colleagues?.length === 0 ? <EmptyNote text="No colleagues on this ward's roster." /> : null}
      {(colleagues ?? []).map((c) => {
        const blocked = why(c);
        const selected = chosen?.id === c.id;
        return (
          <TouchableOpacity
            key={c.id}
            disabled={!!blocked}
            activeOpacity={0.8}
            onPress={() => setChosen(c)}
            style={[styles.colleague, selected && styles.colleagueSelected, blocked && { opacity: 0.45 }]}
          >
            <View style={{ flex: 1 }}>
              <Text style={styles.colleagueName}>{c.name}</Text>
              <Text style={shared.meta}>
                {blocked ?? (working(c) ? `Works ${c.shift}: you would take it in return` : 'Off that day: they would take your shift')}
              </Text>
            </View>
            <Tag label={working(c) ? c.shift : c.on_leave ? 'LEAVE' : 'OFF'} toneName={working(c) ? 'info' : 'muted'} solid={selected} />
          </TouchableOpacity>
        );
      })}

      <Text style={shared.label}>NOTE (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={note}
        onChangeText={setNote}
        maxLength={255}
        placeholder="Why you need the swap"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

function DeclineSheet({ request, onClose, perform }) {
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!request) return;
    setNote('');
    setError(null);
  }, [request]);

  async function save() {
    setBusy(true);
    const result = await perform((c) => c.respondRequest(request.id, { accept: false, note: note.trim() || null }));
    setBusy(false);
    if (result.ok) onClose();
    else setError(result.error);
  }

  return (
    <Sheet
      visible={!!request}
      busy={busy}
      onClose={onClose}
      eyebrow="DECLINE SWAP"
      title={request?.summary ?? ''}
      subtitle={request ? `${request.from} will see your answer.` : null}
      footer={
        <>
          <Button label="Back" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label="Decline" kind="danger" flex busy={busy} onPress={save} />
        </>
      }
    >
      <Text style={shared.label}>REASON (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={note}
        onChangeText={setNote}
        maxLength={255}
        placeholder="e.g. Cannot do nights this week"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.surface },
  headerArea: { backgroundColor: colors.headerStart },
  header: { backgroundColor: '#0b2f44', paddingHorizontal: 14, paddingTop: 10, paddingBottom: 14, flexDirection: 'row', alignItems: 'center' },
  iconBtn: {
    width: 40,
    height: 40,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.1)',
    borderColor: 'rgba(255,255,255,0.15)',
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconBtnText: { color: '#fff', fontSize: 22, lineHeight: 24, fontWeight: '700' },
  eyebrow: { color: '#cffafe', fontSize: 10, fontWeight: '800', letterSpacing: 2.2 },
  name: { marginTop: 2, color: '#fff', fontSize: 18, fontWeight: '800' },
  meta: { marginTop: 2, color: 'rgba(207,250,254,0.85)', fontSize: 12 },
  loading: { paddingVertical: 48, alignItems: 'center' },
  loadingText: { marginTop: 12, color: colors.muted, fontSize: 13, fontWeight: '600' },
  askTitle: { color: colors.cyan700, fontSize: 11, fontWeight: '800', letterSpacing: 1.2 },
  askText: { marginTop: 4, color: colors.slate900, fontSize: 15, fontWeight: '800' },
  askButtons: { marginTop: 10, flexDirection: 'row', gap: 8 },
  weekNav: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 8, paddingHorizontal: 2 },
  weekNavText: { color: colors.cyan700, fontSize: 13, fontWeight: '800' },
  weekHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 8, marginBottom: 6 },
  weekLabel: { color: colors.slate900, fontSize: 16, fontWeight: '800' },
  dayRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8, paddingHorizontal: 6, borderTopWidth: 1, borderTopColor: colors.slate100 },
  today: { backgroundColor: colors.cyan50, borderRadius: radius.sm },
  dayDate: { width: 38, alignItems: 'center' },
  dayWeekday: { color: colors.slate500, fontSize: 10, fontWeight: '800', letterSpacing: 1 },
  dayNumber: { color: colors.slate900, fontSize: 18, fontWeight: '800' },
  shiftChip: { borderWidth: 1, borderRadius: radius.pill, paddingHorizontal: 10, paddingVertical: 3 },
  shiftChipText: { fontSize: 12, fontWeight: '800', letterSpacing: 0.5 },
  dayTime: { color: colors.slate700, fontSize: 12, fontWeight: '700' },
  requestRow: { flexDirection: 'row', gap: 10, paddingVertical: 8, borderTopWidth: 1, borderTopColor: colors.slate100 },
  requestText: { color: colors.slate900, fontSize: 13, fontWeight: '700' },
  footnote: { marginTop: 2, textAlign: 'center', color: colors.mutedSoft, fontSize: 11 },
  colleague: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    padding: 10,
    marginBottom: 6,
  },
  colleagueSelected: { borderColor: colors.cyan700, backgroundColor: colors.cyan50 },
  colleagueName: { color: colors.slate900, fontSize: 14, fontWeight: '800' },
  toast: {
    position: 'absolute',
    left: 16,
    right: 16,
    backgroundColor: colors.slate900,
    borderRadius: radius.md,
    paddingHorizontal: 14,
    paddingVertical: 12,
  },
  toastText: { color: '#fff', fontSize: 13, fontWeight: '700' },
});
