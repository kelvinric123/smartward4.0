import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  StatusBar,
  Platform,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import StatCard from '../components/StatCard';
import BedCard from '../components/BedCard';
import { fetchNurseDashboard } from '../api/endpoints';
import * as mock from '../data/mockData';
import { demoBadgesFor } from '../data/mockPatient';
import PatientScreen from './PatientScreen';
import RosterScreen from './RosterScreen';
import TeamScreen from './TeamScreen';
import { CountBadge, Tag } from '../components/ui';
import { DEMO_WORKLOAD, demoDashboardSchedule } from '../data/mockSchedule';

const REFRESH_INTERVAL_MS = 60000;

const EMPTY_SUMMARY = {
  assigned_beds: 0,
  occupied_beds: 0,
  critical_patients: 0,
  active_infusions: 0,
  infusion_alerts: 0,
  ward_occupancy: 0,
};

function nowLabel() {
  const d = new Date();
  const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const pad = (n) => String(n).padStart(2, '0');
  return {
    date: `${days[d.getDay()]}, ${pad(d.getDate())} ${months[d.getMonth()]}`,
    time: `${pad(d.getHours())}:${pad(d.getMinutes())}`,
  };
}

export default function NurseDashboard({ session, onLogout }) {
  const insets = useSafeAreaInsets();
  const isDemo = !!session?.demo;

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const mountedRef = useRef(true);

  const nurse = data?.nurse ?? session?.nurse ?? { name: 'Nurse' };
  const currentShift = data?.current_shift ?? null;
  const selectedWard = data?.ward ?? null;
  const summary = data?.summary ?? EMPTY_SUMMARY;
  const assignedBeds = data?.beds ?? [];
  // The bed whose chart is open (orders, I/O, meds, infusion, alerts), if any
  const [openBed, setOpenBed] = useState(null);
  // My roster or the team on shift, when open
  const [view, setView] = useState(null);
  const schedule = data?.schedule ?? null;
  const myLoad = summary.my_load ?? null;

  const [currentIndex, setCurrentIndex] = useState(0);
  const total = assignedBeds.length;
  const label = useMemo(nowLabel, []);
  const safeIndex = Math.min(currentIndex, Math.max(0, total - 1));
  const bed = assignedBeds[safeIndex];

  const canPrev = safeIndex > 0;
  const canNext = safeIndex < total - 1;

  const load = useCallback(async (isRefresh = false) => {
    if (isDemo) {
      // Demo beds carry the same "what needs doing" badges and workload the server sends
      const beds = mock.assignedBeds.map((b) => ({ ...b, badges: demoBadgesFor(b), workload: DEMO_WORKLOAD[b.id] ?? null }));
      const total = (key) => beds.reduce((n, b) => n + (b.badges?.[key] ?? 0), 0);
      const demoSchedule = demoDashboardSchedule();
      setData({
        nurse: mock.nurse,
        current_shift: mock.currentShift,
        ward: mock.selectedWard,
        summary: {
          ...mock.summary,
          open_orders: total('orders_open'),
          doses_overdue: total('meds_overdue'),
          pending_alerts: total('alerts_pending'),
          labs_to_review: total('labs_review'),
          my_load: demoSchedule.my_load,
        },
        schedule: demoSchedule.schedule,
        beds,
      });
      setLoadError(null);
      setLoading(false);
      setRefreshing(false);
      return;
    }
    if (isRefresh) setRefreshing(true);
    try {
      const payload = await fetchNurseDashboard(session?.token);
      if (!mountedRef.current) return;
      setData(payload);
      setLoadError(null);
    } catch (e) {
      if (!mountedRef.current) return;
      setLoadError(e?.message ?? 'Could not load your assigned beds.');
      if (e?.status === 401) {
        onLogout?.();
      }
    } finally {
      if (mountedRef.current) {
        setLoading(false);
        setRefreshing(false);
      }
    }
  }, [session, onLogout, isDemo]);

  useEffect(() => {
    mountedRef.current = true;
    load();
    const timer = isDemo ? null : setInterval(() => load(), REFRESH_INTERVAL_MS);
    return () => {
      mountedRef.current = false;
      if (timer) clearInterval(timer);
    };
  }, [load, isDemo]);

  if (view === 'roster' || view === 'team') {
    const Screen = view === 'roster' ? RosterScreen : TeamScreen;
    return (
      <Screen
        session={session}
        onClose={() => {
          setView(null);
          load(); // requests and acknowledgements may have changed
        }}
        onSessionExpired={onLogout}
      />
    );
  }

  if (openBed) {
    return (
      <PatientScreen
        session={session}
        bed={openBed}
        onClose={() => {
          setOpenBed(null);
          load(); // badges may have changed
        }}
        onSessionExpired={onLogout}
      />
    );
  }

  const bedTodo = (b) =>
    b.badges
      ? b.badges.orders_open + b.badges.meds_overdue + b.badges.alerts_pending
        + (b.badges.transfusions_running ?? 0) + (b.badges.transfusions_pending ?? 0)
        + (b.badges.labs_review ?? 0) + (b.badges.assess_overdue ?? 0)
      : 0;

  return (
    <View style={styles.root}>
      <StatusBar barStyle="light-content" backgroundColor={colors.headerStart} />
      <SafeAreaView edges={['top']} style={styles.headerArea}>
        <View style={styles.header}>
          <View style={styles.headerTopRow}>
            <View style={styles.statusPill}>
              <View style={[styles.statusDot, isDemo && { backgroundColor: '#f59e0b' }]} />
              <Text style={styles.statusText}>{isDemo ? 'DEMO' : 'LIVE'}</Text>
            </View>
            <Text style={styles.dateText}>
              {label.date}  ·  {label.time}
            </Text>
          </View>

          <View style={styles.titleRow}>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={onLogout}>
              <Text style={styles.iconBtnText}>‹</Text>
            </TouchableOpacity>
            <View style={{ flex: 1, alignItems: 'center' }}>
              <Text style={styles.titleEyebrow}>NURSE</Text>
              <Text style={styles.titleName} numberOfLines={1}>
                {nurse.name}
              </Text>
            </View>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={onLogout}>
              <Text style={[styles.iconBtnText, { fontSize: 14 }]}>⎋</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.wardRow}>
            <View style={{ flex: 1, paddingRight: 12 }}>
              <Text style={styles.wardEyebrow}>CURRENT WARD</Text>
              <Text style={styles.wardName} numberOfLines={1}>
                {selectedWard?.ward_name ?? 'No ward selected'}
              </Text>
              <Text style={styles.wardShift}>
                {currentShift
                  ? `${currentShift.shift_name ?? currentShift.shift_code} shift`
                  : 'Shift assignment unavailable'}
              </Text>
            </View>
            <View style={styles.patientChip}>
              <Text style={styles.patientChipLabel}>PATIENTS</Text>
              <Text style={styles.patientChipValue}>{summary.occupied_beds}</Text>
            </View>
          </View>
        </View>
      </SafeAreaView>

      <ScrollView
        style={styles.body}
        contentContainerStyle={[
          styles.bodyContent,
          { paddingBottom: 80 + insets.bottom + 16 },
        ]}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={() => load(true)} />
        }
      >
        {loadError ? (
          <TouchableOpacity style={styles.errorBanner} onPress={() => load(true)} activeOpacity={0.85}>
            <Text style={styles.errorBannerTitle}>Could not refresh data</Text>
            <Text style={styles.errorBannerText}>{loadError} · Tap to retry</Text>
          </TouchableOpacity>
        ) : null}

        {loading && !data ? (
          <View style={styles.loadingBox}>
            <ActivityIndicator size="large" color={colors.cyan700} />
            <Text style={styles.loadingText}>Loading your assigned beds...</Text>
          </View>
        ) : null}

        <ScheduleCard schedule={schedule} myLoad={myLoad} onRoster={() => setView('roster')} onTeam={() => setView('team')} />

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.statsRow}
        >
          <StatCard label="ASSIGNED" value={summary.assigned_beds} sub="For this nurse" />
          <StatCard
            label="CRITICAL"
            value={summary.critical_patients}
            sub="Needs attention"
            tone="rose"
          />
          <StatCard
            label="INFUSIONS"
            value={summary.active_infusions}
            sub={`${summary.infusion_alerts} alerts`}
            tone="amber"
          />
          <StatCard
            label="ORDERS"
            value={summary.open_orders ?? 0}
            sub="Open consultant orders"
            tone={summary.open_orders ? 'amber' : 'default'}
          />
          <StatCard
            label="DOSES"
            value={summary.doses_overdue ?? 0}
            sub="Overdue now"
            tone={summary.doses_overdue ? 'rose' : 'default'}
          />
          <StatCard
            label="CALLS"
            value={summary.pending_alerts ?? 0}
            sub="Unanswered alerts"
            tone={summary.pending_alerts ? 'amber' : 'default'}
          />
          <StatCard
            label="LABS"
            value={summary.labs_to_review ?? 0}
            sub="Results to review"
            tone={summary.labs_to_review ? 'amber' : 'default'}
          />
          <StatCard
            label="OCCUPANCY"
            value={`${summary.ward_occupancy}%`}
            sub="Whole ward"
            tone="emerald"
          />
        </ScrollView>

        <View style={styles.sectionHead}>
          <View>
            <Text style={styles.sectionEyebrow}>BEDSIDE QUEUE</Text>
            <Text style={styles.sectionTitle}>Patients</Text>
          </View>
          <View style={styles.totalChip}>
            <Text style={styles.totalChipLabel}>TOTAL</Text>
            <Text style={styles.totalChipValue}>{total}</Text>
          </View>
        </View>

        <View style={styles.tabsCard}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tabsRow}>
            {assignedBeds.map((b, i) => {
              const active = i === safeIndex;
              return (
                <TouchableOpacity
                  key={b.id}
                  activeOpacity={0.85}
                  onPress={() => setCurrentIndex(i)}
                  style={[styles.tab, active && styles.tabActive]}
                >
                  <Text style={[styles.tabText, active && styles.tabTextActive]}>
                    Bed {b.number}
                  </Text>
                  <CountBadge
                    count={bedTodo(b)}
                    toneName={b.badges?.orders_stat || b.badges?.meds_overdue || b.badges?.transfusions_running || b.badges?.labs_critical || b.badges?.assess_overdue ? 'critical' : 'warning'}
                  />
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>

        {bed ? <BedCard bed={bed} fallbackNurse={nurse.name} onOpen={() => setOpenBed(bed)} /> : (
          <View style={styles.emptyState}>
            <Text style={styles.emptyTitle}>No beds assigned right now</Text>
            <Text style={styles.emptyMeta}>
              This view will fill automatically when this nurse has current shift bed assignments.
            </Text>
          </View>
        )}
      </ScrollView>

      <View style={[styles.tabbar, { paddingBottom: Math.max(insets.bottom, 8) }]}>
        <View style={styles.tabbarInner}>
          <TouchableOpacity
            disabled={!canPrev}
            onPress={() => setCurrentIndex((i) => Math.max(0, i - 1))}
            activeOpacity={0.85}
            style={[styles.navBtn, styles.navBtnPrev, !canPrev && styles.navBtnDisabled]}
          >
            <Text style={styles.navBtnPrevText}>‹  Prev</Text>
          </TouchableOpacity>
          <View style={styles.navCenter}>
            <Text style={styles.navCenterLabel}>BED</Text>
            <Text style={styles.navCenterValue}>
              {total === 0 ? '0 / 0' : `${safeIndex + 1} / ${total}`}
            </Text>
          </View>
          <TouchableOpacity
            disabled={!canNext}
            onPress={() => setCurrentIndex((i) => Math.min(total - 1, i + 1))}
            activeOpacity={0.85}
            style={[styles.navBtn, styles.navBtnNext, !canNext && styles.navBtnDisabled]}
          >
            <Text style={styles.navBtnNextText}>Next  ›</Text>
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
}

/** Today and tomorrow from my roster, my workload, and anything waiting on me; opens My roster and Team. */
function ScheduleCard({ schedule, myLoad, onRoster, onTeam }) {
  if (!schedule && !myLoad) return null;
  const waiting = [];
  if (schedule?.swaps_to_answer) waiting.push(`${schedule.swaps_to_answer} swap ${schedule.swaps_to_answer === 1 ? 'request' : 'requests'} to answer`);
  (schedule?.to_acknowledge ?? []).forEach((w) => waiting.push(`${w.state === 'changed' ? 'Changes to' : 'Roster'} ${w.label} to acknowledge`));
  if (schedule?.requests_decided) waiting.push(`${schedule.requests_decided} of your requests decided`);
  const dayText = (d) => {
    if (!d) return '—';
    if (d.leave) return d.leave.label;
    if (!d.shift) return 'Not rostered';
    if (d.shift === 'OFF') return 'Day off';
    return `${d.shift} ${d.time ?? ''}${d.duties?.length ? ` · ${d.duties.join(', ')}` : ''}`;
  };
  const loadTone = myLoad?.level === 'heavy' ? 'warning' : myLoad?.level === 'light' ? 'info' : 'good';

  return (
    <View style={styles.scheduleCard}>
      <TouchableOpacity activeOpacity={0.85} onPress={onRoster} style={styles.scheduleMain}>
        <View style={styles.scheduleHead}>
          <Text style={styles.scheduleEyebrow}>MY ROSTER</Text>
          {waiting.length ? <CountBadge count={waiting.length} toneName="warning" /> : null}
        </View>
        <Text style={styles.scheduleLine} numberOfLines={1}>
          <Text style={styles.scheduleDay}>Today </Text>
          {dayText(schedule?.today)}
        </Text>
        <Text style={styles.scheduleLine} numberOfLines={1}>
          <Text style={styles.scheduleDay}>Tomorrow </Text>
          {dayText(schedule?.tomorrow)}
        </Text>
        {waiting.map((w) => (
          <Text key={w} style={styles.scheduleWaiting} numberOfLines={1}>• {w}</Text>
        ))}
        <Text style={styles.scheduleLink}>Roster, leave and swaps ›</Text>
      </TouchableOpacity>
      <TouchableOpacity activeOpacity={0.85} onPress={onTeam} style={styles.scheduleSide}>
        <Text style={styles.scheduleEyebrow}>MY LOAD</Text>
        {myLoad ? (
          <>
            <Text style={styles.loadValue}>{myLoad.score}</Text>
            <Tag label={myLoad.level.toUpperCase()} toneName={loadTone} />
            <Text style={styles.loadMeta}>Team avg {myLoad.team_average}</Text>
          </>
        ) : (
          <Text style={styles.loadMeta}>No beds this shift</Text>
        )}
        <Text style={styles.scheduleLink}>Team ›</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  scheduleCard: {
    flexDirection: 'row',
    gap: 10,
    marginBottom: 12,
  },
  scheduleMain: {
    flex: 1,
    backgroundColor: '#fff',
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.07)',
    padding: 12,
  },
  scheduleSide: {
    width: 104,
    backgroundColor: '#fff',
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.07)',
    padding: 12,
    alignItems: 'flex-start',
    gap: 4,
  },
  scheduleHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 6 },
  scheduleEyebrow: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 2 },
  scheduleLine: { color: colors.slate900, fontSize: 13, fontWeight: '700', marginTop: 2 },
  scheduleDay: { color: colors.slate500, fontWeight: '800' },
  scheduleWaiting: { marginTop: 4, color: colors.amber700, fontSize: 12, fontWeight: '700' },
  scheduleLink: { marginTop: 8, color: colors.cyan700, fontSize: 12, fontWeight: '800' },
  loadValue: { color: colors.slate900, fontSize: 24, fontWeight: '800' },
  loadMeta: { color: colors.muted, fontSize: 11 },
  root: {
    flex: 1,
    backgroundColor: colors.surface,
  },
  headerArea: {
    backgroundColor: colors.headerStart,
  },
  header: {
    backgroundColor: colors.headerEnd,
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 28,
    backgroundColor: '#0b2f44',
  },
  headerTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  statusPill: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  statusDot: {
    width: 8,
    height: 8,
    borderRadius: 999,
    backgroundColor: '#34d399',
    marginRight: 8,
  },
  statusText: {
    color: 'rgba(207,250,254,0.9)',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
  },
  dateText: {
    color: 'rgba(207,250,254,0.85)',
    fontSize: 12,
    fontWeight: '600',
  },
  titleRow: {
    marginTop: 14,
    flexDirection: 'row',
    alignItems: 'center',
  },
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
  iconBtnText: {
    color: '#fff',
    fontSize: 22,
    lineHeight: 22,
    fontWeight: '700',
  },
  titleEyebrow: {
    color: '#cffafe',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.8,
  },
  titleName: {
    marginTop: 2,
    color: '#fff',
    fontSize: 16,
    fontWeight: '800',
  },
  wardRow: {
    marginTop: 18,
    flexDirection: 'row',
    alignItems: 'flex-end',
  },
  wardEyebrow: {
    color: '#cffafe',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.8,
  },
  wardName: {
    marginTop: 4,
    color: '#fff',
    fontSize: 22,
    fontWeight: '800',
  },
  wardShift: {
    marginTop: 2,
    color: 'rgba(207,250,254,0.85)',
    fontSize: 12,
  },
  patientChip: {
    backgroundColor: 'rgba(255,255,255,0.12)',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: radius.lg,
    alignItems: 'flex-end',
  },
  patientChipLabel: {
    color: '#cffafe',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
  },
  patientChipValue: {
    marginTop: 2,
    color: '#fff',
    fontSize: 20,
    fontWeight: '800',
  },

  body: {
    flex: 1,
    backgroundColor: colors.surface,
    marginTop: -20,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
  },
  bodyContent: {
    paddingTop: 20,
    paddingHorizontal: 14,
  },

  statsRow: {
    paddingVertical: 4,
    gap: 10,
  },

  errorBanner: {
    backgroundColor: colors.rose50,
    borderColor: colors.rose100,
    borderWidth: 1,
    borderRadius: radius.lg,
    padding: 12,
    marginBottom: 12,
  },
  errorBannerTitle: {
    color: colors.rose700,
    fontSize: 12,
    fontWeight: '800',
  },
  errorBannerText: {
    marginTop: 2,
    color: colors.rose600,
    fontSize: 11,
  },
  loadingBox: {
    paddingVertical: 40,
    alignItems: 'center',
  },
  loadingText: {
    marginTop: 12,
    color: colors.muted,
    fontSize: 13,
    fontWeight: '600',
  },

  sectionHead: {
    marginTop: 18,
    marginBottom: 10,
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
  },
  sectionEyebrow: {
    color: colors.muted,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.4,
  },
  sectionTitle: {
    marginTop: 2,
    color: colors.slate900,
    fontSize: 18,
    fontWeight: '800',
  },
  totalChip: {
    backgroundColor: colors.slate900,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: radius.md,
    alignItems: 'flex-end',
  },
  totalChipLabel: {
    color: '#cbd5e1',
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.8,
  },
  totalChipValue: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },

  tabsCard: {
    backgroundColor: '#fff',
    borderRadius: radius.lg,
    padding: 8,
    marginBottom: 12,
    shadowColor: '#0f172a',
    shadowOpacity: 0.04,
    shadowOffset: { width: 0, height: 6 },
    shadowRadius: 10,
    elevation: 1,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.05)',
  },
  tabsRow: {
    gap: 6,
    paddingHorizontal: 2,
  },
  tab: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    backgroundColor: colors.slate100,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  tabActive: {
    backgroundColor: colors.slate900,
  },
  tabText: {
    color: colors.slate600,
    fontSize: 12,
    fontWeight: '700',
  },
  tabTextActive: {
    color: '#fff',
  },

  emptyState: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: colors.slate300,
    backgroundColor: '#fff',
    padding: 28,
    alignItems: 'center',
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.slate900,
  },
  emptyMeta: {
    marginTop: 6,
    fontSize: 13,
    color: colors.muted,
    textAlign: 'center',
  },

  tabbar: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(255,255,255,0.96)',
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  tabbarInner: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingTop: 8,
  },
  navBtn: {
    flex: 1,
    paddingHorizontal: 14,
    paddingVertical: 12,
    borderRadius: radius.lg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  navBtnPrev: {
    backgroundColor: colors.slate100,
  },
  navBtnNext: {
    backgroundColor: colors.slate900,
  },
  navBtnDisabled: {
    opacity: 0.4,
  },
  navBtnPrevText: {
    color: colors.slate700,
    fontSize: 14,
    fontWeight: '800',
  },
  navBtnNextText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },
  navCenter: {
    paddingHorizontal: 14,
    alignItems: 'center',
  },
  navCenterLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.6,
    color: colors.muted,
  },
  navCenterValue: {
    marginTop: 2,
    fontSize: 14,
    fontWeight: '800',
    color: colors.slate900,
  },
});
