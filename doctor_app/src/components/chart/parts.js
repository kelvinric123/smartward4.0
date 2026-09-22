// Small building blocks shared by the patient chart tabs.

import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator } from 'react-native';
import { colors, radius } from '../../theme';

// Tone -> colours, for banners, badges and chips.
export const TONES = {
  critical: { bg: colors.rose50, border: colors.rose100, text: colors.rose700, solid: colors.rose600 },
  warning: { bg: colors.amber50, border: colors.amber100, text: colors.amber700, solid: colors.amber500 },
  good: { bg: colors.emerald50, border: colors.emerald100, text: colors.emerald700, solid: colors.emerald600 },
  info: { bg: colors.blue50, border: colors.blue100, text: colors.blue700, solid: colors.blue700 },
  indigo: { bg: colors.indigo50, border: colors.indigo100, text: colors.indigo700, solid: colors.indigo600 },
  muted: { bg: colors.slate50, border: colors.slate200, text: colors.slate600, solid: colors.slate500 },
};

export function tone(name) {
  return TONES[name] ?? TONES.muted;
}

/** "1,250 mL" */
export function formatMl(ml) {
  return `${Number(ml ?? 0).toLocaleString('en-US')} mL`;
}

/** "+550 mL", "-120 mL", "0 mL" */
export function signedMl(ml) {
  const n = Number(ml ?? 0);
  return `${n > 0 ? '+' : n < 0 ? '-' : ''}${Math.abs(n).toLocaleString('en-US')} mL`;
}

/** A white card with an eyebrow title and something on the right. */
export function Section({ title, right, children, style }) {
  return (
    <View style={[styles.section, style]}>
      {title || right ? (
        <View style={styles.sectionHead}>
          {title ? <Text style={styles.sectionTitle}>{title}</Text> : <View />}
          {right ?? null}
        </View>
      ) : null}
      {children}
    </View>
  );
}

/** A coloured label: statuses, urgencies, "Auto". */
export function Badge({ label, toneName = 'muted', solid }) {
  const t = tone(toneName);
  return (
    <View
      style={[
        styles.badge,
        solid ? { backgroundColor: t.solid, borderColor: t.solid } : { backgroundColor: t.bg, borderColor: t.border },
      ]}
    >
      <Text style={[styles.badgeText, { color: solid ? '#fff' : t.text }]}>{label}</Text>
    </View>
  );
}

/** A selectable pill: urgencies, presets. */
export function Chip({ label, selected, onPress, toneName = 'info', disabled }) {
  const t = tone(toneName);
  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      disabled={disabled}
      style={[
        styles.chip,
        selected ? { backgroundColor: t.solid, borderColor: t.solid } : { backgroundColor: '#fff', borderColor: colors.slate200 },
        disabled && { opacity: 0.4 },
      ]}
    >
      <Text style={[styles.chipText, { color: selected ? '#fff' : colors.slate700 }]}>{label}</Text>
    </TouchableOpacity>
  );
}

const BUTTON_KINDS = {
  primary: { bg: colors.blue700, border: colors.blue700, text: '#fff' },
  secondary: { bg: '#fff', border: colors.slate200, text: colors.slate700 },
  danger: { bg: colors.rose600, border: colors.rose600, text: '#fff' },
  ghost: { bg: 'transparent', border: 'transparent', text: colors.blue700 },
};

export function Button({ label, onPress, kind = 'primary', busy, disabled, small, style }) {
  const k = BUTTON_KINDS[kind] ?? BUTTON_KINDS.primary;
  return (
    <TouchableOpacity
      activeOpacity={0.85}
      onPress={onPress}
      disabled={disabled || busy}
      style={[
        styles.button,
        small && styles.buttonSmall,
        { backgroundColor: k.bg, borderColor: k.border },
        (disabled || busy) && { opacity: 0.55 },
        style,
      ]}
    >
      {busy ? (
        <ActivityIndicator size="small" color={k.text} />
      ) : (
        <Text style={[styles.buttonText, small && styles.buttonTextSmall, { color: k.text }]}>{label}</Text>
      )}
    </TouchableOpacity>
  );
}

/** An alert from the chart: a coloured box with a title and the detail. */
export function Banner({ level, title, detail }) {
  const t = tone(level === 'critical' ? 'critical' : level === 'warning' ? 'warning' : 'info');
  return (
    <View style={[styles.banner, { backgroundColor: t.bg, borderColor: t.border }]}>
      <Text style={[styles.bannerTitle, { color: t.text }]}>{title}</Text>
      {detail ? <Text style={[styles.bannerDetail, { color: t.text }]}>{detail}</Text> : null}
    </View>
  );
}

export function Empty({ children }) {
  return <Text style={styles.empty}>{children}</Text>;
}

const styles = StyleSheet.create({
  section: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: 14,
    marginBottom: 12,
  },
  sectionHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
    gap: 8,
  },
  sectionTitle: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
    color: colors.muted,
  },
  badge: {
    borderWidth: 1,
    borderRadius: radius.pill,
    paddingHorizontal: 8,
    paddingVertical: 2,
    alignSelf: 'flex-start',
  },
  badgeText: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.3,
  },
  chip: {
    borderWidth: 1,
    borderRadius: radius.pill,
    paddingHorizontal: 14,
    paddingVertical: 8,
  },
  chipText: {
    fontSize: 13,
    fontWeight: '700',
  },
  button: {
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: 16,
    paddingVertical: 12,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonSmall: {
    paddingHorizontal: 12,
    paddingVertical: 7,
    borderRadius: radius.sm,
  },
  buttonText: {
    fontSize: 14,
    fontWeight: '800',
  },
  buttonTextSmall: {
    fontSize: 12,
  },
  banner: {
    borderWidth: 1,
    borderRadius: radius.md,
    padding: 10,
    marginBottom: 8,
  },
  bannerTitle: {
    fontSize: 13,
    fontWeight: '800',
  },
  bannerDetail: {
    marginTop: 2,
    fontSize: 12,
    lineHeight: 17,
  },
  empty: {
    fontSize: 12,
    color: colors.muted,
  },
});
