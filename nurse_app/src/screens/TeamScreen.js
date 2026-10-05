// Team on shift: who is on the ward with the nurse now and on the next shift,
// from the AI Nurse Schedule - each nurse's beds and patients, their workload
// against the shift average (as the AI planner scores it), the team leader and
// other special duties.

import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, RefreshControl, ActivityIndicator, BackHandler, StatusBar } from 'react-native';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import { createScheduleClient } from '../api/schedule';
import { Banner, Card, EmptyNote, Progress, SectionTitle, Tag, shared } from '../components/ui';

const LEVEL = {
  heavy: { label: 'Heavy', tone: 'warning' },
  light: { label: 'Light', tone: 'info' },
  even: { label: 'Even', tone: 'good' },
};

export default function TeamScreen({ session, onClose, onSessionExpired }) {
  const insets = useSafeAreaInsets();
  const client = useMemo(() => createScheduleClient(session), [session]);
  const [team, setTeam] = useState(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState(null);
  const mounted = useRef(true);

  const load = useCallback(async (refresh = false) => {
    if (refresh) setRefreshing(true);
    try {
      const res = await client.loadTeam();
      if (!mounted.current) return;
      setTeam(res.team);
      setError(null);
    } catch (e) {
      if (!mounted.current) return;
      if (e?.status === 401) onSessionExpired?.();
      setError(e?.message ?? 'Could not load the team.');
    } finally {
      if (mounted.current) {
        setLoading(false);
        setRefreshing(false);
      }
    }
  }, [client, onSessionExpired]);

  useEffect(() => {
    mounted.current = true;
    load();
    return () => {
      mounted.current = false;
    };
  }, [load]);

  useEffect(() => {
    const sub = BackHandler.addEventListener('hardwareBackPress', () => {
      onClose();
      return true;
    });
    return () => sub.remove();
  }, [onClose]);

  return (
    <View style={styles.root}>
      <StatusBar barStyle="light-content" backgroundColor={colors.headerStart} />
      <SafeAreaView edges={['top']} style={styles.headerArea}>
        <View style={styles.header}>
          <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={onClose}>
            <Text style={styles.iconBtnText}>‹</Text>
          </TouchableOpacity>
          <View style={{ flex: 1, paddingHorizontal: 10 }}>
            <Text style={styles.eyebrow}>TEAM ON SHIFT</Text>
            <Text style={styles.name} numberOfLines={1}>{team?.ward?.name ?? 'Ward team'}</Text>
          </View>
          <TouchableOpacity style={styles.iconBtn} activeOpacity={0.8} onPress={() => load(true)}>
            {refreshing ? <ActivityIndicator size="small" color="#fff" /> : <Text style={[styles.iconBtnText, { fontSize: 18 }]}>⟳</Text>}
          </TouchableOpacity>
        </View>
      </SafeAreaView>

      <ScrollView
        style={{ flex: 1 }}
        contentContainerStyle={{ padding: 14, paddingBottom: insets.bottom + 32 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={() => load(true)} />}
      >
        {error ? <Banner toneName="critical" title="Could not load the team" text={`${error} Tap to retry.`} onPress={() => load(true)} /> : null}
        {loading && !team ? <ActivityIndicator size="large" color={colors.cyan700} style={{ marginTop: 48 }} /> : null}

        {team && !team.ward ? (
          <Card>
            <EmptyNote text="You have no home ward and no shift on now, so there is no team to show." />
          </Card>
        ) : null}

        {team?.current ? <SlotCard eyebrow="ON NOW" slot={team.current} /> : null}
        {team?.ward && !team.current ? (
          <Card>
            <EmptyNote text="No shift is on right now (between shifts)." />
          </Card>
        ) : null}
        {team?.next ? <SlotCard eyebrow="NEXT SHIFT" slot={team.next} /> : null}

        {team?.ward ? (
          <Text style={styles.footnote}>
            Workload is the AI Nurse Schedule's score: each patient plus their level of care, EWS, isolation, fall risk, infusions, STAT orders and new admissions.
          </Text>
        ) : null}
      </ScrollView>
    </View>
  );
}

function SlotCard({ eyebrow, slot }) {
  const max = Math.max(1, ...slot.nurses.map((n) => n.score));

  return (
    <>
      <SectionTitle
        eyebrow={eyebrow}
        title={`${slot.label} · ${slot.name} ${slot.time}`}
        right={slot.nurses.length ? <Tag label={`AVG ${slot.average}`} toneName="muted" /> : null}
      />
      <Card>
        {slot.nurses.length === 0 ? (
          <EmptyNote text="Nobody is rostered or assigned beds on this shift yet." />
        ) : (
          slot.nurses.map((n) => {
            const level = LEVEL[n.level] ?? LEVEL.even;
            return (
              <View key={n.id} style={[styles.nurse, n.is_me && styles.me]}>
                <View style={styles.nurseHead}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.nurseName}>
                      {n.name}
                      {n.is_me ? ' (you)' : ''}
                    </Text>
                    {n.designation ? <Text style={shared.meta}>{n.designation}</Text> : null}
                  </View>
                  <Tag label={level.label} toneName={level.tone} />
                </View>
                {n.duties.length ? (
                  <View style={[shared.chipWrap, { marginTop: 6 }]}>
                    {n.duties.map((d) => (
                      <Tag key={d} label={d} toneName={d === 'Team Leader' ? 'info' : 'muted'} solid={d === 'Team Leader'} />
                    ))}
                  </View>
                ) : null}
                <Text style={styles.beds}>
                  {n.beds.length ? `Beds ${n.beds.join(', ')} · ${n.patients} ${n.patients === 1 ? 'patient' : 'patients'}` : 'No beds assigned'}
                </Text>
                <View style={styles.loadRow}>
                  <View style={{ flex: 1 }}>
                    <Progress percent={(n.score / max) * 100} toneName={level.tone} />
                  </View>
                  <Text style={styles.score}>{n.score}</Text>
                </View>
              </View>
            );
          })
        )}
        {slot.unassigned_beds?.length ? (
          <Text style={[shared.meta, { marginTop: 8, color: colors.amber700, fontWeight: '700' }]}>
            Patients with no nurse this shift: {slot.unassigned_beds.join(', ')}
          </Text>
        ) : null}
      </Card>
    </>
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
  nurse: { paddingVertical: 10, paddingHorizontal: 8, borderTopWidth: 1, borderTopColor: colors.slate100 },
  me: { backgroundColor: colors.cyan50, borderRadius: radius.sm },
  nurseHead: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  nurseName: { color: colors.slate900, fontSize: 14, fontWeight: '800' },
  beds: { marginTop: 6, color: colors.slate700, fontSize: 12, fontWeight: '600' },
  loadRow: { marginTop: 6, flexDirection: 'row', alignItems: 'center', gap: 8 },
  score: { width: 34, textAlign: 'right', color: colors.slate900, fontSize: 13, fontWeight: '800' },
  footnote: { marginTop: 2, textAlign: 'center', color: colors.mutedSoft, fontSize: 11 },
});
