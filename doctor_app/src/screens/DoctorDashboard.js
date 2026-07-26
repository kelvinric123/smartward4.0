import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  StatusBar,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import StatCard from '../components/StatCard';
import BedCard from '../components/BedCard';
import CriticalListModal from '../components/CriticalListModal';
import { fetchDoctorDashboard } from '../api/endpoints';
import * as mock from '../data/mockData';

const REFRESH_INTERVAL_MS = 60000;

const EMPTY_SUMMARY = {
  total_beds_under_care: 0,
  wards_covered: 0,
  critical_patients: 0,
  pending_discharge: 0,
  pending_reviews: 0,
  pending_orders: 0,
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

export default function DoctorDashboard({ session, onLogout }) {
  const insets = useSafeAreaInsets();
  const isDemo = !!session?.demo;

  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [loadError, setLoadError] = useState(null);
  const mountedRef = useRef(true);

  const doctor = data?.doctor ?? session?.doctor ?? { name: 'Consultant', title: 'Consultant' };
  const wards = data?.wards ?? session?.wards ?? [];
  const summary = data?.summary ?? EMPTY_SUMMARY;
  const allBeds = data?.beds ?? [];

  const [wardFilter, setWardFilter] = useState('all');
  const [currentIndex, setCurrentIndex] = useState(0);
  const [criticalOpen, setCriticalOpen] = useState(false);

  const load = useCallback(async (isRefresh = false) => {
    if (isDemo) {
      setData({
        doctor: mock.doctor,
        summary: mock.summary,
        wards: mock.wards,
        beds: mock.consultantBeds,
      });
      setLoadError(null);
      setLoading(false);
      setRefreshing(false);
      return;
    }
    if (isRefresh) setRefreshing(true);
    try {
      const payload = await fetchDoctorDashboard(session?.doctor?.id, session?.token);
      if (!mountedRef.current) return;
      setData(payload);
      setLoadError(null);
    } catch (e) {
      if (!mountedRef.current) return;
      setLoadError(e?.message ?? 'Could not load your patients.');
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

  const beds = useMemo(() => {
    if (wardFilter === 'all') return allBeds;
    return allBeds.filter((b) => b.ward_id === wardFilter);
  }, [wardFilter, allBeds]);

  const total = beds.length;
  const safeIndex = Math.min(currentIndex, Math.max(0, total - 1));
  const bed = beds[safeIndex];
  const label = useMemo(nowLabel, []);

  const canPrev = safeIndex > 0;
  const canNext = safeIndex < total - 1;

  function selectWard(id) {
    setWardFilter(id);
    setCurrentIndex(0);
  }

  function jumpToBed(targetBed) {
    setCriticalOpen(false);
    setWardFilter('all');
    const idx = allBeds.findIndex((b) => b.id === targetBed.id);
    if (idx >= 0) setCurrentIndex(idx);
  }

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
              <Text style={styles.titleEyebrow}>CONSULTANT</Text>
              <Text style={styles.titleName} numberOfLines={1}>
                {doctor.name}
              </Text>
              <Text style={styles.titleSub} numberOfLines={1}>
                {doctor.title}{doctor.specialty ? `  ·  ${doctor.specialty}` : ''}
              </Text>
            </View>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={onLogout}>
              <Text style={[styles.iconBtnText, { fontSize: 14 }]}>⎋</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.wardRow}>
            <View style={{ flex: 1, paddingRight: 12 }}>
              <Text style={styles.wardEyebrow}>UNDER YOUR CARE</Text>
              <Text style={styles.wardName} numberOfLines={1}>
                {summary.total_beds_under_care} beds · {summary.wards_covered} wards
              </Text>
              <Text style={styles.wardShift}>
                QMed Smart Ward · Consultant View
              </Text>
            </View>
            <TouchableOpacity
              style={styles.patientChip}
              activeOpacity={0.85}
              onPress={() => setCriticalOpen(true)}
            >
              <Text style={styles.patientChipLabel}>CRITICAL</Text>
              <Text style={styles.patientChipValue}>{summary.critical_patients}</Text>
            </TouchableOpacity>
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
            <ActivityIndicator size="large" color={colors.blue700} />
            <Text style={styles.loadingText}>Loading your patients...</Text>
          </View>
        ) : null}

        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerStyle={styles.statsRow}
        >
          <StatCard label="BEDS" value={summary.total_beds_under_care} sub="Under your care" tone="blue" />
          <TouchableOpacity activeOpacity={0.85} onPress={() => setCriticalOpen(true)}>
            <StatCard
              label="CRITICAL"
              value={summary.critical_patients}
              sub="Tap to view ›"
              tone="rose"
            />
          </TouchableOpacity>
          <StatCard
            label="REVIEWS"
            value={summary.pending_reviews}
            sub="Pending consultant review"
            tone="amber"
          />
          <StatCard
            label="ORDERS"
            value={summary.pending_orders}
            sub="Awaiting your action"
            tone="indigo"
          />
          <StatCard
            label="DISCHARGE"
            value={summary.pending_discharge}
            sub="Pending discharge"
            tone="emerald"
          />
        </ScrollView>

        <View style={styles.sectionHead}>
          <View>
            <Text style={styles.sectionEyebrow}>WARDS</Text>
            <Text style={styles.sectionTitle}>Filter by Ward</Text>
          </View>
          <View style={styles.totalChip}>
            <Text style={styles.totalChipLabel}>SHOWING</Text>
            <Text style={styles.totalChipValue}>{total}</Text>
          </View>
        </View>

        <View style={styles.tabsCard}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tabsRow}>
            <TouchableOpacity
              activeOpacity={0.85}
              onPress={() => selectWard('all')}
              style={[styles.tab, wardFilter === 'all' && styles.tabActive]}
            >
              <Text style={[styles.tabText, wardFilter === 'all' && styles.tabTextActive]}>
                All wards ({allBeds.length})
              </Text>
            </TouchableOpacity>
            {wards.map((w) => {
              const count = allBeds.filter((b) => b.ward_id === w.id).length;
              const active = wardFilter === w.id;
              return (
                <TouchableOpacity
                  key={w.id}
                  activeOpacity={0.85}
                  onPress={() => selectWard(w.id)}
                  style={[styles.tab, active && styles.tabActive]}
                >
                  <Text style={[styles.tabText, active && styles.tabTextActive]}>
                    {w.ward_name} ({count})
                  </Text>
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>

        <View style={styles.sectionHead}>
          <View>
            <Text style={styles.sectionEyebrow}>BEDSIDE QUEUE</Text>
            <Text style={styles.sectionTitle}>Patients under your care</Text>
          </View>
        </View>

        <View style={styles.tabsCard}>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.tabsRow}>
            {beds.map((b, i) => {
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
                </TouchableOpacity>
              );
            })}
          </ScrollView>
        </View>

        {bed ? <BedCard bed={bed} doctorName={doctor.name} /> : (
          <View style={styles.emptyState}>
            <Text style={styles.emptyTitle}>
              {loading ? 'Loading...' : wardFilter === 'all' ? 'No patients under your care' : 'No beds in this ward'}
            </Text>
            <Text style={styles.emptyMeta}>
              {wardFilter === 'all'
                ? 'No admitted patients are currently assigned to you in the QMed Smart Ward system.'
                : 'You have no patients in the selected ward. Switch to another ward or "All wards".'}
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

      <CriticalListModal
        visible={criticalOpen}
        onClose={() => setCriticalOpen(false)}
        beds={allBeds}
        onSelect={jumpToBed}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.surface,
  },
  headerArea: {
    backgroundColor: colors.headerStart,
  },
  header: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 28,
    backgroundColor: '#0b1e36',
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
    color: 'rgba(219,234,254,0.9)',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
  },
  dateText: {
    color: 'rgba(219,234,254,0.85)',
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
    color: '#bfdbfe',
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
  titleSub: {
    marginTop: 2,
    color: 'rgba(219,234,254,0.8)',
    fontSize: 11,
  },
  wardRow: {
    marginTop: 18,
    flexDirection: 'row',
    alignItems: 'flex-end',
  },
  wardEyebrow: {
    color: '#bfdbfe',
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
    color: 'rgba(219,234,254,0.85)',
    fontSize: 12,
  },
  patientChip: {
    backgroundColor: 'rgba(244,63,94,0.18)',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: radius.lg,
    alignItems: 'flex-end',
    borderWidth: 1,
    borderColor: 'rgba(244,63,94,0.35)',
  },
  patientChipLabel: {
    color: '#fecdd3',
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
    backgroundColor: colors.slate100,
    borderRadius: 999,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  tabActive: {
    backgroundColor: colors.blue700,
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
    backgroundColor: colors.blue700,
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
