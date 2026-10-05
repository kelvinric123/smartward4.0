// Full-screen patient chart for one bed: the I/O chart, the medications, the
// consultant orders, the oxygen therapy and the lab investigations, from the
// same records the ward dashboard and the nurse app use. Opened from the bed
// card; every change answers with the refreshed chart, which is simply drawn
// again.

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Modal,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
  RefreshControl,
  ActivityIndicator,
  Keyboard,
  Platform,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../../theme';
import { createChartClient } from '../../api/chart';
import IoTab from './IoTab';
import MedsTab from './MedsTab';
import OrdersTab from './OrdersTab';
import OxygenTab from './OxygenTab';
import LabsTab from './LabsTab';

const TABS = [
  { key: 'io', label: 'I/O' },
  { key: 'meds', label: 'Meds' },
  { key: 'orders', label: 'Orders' },
  { key: 'oxygen', label: 'O₂' },
  { key: 'labs', label: 'Labs' },
];

const FLUID_ORDER_TEXT = 'Fluid restriction. Strict input/output chart.';

/** The active allergies as "Penicillin (Severe) · Latex"; older servers send plain names. */
function allergyLine(patient) {
  if (Array.isArray(patient?.allergy_list)) {
    return patient.allergy_list.filter((a) => !a.resolved).map((a) => a.label).join('  ·  ');
  }
  return (patient?.allergies ?? []).filter((a) => typeof a === 'string').join('  ·  ');
}

function useKeyboardHeight() {
  const [height, setHeight] = useState(0);
  useEffect(() => {
    const showEvt = Platform.OS === 'ios' ? 'keyboardWillShow' : 'keyboardDidShow';
    const hideEvt = Platform.OS === 'ios' ? 'keyboardWillHide' : 'keyboardDidHide';
    const showSub = Keyboard.addListener(showEvt, (e) => setHeight(e?.endCoordinates?.height ?? 0));
    const hideSub = Keyboard.addListener(hideEvt, () => setHeight(0));
    return () => {
      showSub.remove();
      hideSub.remove();
    };
  }, []);
  return height;
}

function TabBadge({ tab, badges }) {
  if (!badges) return null;
  if (tab === 'io' && badges.io_level) {
    return <View style={[styles.dot, { backgroundColor: badges.io_level === 'critical' ? colors.rose500 : colors.amber500 }]} />;
  }
  // SpO2 against the target: red below it, amber above it while on oxygen
  if (tab === 'oxygen' && badges.oxygen_level) {
    return <View style={[styles.dot, { backgroundColor: badges.oxygen_level === 'critical' ? colors.rose500 : colors.amber500 }]} />;
  }
  const count = tab === 'meds' ? badges.meds_overdue
    : tab === 'orders' ? badges.orders_open
    : tab === 'labs' ? badges.labs_review
    : 0;
  if (!count) return null;
  const urgent = tab === 'meds' || (tab === 'labs' && badges.labs_overdue > 0);
  return (
    <View style={[styles.count, urgent && { backgroundColor: colors.rose500 }]}>
      <Text style={styles.countText}>{count}</Text>
    </View>
  );
}

export default function PatientChartModal({ visible, onClose, bed, doctorName, demo, initialTab = 'io', onChanged }) {
  const insets = useSafeAreaInsets();
  const keyboard = useKeyboardHeight();
  const patientId = bed?.patient_id ?? null;
  const client = useMemo(
    () => (bed ? createChartClient({ demo, bed, doctorName }) : null),
    // One client per patient; the bed object is rebuilt on every dashboard refresh
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [demo, patientId, doctorName]
  );

  const [tab, setTab] = useState(initialTab);
  const [chart, setChart] = useState(null);
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);
  const [notice, setNotice] = useState(null);
  const [ioDay, setIoDay] = useState(null);
  // Another tab asking for an order: { id, text, fluid }
  const [composeRequest, setComposeRequest] = useState(null);
  const noticeTimer = useRef(null);

  const load = useCallback(async (day, isRefresh = false) => {
    if (!client) return;
    if (isRefresh) setRefreshing(true);
    else setLoading(true);
    try {
      const data = await client.load({ ioDay: day });
      setChart(data.chart);
      setError(null);
    } catch (e) {
      setError(e?.message ?? 'Could not load the chart.');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [client]);

  // A fresh start each time the chart opens
  useEffect(() => {
    if (!visible) return;
    setTab(initialTab);
    setChart(null);
    setError(null);
    setIoDay(null);
    load(null);
  }, [visible, initialTab, load]);

  useEffect(() => () => clearTimeout(noticeTimer.current), []);

  function flash(message) {
    setNotice(message);
    clearTimeout(noticeTimer.current);
    noticeTimer.current = setTimeout(() => setNotice(null), 4000);
  }

  function changeDay(day) {
    setIoDay(day);
    load(day);
  }

  // Orders and reviews: the answer carries today's chart, so the I/O tab goes back to today
  async function act(action) {
    const data = await action();
    setChart(data.chart);
    setIoDay(null);
    if (data.message) flash(data.message);
    onChanged?.();
    return data;
  }

  // Open the order form on the Orders tab, started with the text for what was asked
  function composeOrder(text, fluid = false) {
    setTab('orders');
    setComposeRequest({ id: Date.now(), text, fluid });
  }

  const patient = chart?.patient;
  const allergies = allergyLine(patient ?? bed);
  const vip = patient?.vip ?? bed?.vip_status ?? null;

  return (
    <Modal visible={visible} animationType="slide" statusBarTranslucent onRequestClose={onClose}>
      <View style={styles.root}>
        <View style={[styles.header, { paddingTop: insets.top + 10 }]}>
          <View style={styles.headerRow}>
            <View style={{ flex: 1, paddingRight: 12 }}>
              <Text style={styles.eyebrow}>{demo ? 'PATIENT CHART · DEMO' : 'PATIENT CHART'}</Text>
              <View style={styles.nameRow}>
                <Text style={styles.name} numberOfLines={1}>{patient?.name ?? bed?.patient_name ?? 'Patient'}</Text>
                {vip ? (
                  <View style={[styles.vip, vip === 'VVIP' && styles.vvip]}>
                    <Text style={styles.vipText}>{vip}</Text>
                  </View>
                ) : null}
              </View>
              <Text style={styles.sub} numberOfLines={1}>
                Bed {patient?.bed ?? bed?.number ?? '-'}  ·  {patient?.ward ?? bed?.ward_name ?? ''}
                {patient?.mrn ? `  ·  MRN ${patient.mrn}` : ''}
              </Text>
            </View>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn} activeOpacity={0.85}>
              <Text style={styles.closeText}>Done</Text>
            </TouchableOpacity>
          </View>
          {allergies ? (
            <Text style={styles.allergies} numberOfLines={2}>⚠ Allergies: {allergies}</Text>
          ) : null}

          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            style={styles.tabsScroll}
            contentContainerStyle={styles.tabs}
          >
            {TABS.map((t) => (
              <TouchableOpacity
                key={t.key}
                onPress={() => setTab(t.key)}
                activeOpacity={0.85}
                style={[styles.tab, tab === t.key && styles.tabActive]}
              >
                <Text style={[styles.tabText, tab === t.key && styles.tabTextActive]}>{t.label}</Text>
                <TabBadge tab={t.key} badges={chart?.badges} />
              </TouchableOpacity>
            ))}
          </ScrollView>
        </View>

        <ScrollView
          style={styles.body}
          contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32 + keyboard }}
          keyboardShouldPersistTaps="handled"
          refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(ioDay, true)} />}
        >
          {error ? (
            <TouchableOpacity style={styles.error} onPress={() => load(ioDay)} activeOpacity={0.85}>
              <Text style={styles.errorTitle}>Could not load the chart</Text>
              <Text style={styles.errorText}>{error} · Tap to retry</Text>
            </TouchableOpacity>
          ) : null}

          {loading && !chart ? (
            <View style={styles.loading}>
              <ActivityIndicator size="large" color={colors.blue700} />
              <Text style={styles.loadingText}>Loading the chart...</Text>
            </View>
          ) : null}

          {chart ? (
            <>
              {tab === 'io' ? (
                <IoTab io={chart.io} onDay={changeDay} onRestrict={() => composeOrder(FLUID_ORDER_TEXT, true)} busy={loading} />
              ) : null}
              {tab === 'meds' ? <MedsTab medications={chart.medications} /> : null}
              {tab === 'orders' ? (
                <OrdersTab
                  orders={chart.orders}
                  composeRequest={composeRequest}
                  onComposeHandled={() => setComposeRequest(null)}
                  onCreate={(body) => act(() => client.createOrder(body))}
                  onCancel={(orderId, reason) => act(() => client.cancelOrder(orderId, reason))}
                />
              ) : null}
              {tab === 'oxygen' ? <OxygenTab oxygen={chart.oxygen} onOrder={(text) => composeOrder(text)} /> : null}
              {tab === 'labs' ? <LabsTab labs={chart.labs} onReview={(labId) => act(() => client.reviewLab(labId))} /> : null}
              <Text style={styles.updated}>Updated {chart.generated_label} · pull down to refresh</Text>
            </>
          ) : null}
        </ScrollView>

        {notice ? (
          <View style={[styles.notice, { bottom: insets.bottom + 18 }]}>
            <Text style={styles.noticeText}>{notice}</Text>
          </View>
        ) : null}
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.bg },
  header: {
    backgroundColor: colors.headerStart,
    paddingHorizontal: 16,
    paddingBottom: 12,
  },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start' },
  eyebrow: { fontSize: 10, fontWeight: '800', letterSpacing: 2, color: '#93c5fd' },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 4 },
  name: { flexShrink: 1, fontSize: 20, fontWeight: '800', color: '#fff' },
  vip: { backgroundColor: colors.amber100, borderRadius: radius.pill, paddingHorizontal: 8, paddingVertical: 2 },
  vvip: { backgroundColor: '#fbbf24' },
  vipText: { fontSize: 11, fontWeight: '900', color: '#451a03', letterSpacing: 0.5 },
  sub: { marginTop: 2, fontSize: 12, color: '#cbd5e1' },
  allergies: {
    marginTop: 8,
    fontSize: 12,
    fontWeight: '700',
    color: '#fecdd3',
  },
  closeBtn: {
    backgroundColor: 'rgba(255,255,255,0.14)',
    borderRadius: radius.pill,
    paddingHorizontal: 16,
    paddingVertical: 8,
  },
  closeText: { color: '#fff', fontWeight: '800', fontSize: 13 },
  // Five tabs fill the bar on most phones, and scroll sideways on the narrowest
  tabsScroll: {
    marginTop: 14,
    flexGrow: 0,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderRadius: radius.md,
  },
  tabs: {
    flexGrow: 1,
    flexDirection: 'row',
    padding: 4,
    gap: 4,
  },
  tab: {
    flexGrow: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 5,
    paddingVertical: 9,
    paddingHorizontal: 8,
    borderRadius: radius.sm,
  },
  tabActive: { backgroundColor: '#fff' },
  tabText: { fontSize: 13, fontWeight: '700', color: '#cbd5e1' },
  tabTextActive: { color: colors.blue900 },
  dot: { width: 8, height: 8, borderRadius: 4 },
  count: {
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    paddingHorizontal: 5,
    backgroundColor: colors.indigo600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countText: { color: '#fff', fontSize: 10, fontWeight: '800' },
  body: { flex: 1 },
  error: {
    backgroundColor: colors.rose50,
    borderWidth: 1,
    borderColor: colors.rose100,
    borderRadius: radius.md,
    padding: 12,
    marginBottom: 12,
  },
  errorTitle: { fontSize: 13, fontWeight: '800', color: colors.rose700 },
  errorText: { marginTop: 2, fontSize: 12, color: colors.rose700 },
  loading: { alignItems: 'center', paddingVertical: 48 },
  loadingText: { marginTop: 10, fontSize: 13, color: colors.muted },
  updated: { marginTop: 4, textAlign: 'center', fontSize: 11, color: colors.mutedSoft },
  notice: {
    position: 'absolute',
    left: 16,
    right: 16,
    backgroundColor: colors.slate900,
    borderRadius: radius.md,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  noticeText: { color: '#fff', fontSize: 13, fontWeight: '600' },
});
