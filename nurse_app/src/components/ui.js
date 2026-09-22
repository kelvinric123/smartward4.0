// Small building blocks shared by the patient chart screens.

import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, ActivityIndicator } from 'react-native';
import { colors, radius } from '../theme';

// Tone -> colours, for banners, badges, pills and status text.
export const TONES = {
  critical: { bg: colors.rose50, border: colors.rose100, text: colors.rose700, solid: colors.rose600 },
  warning: { bg: colors.amber50, border: colors.amber100, text: colors.amber700, solid: colors.amber500 },
  good: { bg: colors.emerald50, border: colors.emerald100, text: colors.emerald700, solid: colors.emerald600 },
  info: { bg: colors.cyan50, border: colors.cyan100, text: colors.cyan700, solid: colors.cyan700 },
  muted: { bg: colors.slate50, border: colors.slate200, text: colors.slate600, solid: colors.slate500 },
  default: { bg: colors.slate100, border: colors.slate200, text: colors.slate700, solid: colors.slate900 },
};

export function tone(name) {
  return TONES[name] ?? TONES.default;
}

/** A card with an optional eyebrow title and something on the right. */
export function Card({ title, right, children, style, padded = true }) {
  return (
    <View style={[styles.card, padded && styles.cardPadded, style]}>
      {title || right ? (
        <View style={styles.cardHead}>
          {title ? <Text style={styles.cardTitle}>{title}</Text> : <View />}
          {right ?? null}
        </View>
      ) : null}
      {children}
    </View>
  );
}

/** A selectable pill: presets, types, statuses. */
export function Chip({ label, selected, onPress, toneName = 'info', small, disabled, count }) {
  const t = tone(toneName);
  return (
    <TouchableOpacity
      activeOpacity={0.8}
      onPress={onPress}
      disabled={disabled}
      style={[
        styles.chip,
        small && styles.chipSmall,
        selected ? { backgroundColor: t.solid, borderColor: t.solid } : { backgroundColor: '#fff', borderColor: colors.slate200 },
        disabled && { opacity: 0.4 },
      ]}
    >
      <Text style={[styles.chipText, small && styles.chipTextSmall, { color: selected ? '#fff' : colors.slate700 }]}>
        {label}
        {count != null ? `  ${count}` : ''}
      </Text>
    </TouchableOpacity>
  );
}

/** Full-width or inline button. kind: primary | secondary | danger | ghost */
export function Button({ label, onPress, kind = 'primary', busy, disabled, small, style, flex }) {
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
        flex && { flex: 1 },
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

const BUTTON_KINDS = {
  primary: { bg: colors.cyan700, border: colors.cyan700, text: '#fff' },
  dark: { bg: colors.slate900, border: colors.slate900, text: '#fff' },
  secondary: { bg: colors.slate100, border: colors.slate100, text: colors.slate700 },
  danger: { bg: colors.rose600, border: colors.rose600, text: '#fff' },
  success: { bg: colors.emerald600, border: colors.emerald600, text: '#fff' },
  outline: { bg: '#fff', border: colors.slate300, text: colors.slate700 },
};

/** Count bubble for tabs and bed chips. */
export function CountBadge({ count, toneName = 'critical', dot }) {
  if (!dot && !count) return null;
  const t = tone(toneName);
  return (
    <View style={[dot ? styles.dot : styles.countBadge, { backgroundColor: t.solid }]}>
      {dot ? null : <Text style={styles.countBadgeText}>{count > 99 ? '99+' : count}</Text>}
    </View>
  );
}

/** A small rounded label. */
export function Tag({ label, toneName = 'default', solid }) {
  const t = tone(toneName);
  return (
    <View style={[styles.tag, { backgroundColor: solid ? t.solid : t.bg, borderColor: solid ? t.solid : t.border }]}>
      <Text style={[styles.tagText, { color: solid ? '#fff' : t.text }]}>{label}</Text>
    </View>
  );
}

/** A coloured message strip: alerts, errors, notes. */
export function Banner({ title, text, toneName = 'info', onPress, right }) {
  const t = tone(toneName);
  const Wrap = onPress ? TouchableOpacity : View;
  return (
    <Wrap activeOpacity={0.85} onPress={onPress} style={[styles.banner, { backgroundColor: t.bg, borderColor: t.border }]}>
      <View style={[styles.bannerBar, { backgroundColor: t.solid }]} />
      <View style={{ flex: 1 }}>
        {title ? <Text style={[styles.bannerTitle, { color: t.text }]}>{title}</Text> : null}
        {text ? <Text style={[styles.bannerText, { color: t.text }]}>{text}</Text> : null}
      </View>
      {right ?? null}
    </Wrap>
  );
}

/** Label on the left, value on the right. */
export function Row({ label, value, valueTone, bold }) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text
        style={[styles.rowValue, bold && { fontWeight: '800' }, valueTone && { color: tone(valueTone).text }]}
        numberOfLines={3}
      >
        {value == null || value === '' ? '—' : value}
      </Text>
    </View>
  );
}

export function EmptyNote({ text }) {
  return <Text style={styles.empty}>{text}</Text>;
}

export function SectionTitle({ eyebrow, title, right }) {
  return (
    <View style={styles.sectionTitleRow}>
      <View style={{ flex: 1 }}>
        {eyebrow ? <Text style={styles.sectionEyebrow}>{eyebrow}</Text> : null}
        {title ? <Text style={styles.sectionTitle}>{title}</Text> : null}
      </View>
      {right ?? null}
    </View>
  );
}

/** Thin progress bar (0-100). */
export function Progress({ percent, toneName = 'info' }) {
  const t = tone(toneName);
  const p = Math.max(0, Math.min(100, Number(percent) || 0));
  return (
    <View style={styles.progressTrack}>
      <View style={[styles.progressFill, { width: `${p}%`, backgroundColor: t.solid }]} />
    </View>
  );
}

// "Now", "15 min ago", ... sent to the server as minutes_ago.
export const TIME_OFFSETS = [
  { label: 'Now', minutes: 0 },
  { label: '15 min ago', minutes: 15 },
  { label: '30 min ago', minutes: 30 },
  { label: '1 h ago', minutes: 60 },
  { label: '2 h ago', minutes: 120 },
  { label: '4 h ago', minutes: 240 },
];

export function TimeOffsetPicker({ value, onChange }) {
  return (
    <View style={styles.chipWrap}>
      {TIME_OFFSETS.map((o) => (
        <Chip key={o.minutes} small label={o.label} selected={value === o.minutes} onPress={() => onChange(o.minutes)} />
      ))}
    </View>
  );
}

export const shared = StyleSheet.create({
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  label: {
    marginTop: 14,
    marginBottom: 6,
    color: colors.slate500,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.6,
  },
  input: {
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: '#fff',
    borderRadius: radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: colors.slate900,
  },
  textarea: {
    minHeight: 84,
    textAlignVertical: 'top',
  },
  hint: {
    marginTop: 4,
    color: colors.muted,
    fontSize: 11,
  },
  error: {
    marginTop: 12,
    color: colors.rose600,
    fontSize: 12,
    fontWeight: '700',
  },
  meta: {
    color: colors.muted,
    fontSize: 11,
  },
});

const styles = StyleSheet.create({
  card: {
    backgroundColor: '#fff',
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.07)',
    marginBottom: 12,
    shadowColor: '#0f172a',
    shadowOpacity: 0.04,
    shadowOffset: { width: 0, height: 6 },
    shadowRadius: 10,
    elevation: 1,
  },
  cardPadded: { padding: 12 },
  cardHead: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 8,
    gap: 8,
  },
  cardTitle: {
    color: colors.muted,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
  },
  chip: {
    borderWidth: 1,
    borderRadius: radius.pill,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  chipSmall: { paddingHorizontal: 10, paddingVertical: 6 },
  chipText: { fontSize: 13, fontWeight: '700' },
  chipTextSmall: { fontSize: 12 },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  button: {
    borderWidth: 1,
    borderRadius: radius.md,
    paddingVertical: 12,
    paddingHorizontal: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonSmall: { paddingVertical: 8, paddingHorizontal: 12 },
  buttonText: { fontSize: 14, fontWeight: '800' },
  buttonTextSmall: { fontSize: 12 },
  countBadge: {
    minWidth: 18,
    height: 18,
    paddingHorizontal: 5,
    borderRadius: 9,
    alignItems: 'center',
    justifyContent: 'center',
  },
  countBadgeText: { color: '#fff', fontSize: 10, fontWeight: '800' },
  dot: { width: 8, height: 8, borderRadius: 4 },
  tag: {
    alignSelf: 'flex-start',
    borderWidth: 1,
    borderRadius: radius.pill,
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  tagText: { fontSize: 10, fontWeight: '800', letterSpacing: 0.3 },
  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: radius.md,
    padding: 10,
    paddingLeft: 12,
    marginBottom: 10,
    gap: 10,
    overflow: 'hidden',
  },
  bannerBar: { position: 'absolute', left: 0, top: 0, bottom: 0, width: 4 },
  bannerTitle: { fontSize: 13, fontWeight: '800' },
  bannerText: { marginTop: 2, fontSize: 12, lineHeight: 17 },
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    paddingVertical: 5,
    gap: 16,
  },
  rowLabel: { color: colors.muted, fontSize: 12 },
  rowValue: { flex: 1, color: colors.slate900, fontSize: 12, fontWeight: '600', textAlign: 'right' },
  empty: { color: colors.muted, fontSize: 12, paddingVertical: 4 },
  sectionTitleRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    justifyContent: 'space-between',
    marginTop: 6,
    marginBottom: 8,
    gap: 8,
  },
  sectionEyebrow: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 2.2 },
  sectionTitle: { marginTop: 2, color: colors.slate900, fontSize: 16, fontWeight: '800' },
  progressTrack: {
    height: 6,
    borderRadius: 3,
    backgroundColor: colors.slate100,
    overflow: 'hidden',
  },
  progressFill: { height: 6, borderRadius: 3 },
});
