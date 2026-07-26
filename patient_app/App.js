import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator } from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import ConnectScreen from './src/screens/ConnectScreen';
import PatientHome from './src/screens/PatientHome';
import { fetchBedSnapshot } from './src/api/endpoints';
import { buildDemoData, buildLiveData } from './src/data/liveData';
import { colors, radius } from './src/theme';

const SNAPSHOT_POLL_MS = 15000;

/**
 * Live mode: follows one bed on the SmartWard server, refreshing the
 * snapshot every SNAPSHOT_POLL_MS. Keeps showing the last good snapshot
 * if a refresh fails (e.g. brief network drop).
 */
function LiveHome({ session, onExit }) {
  const [snapshot, setSnapshot] = useState(session.snapshot ?? null);

  useEffect(() => {
    let alive = true;
    const load = async () => {
      try {
        const snap = await fetchBedSnapshot(session.bed.id);
        if (alive && snap?.success) setSnapshot(snap);
      } catch (e) {
        // Keep last good snapshot; next poll will retry.
      }
    };
    if (!session.snapshot) load();
    const timer = setInterval(load, SNAPSHOT_POLL_MS);
    return () => {
      alive = false;
      clearInterval(timer);
    };
  }, [session.bed.id]);

  if (!snapshot) {
    return (
      <SafeAreaView style={styles.centerScreen}>
        <ActivityIndicator size="large" color="#fff" />
        <Text style={styles.centerText}>Connecting to your bed…</Text>
      </SafeAreaView>
    );
  }

  if (!snapshot.occupied) {
    const discharged = snapshot.vacancy_reason === 'discharged';
    const who = snapshot.discharged_patient;

    return (
      <SafeAreaView style={styles.centerScreen}>
        <Text style={styles.centerEmoji}>{discharged ? '👋' : '🛏️'}</Text>
        <Text style={styles.centerTitle}>
          {discharged
            ? `Take care${who?.first_name ? `, ${who.first_name}` : ''}!`
            : `Bed ${session.bed.display_name ?? session.bed.bed_number}`}
        </Text>
        <Text style={styles.centerText}>
          {discharged ? (
            <>
              You have been discharged from{' '}
              {snapshot.bed?.ward_name ?? 'the ward'}
              {who?.discharged_at_label ? ` on ${who.discharged_at_label}` : ''}.{'\n\n'}
              Thank you for staying with us. Please follow the discharge instructions your nurse
              gave you, and keep any follow-up appointments.
            </>
          ) : (
            <>
              No patient is admitted to this bed right now.{'\n'}
              This screen updates automatically once an admission is registered.
            </>
          )}
        </Text>
        <TouchableOpacity style={styles.centerBtn} onPress={onExit} activeOpacity={0.85}>
          <Text style={styles.centerBtnText}>
            {discharged ? 'Close' : 'Choose a different bed'}
          </Text>
        </TouchableOpacity>
      </SafeAreaView>
    );
  }

  return (
    <PatientHome
      data={buildLiveData(snapshot)}
      demo={false}
      bedId={session.bed.id}
      onExit={onExit}
    />
  );
}

export default function App() {
  const [session, setSession] = useState(null);

  return (
    <SafeAreaProvider>
      {!session ? (
        <ConnectScreen onConnect={setSession} />
      ) : session.demo ? (
        <PatientHome data={buildDemoData()} demo onExit={() => setSession(null)} />
      ) : (
        <LiveHome session={session} onExit={() => setSession(null)} />
      )}
      <StatusBar style="light" />
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  centerScreen: {
    flex: 1,
    backgroundColor: colors.headerStart,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 32,
  },
  centerEmoji: {
    fontSize: 44,
  },
  centerTitle: {
    marginTop: 12,
    color: '#fff',
    fontSize: 22,
    fontWeight: '800',
  },
  centerText: {
    marginTop: 12,
    color: 'rgba(219,234,254,0.85)',
    fontSize: 13,
    lineHeight: 20,
    textAlign: 'center',
  },
  centerBtn: {
    marginTop: 24,
    backgroundColor: 'rgba(255,255,255,0.12)',
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.25)',
    borderRadius: radius.lg,
    paddingVertical: 12,
    paddingHorizontal: 22,
  },
  centerBtnText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },
});
