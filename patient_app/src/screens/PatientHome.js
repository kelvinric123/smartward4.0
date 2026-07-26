import React, { useMemo, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  StatusBar,
  Alert,
} from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius, shadow } from '../theme';
import Avatar from '../components/Avatar';
import Toast from '../components/Toast';
import HomeTab from './tabs/HomeTab';
import CareTeamTab from './tabs/CareTeamTab';
import HealthTab from './tabs/HealthTab';
import RoomTab from './tabs/RoomTab';
import MealsTab from './tabs/MealsTab';
import RequestsTab from './tabs/RequestsTab';
import ScheduleTab from './tabs/ScheduleTab';

const TABS = [
  { id: 'home', label: 'Home', emoji: '🏠' },
  { id: 'care', label: 'Care', emoji: '👥' },
  { id: 'health', label: 'Health', emoji: '❤️' },
  { id: 'room', label: 'Room', emoji: '💡' },
  { id: 'meals', label: 'Meals', emoji: '🍽️' },
  { id: 'schedule', label: 'Plan', emoji: '📅' },
];

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

export default function PatientHome({ data, demo = true, bedId = null, onExit }) {
  const {
    patient,
    careTeam,
    vitals,
    medications,
    recoveryOverview,
    roomControls,
    dietProfile,
    mealMenu,
    requestCategories,
    todaySchedule,
    dischargeChecklist,
    notifications,
    visitor,
  } = data;

  const insets = useSafeAreaInsets();
  const [tab, setTab] = useState('home');
  const [showRequests, setShowRequests] = useState(false);
  const [toast, setToast] = useState({ visible: false, message: '' });
  const label = useMemo(nowLabel, []);

  const showToast = (message) => setToast({ visible: true, message });

  const confirmExit = () => {
    if (!onExit) return;
    Alert.alert(
      demo ? 'Leave demo mode?' : 'Disconnect from this bed?',
      demo
        ? 'You will return to the setup screen.'
        : 'The app will stop following this bed and return to the setup screen.',
      [
        { text: 'Cancel', style: 'cancel' },
        { text: demo ? 'Leave' : 'Disconnect', style: 'destructive', onPress: onExit },
      ]
    );
  };

  const goRequests = () => {
    setShowRequests(true);
    setTab('requests');
  };

  // 'requests' is reachable from FAB but not in the bottom tab list.
  return (
    <View style={styles.root}>
      <StatusBar barStyle="light-content" backgroundColor={colors.headerStart} />

      <SafeAreaView edges={['top']} style={styles.headerArea}>
        <View style={styles.header}>
          <View style={styles.headerTopRow}>
            <View style={styles.statusPill}>
              <View
                style={[styles.statusDot, demo && { backgroundColor: '#fbbf24' }]}
              />
              <Text style={styles.statusText}>{demo ? 'DEMO' : 'LIVE'}</Text>
            </View>
            <Text style={styles.dateText}>
              {label.date}  ·  {label.time}
            </Text>
          </View>

          <View style={styles.titleRow}>
            <Avatar initials={patient.photo_initials} color="#fff" size={48} />
            <View style={{ flex: 1, marginLeft: 12 }}>
              <Text style={styles.titleEyebrow}>PATIENT</Text>
              <Text style={styles.titleName} numberOfLines={1}>
                {patient.name}
              </Text>
              <Text style={styles.titleMeta}>
                MRN {patient.mrn} · {patient.gender}, {patient.age} yrs
              </Text>
            </View>
            <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={confirmExit}>
              <Text style={styles.iconBtnText}>≡</Text>
            </TouchableOpacity>
          </View>

          <View style={styles.wardRow}>
            <View style={{ flex: 1, paddingRight: 12 }}>
              <Text style={styles.wardEyebrow}>YOUR ROOM</Text>
              <Text style={styles.wardName} numberOfLines={1}>
                Room {patient.room}
              </Text>
              <Text style={styles.wardShift}>{patient.ward}</Text>
            </View>
            <View style={styles.patientChip}>
              <Text style={styles.patientChipLabel}>LOS</Text>
              <Text style={styles.patientChipValue}>
                {patient.los_days}<Text style={{ fontSize: 12, fontWeight: '600' }}> days</Text>
              </Text>
            </View>
          </View>
        </View>
      </SafeAreaView>

      <View style={styles.body}>
        {/* Render only the active tab — keeps things light. */}
        {tab === 'home' ? (
          <HomeTab
            patient={patient}
            recoveryOverview={recoveryOverview}
            todaySchedule={todaySchedule}
            careTeam={careTeam}
            notifications={notifications}
            onOpenRequest={goRequests}
            onOpenCareTeam={() => setTab('care')}
            onOpenSchedule={() => setTab('schedule')}
          />
        ) : null}

        {tab === 'care' ? <CareTeamTab careTeam={careTeam} /> : null}

        {tab === 'health' ? (
          <HealthTab
            patient={patient}
            vitals={vitals}
            medications={medications}
            recoveryOverview={recoveryOverview}
          />
        ) : null}

        {tab === 'room' ? <RoomTab roomControls={roomControls} onToast={showToast} /> : null}

        {tab === 'meals' ? (
          <MealsTab dietProfile={dietProfile} mealMenu={mealMenu} onToast={showToast} />
        ) : null}

        {tab === 'schedule' ? (
          <ScheduleTab
            todaySchedule={todaySchedule}
            dischargeChecklist={dischargeChecklist}
            visitor={visitor}
          />
        ) : null}

        {tab === 'requests' ? (
          <RequestsTab
            requestCategories={requestCategories}
            openInitial={showRequests}
            onConsumeOpen={() => setShowRequests(false)}
            bedId={bedId}
            demo={demo}
          />
        ) : null}
      </View>

      {/* Floating quick-call */}
      {tab !== 'requests' ? (
        <TouchableOpacity
          activeOpacity={0.9}
          onPress={goRequests}
          style={[styles.fab, { bottom: 86 + Math.max(insets.bottom, 0) }]}
        >
          <Text style={styles.fabEmoji}>🛎️</Text>
          <Text style={styles.fabLabel}>NURSE</Text>
        </TouchableOpacity>
      ) : null}

      <View style={[styles.tabbar, { paddingBottom: Math.max(insets.bottom, 8) }]}>
        <View style={styles.tabbarInner}>
          {TABS.map((t) => {
            const active = tab === t.id;
            return (
              <TouchableOpacity
                key={t.id}
                onPress={() => setTab(t.id)}
                activeOpacity={0.85}
                style={styles.navBtn}
              >
                <Text style={[styles.navEmoji, active && styles.navEmojiActive]}>
                  {t.emoji}
                </Text>
                <Text style={[styles.navLabel, active && styles.navLabelActive]}>
                  {t.label}
                </Text>
                {active ? <View style={styles.navUnderline} /> : null}
              </TouchableOpacity>
            );
          })}
        </View>
      </View>

      <Toast
        message={toast.message}
        visible={toast.visible}
        onHide={() => setToast({ visible: false, message: '' })}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.surface },

  headerArea: { backgroundColor: colors.headerStart },
  header: {
    paddingHorizontal: 16,
    paddingTop: 12,
    paddingBottom: 28,
    backgroundColor: colors.headerStart,
    // Faux gradient using overlapping background colour bands; works in core RN.
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
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderColor: 'rgba(255,255,255,0.18)',
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconBtnText: { color: '#fff', fontSize: 22, lineHeight: 22, fontWeight: '700' },
  titleEyebrow: {
    color: '#bfdbfe',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.8,
  },
  titleName: {
    marginTop: 2,
    color: '#fff',
    fontSize: 18,
    fontWeight: '800',
  },
  titleMeta: {
    marginTop: 2,
    color: 'rgba(219,234,254,0.85)',
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
    backgroundColor: 'rgba(255,255,255,0.14)',
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: radius.lg,
    alignItems: 'flex-end',
  },
  patientChipLabel: {
    color: '#bfdbfe',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
  },
  patientChipValue: {
    marginTop: 2,
    color: '#fff',
    fontSize: 22,
    fontWeight: '800',
  },

  body: {
    flex: 1,
    backgroundColor: colors.surface,
    marginTop: -20,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    overflow: 'hidden',
  },

  fab: {
    position: 'absolute',
    right: 18,
    backgroundColor: colors.rose600,
    width: 62,
    height: 62,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    ...shadow.lg,
    shadowColor: colors.rose600,
    zIndex: 20,
  },
  fabEmoji: { fontSize: 22, lineHeight: 24 },
  fabLabel: {
    color: '#fff',
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.4,
    marginTop: 1,
  },

  tabbar: {
    backgroundColor: 'rgba(255,255,255,0.98)',
    borderTopWidth: 1,
    borderTopColor: colors.line,
  },
  tabbarInner: {
    flexDirection: 'row',
    paddingHorizontal: 6,
    paddingTop: 6,
  },
  navBtn: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 6,
  },
  navEmoji: {
    fontSize: 20,
    opacity: 0.55,
  },
  navEmojiActive: {
    opacity: 1,
  },
  navLabel: {
    marginTop: 2,
    fontSize: 10,
    fontWeight: '700',
    color: colors.muted,
  },
  navLabelActive: {
    color: colors.slate900,
    fontWeight: '800',
  },
  navUnderline: {
    marginTop: 3,
    width: 16,
    height: 3,
    borderRadius: 3,
    backgroundColor: colors.brand600,
  },
});
