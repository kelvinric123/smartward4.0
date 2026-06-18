import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius } from '../theme';

const TONES = {
  default: {
    bg: colors.card,
    label: colors.slate500,
    value: colors.slate900,
    sub: colors.slate500,
    border: 'rgba(15,23,42,0.06)',
  },
  rose: {
    bg: colors.rose50,
    label: colors.rose600,
    value: colors.rose700,
    sub: colors.rose600,
    border: colors.rose100,
  },
  amber: {
    bg: colors.amber50,
    label: colors.amber700,
    value: colors.amber700,
    sub: colors.amber700,
    border: colors.amber100,
  },
  emerald: {
    bg: colors.emerald50,
    label: colors.emerald700,
    value: colors.emerald700,
    sub: colors.emerald700,
    border: colors.emerald100,
  },
  blue: {
    bg: colors.blue50,
    label: colors.blue700,
    value: colors.blue700,
    sub: colors.blue700,
    border: colors.blue100,
  },
  indigo: {
    bg: colors.indigo50,
    label: colors.indigo700,
    value: colors.indigo700,
    sub: colors.indigo700,
    border: colors.indigo100,
  },
};

export default function StatCard({ label, value, sub, tone = 'default' }) {
  const t = TONES[tone] ?? TONES.default;
  return (
    <View style={[styles.wrap, { backgroundColor: t.bg, borderColor: t.border }]}>
      <Text style={[styles.label, { color: t.label }]}>{label}</Text>
      <Text style={[styles.value, { color: t.value }]}>{value}</Text>
      {sub ? <Text style={[styles.sub, { color: t.sub }]}>{sub}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    minWidth: 138,
    borderRadius: radius.lg,
    paddingHorizontal: 12,
    paddingVertical: 12,
    borderWidth: 1,
    shadowColor: '#0f172a',
    shadowOffset: { width: 0, height: 12 },
    shadowOpacity: 0.06,
    shadowRadius: 18,
    elevation: 2,
  },
  label: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 1.6,
    textTransform: 'uppercase',
  },
  value: {
    marginTop: 6,
    fontSize: 22,
    fontWeight: '800',
  },
  sub: {
    marginTop: 2,
    fontSize: 11,
  },
});
