import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../theme';
import Pill from './Pill';

// Main-page entry point for the shift handover (see HandoverModal).
export default function HandoverCard({
  currentShift,
  nextShift,
  patientCount,
  handedOverCount,
  pendingIncoming,
  onGive,
  onReceive,
}) {
  const shiftLine = currentShift && nextShift
    ? `${currentShift.shift_name ?? currentShift.shift_code} → ${nextShift.shift_name ?? nextShift.shift_code}`
    : nextShift
      ? `To ${nextShift.shift_name ?? nextShift.shift_code} shift`
      : 'Pass over to the next shift';
  const allSent = patientCount > 0 && handedOverCount >= patientCount;

  return (
    <View style={styles.card}>
      <View style={styles.headRow}>
        <View style={{ flex: 1, paddingRight: 8 }}>
          <Text style={styles.eyebrow}>SHIFT HANDOVER</Text>
          <Text style={styles.title} numberOfLines={1}>{shiftLine}</Text>
          <Text style={styles.meta} numberOfLines={1}>
            {nextShift?.starts_at_label
              ? `Next shift starts ${nextShift.starts_at_label}`
              : 'Patient condition & nursing plan'}
          </Text>
        </View>
        {pendingIncoming > 0 ? (
          <Pill label={`${pendingIncoming} TO RECEIVE`} bg={colors.amber100} color={colors.amber700} />
        ) : null}
      </View>

      <View style={styles.btnRow}>
        <TouchableOpacity style={[styles.btn, styles.btnGive]} activeOpacity={0.85} onPress={onGive}>
          <Text style={styles.btnGiveText}>Hand over</Text>
          <Text style={styles.btnGiveSub}>
            {allSent ? '✓ All patients sent' : `${handedOverCount}/${patientCount} patients sent`}
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          style={[styles.btn, styles.btnReceive, pendingIncoming > 0 && styles.btnReceiveAlert]}
          activeOpacity={0.85}
          onPress={onReceive}
        >
          <Text style={[styles.btnReceiveText, pendingIncoming > 0 && { color: colors.amber700 }]}>
            Receive
          </Text>
          <Text style={[styles.btnReceiveSub, pendingIncoming > 0 && { color: colors.amber700 }]}>
            {pendingIncoming > 0 ? `${pendingIncoming} waiting for you` : 'Nothing pending'}
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    marginTop: 14,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    padding: 12,
    shadowColor: '#0f172a',
    shadowOpacity: 0.04,
    shadowOffset: { width: 0, height: 6 },
    shadowRadius: 10,
    elevation: 1,
  },
  headRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  eyebrow: {
    color: colors.cyan700,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  title: {
    marginTop: 2,
    color: colors.slate900,
    fontSize: 16,
    fontWeight: '800',
  },
  meta: {
    marginTop: 2,
    color: colors.muted,
    fontSize: 11,
  },
  btnRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 12,
  },
  btn: {
    flex: 1,
    borderRadius: radius.md,
    paddingVertical: 10,
    paddingHorizontal: 12,
  },
  btnGive: {
    backgroundColor: colors.slate900,
  },
  btnGiveText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },
  btnGiveSub: {
    marginTop: 2,
    color: '#cbd5e1',
    fontSize: 11,
    fontWeight: '600',
  },
  btnReceive: {
    backgroundColor: colors.slate100,
    borderWidth: 1,
    borderColor: colors.slate200,
  },
  btnReceiveAlert: {
    backgroundColor: colors.amber50,
    borderColor: colors.amber500,
  },
  btnReceiveText: {
    color: colors.slate900,
    fontSize: 14,
    fontWeight: '800',
  },
  btnReceiveSub: {
    marginTop: 2,
    color: colors.muted,
    fontSize: 11,
    fontWeight: '600',
  },
});
