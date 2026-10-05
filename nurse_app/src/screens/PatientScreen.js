// One patient's chart: the nurse app's twin of the ward dashboard's Patient
// Details. Tabs for the overview, consultant orders, the I/O chart,
// medications, oxygen therapy, lab investigations, infusions, blood
// transfusion, assessment scales, bedside alerts and the nursing plan (this
// shift's tasks and the nursing care plan).
//
// Every action answers with the whole refreshed chart, which simply replaces
// what is on screen - so the app always shows the server's view.

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
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import { createPatientClient } from '../api/patient';
import { Banner, CountBadge, Tag } from '../components/ui';
import OverviewTab from '../components/patient/OverviewTab';
import OrdersTab from '../components/patient/OrdersTab';
import IoTab from '../components/patient/IoTab';
import MedsTab from '../components/patient/MedsTab';
import InfusionTab from '../components/patient/InfusionTab';
import TransfusionTab from '../components/patient/TransfusionTab';
import AlertsTab from '../components/patient/AlertsTab';
import NursingPlanTab from '../components/patient/NursingPlanTab';
import LabsTab from '../components/patient/LabsTab';
import OxygenTab from '../components/patient/OxygenTab';
import AssessTab from '../components/patient/AssessTab';

const REFRESH_INTERVAL_MS = 60000;

function ewsTone(ews) {
  if (ews == null) return 'muted';
  if (ews >= 5) return 'critical';
  if (ews >= 3) return 'warning';
  return 'good';
}

export default function PatientScreen({ session, bed, onClose, onSessionExpired, onChanged }) {
  const insets = useSafeAreaInsets();
  const client = useMemo(() => createPatientClient(session, bed), [session, bed]);

  const [chart, setChart] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadingDay, setLoadingDay] = useState(false);
  const [error, setError] = useState(null);
  const [tab, setTab] = useState('overview');
  const [ioDay, setIoDay] = useState(null); // null = today's chart day
  const [toast, setToast] = useState(null);
  const toastAnim = useRef(new Animated.Value(0)).current;
  const toastTimer = useRef(null);
  const toastId = useRef(0);
  const mounted = useRef(true);
  const scrollRef = useRef(null);

  const handleError = useCallback((e) => {
    if (e?.status === 401) {
      onSessionExpired?.();
      return 'Your session has expired. Please log in again.';
    }
    return e?.message ?? 'Something went wrong.';
  }, [onSessionExpired]);

  const load = useCallback(async ({ day = ioDay, refresh = false } = {}) => {
    if (refresh) setRefreshing(true);
    try {
      const res = await client.load({ ioDay: day });
      if (!mounted.current) return;
      setChart(res.patient);
      setError(null);
    } catch (e) {
      if (mounted.current) setError(handleError(e));
    } finally {
      if (mounted.current) {
        setLoading(false);
        setRefreshing(false);
      }
    }
  }, [client, ioDay, handleError]);

  useEffect(() => {
    mounted.current = true;
    load();
    return () => {
      mounted.current = false;
      if (toastTimer.current) clearTimeout(toastTimer.current);
    };
    // Load once on open; the interval below keeps it fresh
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  useEffect(() => {
    if (session?.demo) return undefined;
    const timer = setInterval(() => load(), REFRESH_INTERVAL_MS);
    return () => clearInterval(timer);
  }, [load, session]);

  // Android back button closes the chart
  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onClose();
      return true;
    });
    return () => sub.remove();
  }, [onClose]);

  function showToast(message, isError = false) {
    if (!message) return;
    // A newer toast must not be cleared by an older one still fading out
    const id = ++toastId.current;
    if (toastTimer.current) clearTimeout(toastTimer.current);
    toastAnim.stopAnimation();
    setToast({ message, isError });
    Animated.timing(toastAnim, { toValue: 1, duration: 180, useNativeDriver: true }).start();
    toastTimer.current = setTimeout(() => {
      Animated.timing(toastAnim, { toValue: 0, duration: 220, useNativeDriver: true }).start(() => {
        if (toastId.current === id) setToast(null);
      });
    }, 3200);
  }

  /**
   * Run an action. On success the chart is replaced with the server's and a
   * toast confirms it; on failure the error goes back to the sheet that
   * asked, which stays open.
   */
  const perform = useCallback(async (action) => {
    try {
      const res = await action(client);
      if (!mounted.current) return { ok: true };
      setChart(res.patient);
      setIoDay(res.patient?.io?.day?.is_current ? null : res.patient?.io?.day?.key ?? null);
      showToast(res.message);
      onChanged?.();
      return { ok: true };
    } catch (e) {
      return { ok: false, error: handleError(e) };
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [client, handleError, onChanged]);

  async function loadIoDay(day) {
    setLoadingDay(true);
    setIoDay(day);
    await load({ day });
    setLoadingDay(false);
  }

  function goTab(key) {
    setTab(key);
    scrollRef.current?.scrollTo?.({ y: 0, animated: false });
  }

  const p = chart?.patient;
  const b = chart?.badges;
  const ews = chart?.vitals?.latest?.ews;

  const tabs = b
    ? [
        { key: 'overview', label: 'Overview' },
        { key: 'orders', label: 'Orders', count: b.orders_open, tone: b.orders_stat ? 'critical' : 'info' },
        { key: 'io', label: 'I/O', dot: !!b.io_level, tone: b.io_level === 'critical' ? 'critical' : 'warning' },
        { key: 'meds', label: 'Meds', count: b.meds_overdue, tone: 'critical' },
        // Oxygen, Labs and Assess show only when the server sends them (an older server does not)
        chart.oxygen ? { key: 'oxygen', label: 'Oxygen', dot: !!b.oxygen_level, tone: b.oxygen_level === 'critical' ? 'critical' : 'warning' } : null,
        // Only while the ward has Lab Investigations switched on
        chart.labs?.enabled
          ? { key: 'labs', label: 'Labs', count: b.labs_review, tone: b.labs_overdue || b.labs_critical ? 'critical' : 'warning' }
          : null,
        { key: 'infusion', label: 'Infusion', count: b.infusion_alarms, tone: 'critical' },
        {
          key: 'transfusion',
          label: 'Transfusion',
          count: b.transfusions_running || b.transfusions_pending,
          tone: b.transfusions_running || b.transfusion_critical ? 'critical' : 'warning',
        },
        chart.assessments?.scales?.length
          ? { key: 'assess', label: 'Assess', count: b.assess_overdue || b.assess_due, tone: b.assess_overdue ? 'critical' : 'warning' }
          : null,
        { key: 'alerts', label: 'Alerts', count: b.alerts_pending, tone: 'warning' },
        {
          key: 'plan',
          label: 'Nursing plan',
          count: b.shift_overdue || b.care_plan_due,
          tone: b.shift_overdue ? 'critical' : 'info',
        },
      ].filter(Boolean)
    : [];

  return (
    <View style={styles.root}>
      <StatusBar barStyle="light-content" backgroundColor={colors.headerStart} />
      <SafeAreaView edges={['top']} style={styles.headerArea}>
        <View style={styles.header}>
          <View style={styles.headerRow}>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={onClose}>
              <Text style={styles.iconBtnText}>‹</Text>
            </TouchableOpacity>
            <View style={{ flex: 1, paddingHorizontal: 10 }}>
              <Text style={styles.eyebrow} numberOfLines={1}>
                BED {bed.number}
                {p?.ward ? ` · ${p.ward.toUpperCase()}` : ''}
              </Text>
              <Text style={styles.name} numberOfLines={1}>
                {p?.name ?? bed.patient_name}
              </Text>
            </View>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={() => load({ refresh: true })}>
              {refreshing ? <ActivityIndicator size="small" color="#fff" /> : <Text style={[styles.iconBtnText, { fontSize: 18 }]}>⟳</Text>}
            </TouchableOpacity>
          </View>

          <View style={styles.metaRow}>
            <Text style={styles.meta} numberOfLines={1}>
              MRN {p?.mrn ?? bed.mrn ?? '—'}
              {p?.gender || p?.age ? `  ·  ${p?.gender ?? '-'}, ${p?.age ?? '-'} yrs` : ''}
              {p?.stay_label ? `  ·  ${p.stay_label}` : ''}
            </Text>
            {ews != null ? <Tag label={`EWS ${ews}`} toneName={ewsTone(ews)} solid /> : null}
          </View>

          {p ? (
            <View style={styles.flags}>
              {p.vip ? <Tag label={p.vip} toneName="warning" solid /> : null}
              {p.allergies.filter((a) => !a.resolved).slice(0, 3).map((a, i) => (
                <Tag key={i} label={`⚠ ${a.name}${a.severity ? ` (${a.severity})` : ''}`} toneName="critical" solid />
              ))}
              {p.nbm ? <Tag label="NIL BY MOUTH" toneName="critical" /> : null}
              {p.isolation ? <Tag label={p.isolation.toUpperCase()} toneName="warning" /> : null}
              {p.fall_risk === 'High' || p.fall_risk === 'FR Alert Active' ? <Tag label="FALL RISK" toneName="warning" /> : null}
              {p.status === 'pending_discharge' ? <Tag label="PENDING DISCHARGE" toneName="info" /> : null}
            </View>
          ) : null}
        </View>
      </SafeAreaView>

      {tabs.length ? (
        <View style={styles.tabBar}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tabRow}>
            {tabs.map((t) => {
              const active = tab === t.key;
              return (
                <TouchableOpacity
                  key={t.key}
                  activeOpacity={0.85}
                  onPress={() => goTab(t.key)}
                  style={[styles.tab, active && styles.tabActive]}
                >
                  <Text style={[styles.tabText, active && styles.tabTextActive]}>{t.label}</Text>
                  {t.count ? <CountBadge count={t.count} toneName={t.tone} /> : null}
                  {t.dot ? <CountBadge dot toneName={t.tone} /> : null}
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>
      ) : null}

      <ScrollView
        ref={scrollRef}
        style={styles.body}
        contentContainerStyle={[styles.bodyContent, { paddingBottom: insets.bottom + 32 }]}
        keyboardShouldPersistTaps="handled"
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load({ refresh: true })} />}
      >
        {error ? (
          <Banner toneName="critical" title="Could not refresh this chart" text={`${error} Tap to retry.`} onPress={() => load({ refresh: true })} />
        ) : null}

        {loading && !chart ? (
          <View style={styles.loading}>
            <ActivityIndicator size="large" color={colors.cyan700} />
            <Text style={styles.loadingText}>Opening the chart...</Text>
          </View>
        ) : null}

        {chart ? (
          <>
            {tab === 'overview' ? <OverviewTab chart={chart} goTab={goTab} /> : null}
            {tab === 'orders' ? <OrdersTab chart={chart} perform={perform} /> : null}
            {tab === 'io' ? <IoTab chart={chart} perform={perform} loadIoDay={loadIoDay} loadingDay={loadingDay} /> : null}
            {tab === 'meds' ? <MedsTab chart={chart} perform={perform} /> : null}
            {tab === 'oxygen' && chart.oxygen ? <OxygenTab chart={chart} perform={perform} /> : null}
            {tab === 'labs' && chart.labs ? <LabsTab chart={chart} perform={perform} /> : null}
            {tab === 'infusion' ? <InfusionTab chart={chart} /> : null}
            {tab === 'transfusion' ? <TransfusionTab chart={chart} perform={perform} /> : null}
            {tab === 'assess' && chart.assessments ? <AssessTab chart={chart} perform={perform} /> : null}
            {tab === 'alerts' ? <AlertsTab chart={chart} perform={perform} /> : null}
            {tab === 'plan' ? <NursingPlanTab chart={chart} perform={perform} goTab={goTab} /> : null}
            <Text style={styles.footnote}>
              {session?.demo ? 'Demo data · changes are not saved' : `Updated ${chart.generated_label}`}
            </Text>
          </>
        ) : null}
      </ScrollView>

      {toast ? (
        <Animated.View
          style={[
            styles.toast,
            { pointerEvents: 'none' },
            { bottom: insets.bottom + 18, opacity: toastAnim, transform: [{ translateY: toastAnim.interpolate({ inputRange: [0, 1], outputRange: [16, 0] }) }] },
            toast.isError && { backgroundColor: colors.rose700 },
          ]}
        >
          <Text style={styles.toastText}>{toast.message}</Text>
        </Animated.View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.surface },
  headerArea: { backgroundColor: colors.headerStart },
  header: { backgroundColor: '#0b2f44', paddingHorizontal: 14, paddingTop: 10, paddingBottom: 12 },
  headerRow: { flexDirection: 'row', alignItems: 'center' },
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
  metaRow: { marginTop: 10, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  meta: { flex: 1, color: 'rgba(207,250,254,0.85)', fontSize: 12 },
  flags: { marginTop: 8, flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  tabBar: {
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: colors.line,
  },
  tabRow: { paddingHorizontal: 10, paddingVertical: 8, gap: 6 },
  tab: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: colors.slate100,
    borderRadius: radius.pill,
    paddingHorizontal: 13,
    paddingVertical: 8,
  },
  tabActive: { backgroundColor: colors.slate900 },
  tabText: { color: colors.slate600, fontSize: 13, fontWeight: '800' },
  tabTextActive: { color: '#fff' },
  body: { flex: 1 },
  bodyContent: { padding: 14 },
  loading: { paddingVertical: 48, alignItems: 'center' },
  loadingText: { marginTop: 12, color: colors.muted, fontSize: 13, fontWeight: '600' },
  footnote: { marginTop: 4, textAlign: 'center', color: colors.mutedSoft, fontSize: 10 },
  toast: {
    position: 'absolute',
    left: 16,
    right: 16,
    backgroundColor: colors.slate900,
    borderRadius: radius.md,
    paddingHorizontal: 14,
    paddingVertical: 12,
    shadowColor: '#000',
    shadowOpacity: 0.2,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 6,
  },
  toastText: { color: '#fff', fontSize: 13, fontWeight: '700' },
});
