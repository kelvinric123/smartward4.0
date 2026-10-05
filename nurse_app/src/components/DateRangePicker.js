// A month calendar for picking a range of days (first and last day of leave),
// in plain views, so it needs no native date picker. Tap the first day, then
// the last; tapping again starts a new range. Days before `minDate` are off.

import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../theme';

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];
const pad = (n) => String(n).padStart(2, '0');
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export default function DateRangePicker({ start, end, onChange, minDate }) {
  const first = start ? new Date(`${start}T00:00:00`) : new Date();
  const [month, setMonth] = useState(new Date(first.getFullYear(), first.getMonth(), 1));
  const min = minDate ?? ymd(new Date());

  // Monday-first grid, padded to whole weeks
  const lead = (month.getDay() + 6) % 7;
  const days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
  const cells = [...Array(lead).fill(null), ...Array.from({ length: days }, (_, i) => new Date(month.getFullYear(), month.getMonth(), i + 1))];
  while (cells.length % 7) cells.push(null);

  function tap(date) {
    const key = ymd(date);
    if (!start || end || key < start) onChange(key, null);
    else onChange(start, key);
  }

  function shift(by) {
    setMonth(new Date(month.getFullYear(), month.getMonth() + by, 1));
  }

  return (
    <View style={styles.wrap}>
      <View style={styles.head}>
        <TouchableOpacity onPress={() => shift(-1)} style={styles.nav} activeOpacity={0.7}>
          <Text style={styles.navText}>‹</Text>
        </TouchableOpacity>
        <Text style={styles.month}>
          {MONTHS[month.getMonth()]} {month.getFullYear()}
        </Text>
        <TouchableOpacity onPress={() => shift(1)} style={styles.nav} activeOpacity={0.7}>
          <Text style={styles.navText}>›</Text>
        </TouchableOpacity>
      </View>
      <View style={styles.grid}>
        {WEEKDAYS.map((w) => (
          <Text key={w} style={styles.weekday}>{w}</Text>
        ))}
        {cells.map((date, i) => {
          if (!date) return <View key={`x${i}`} style={styles.cell} />;
          const key = ymd(date);
          const disabled = key < min;
          const edge = key === start || key === end;
          const inside = start && end && key > start && key < end;
          return (
            <TouchableOpacity
              key={key}
              disabled={disabled}
              onPress={() => tap(date)}
              activeOpacity={0.7}
              style={[styles.cell, inside && styles.inside, edge && styles.edge]}
            >
              <Text style={[styles.dayText, disabled && styles.disabled, inside && styles.insideText, edge && styles.edgeText]}>{date.getDate()}</Text>
            </TouchableOpacity>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: { backgroundColor: '#fff', borderWidth: 1, borderColor: colors.slate200, borderRadius: radius.md, padding: 8 },
  head: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 4 },
  nav: { width: 36, height: 32, alignItems: 'center', justifyContent: 'center' },
  navText: { color: colors.cyan700, fontSize: 22, fontWeight: '800' },
  month: { color: colors.slate900, fontSize: 14, fontWeight: '800' },
  grid: { flexDirection: 'row', flexWrap: 'wrap' },
  weekday: { width: `${100 / 7}%`, textAlign: 'center', color: colors.mutedSoft, fontSize: 10, fontWeight: '800', paddingVertical: 4 },
  cell: { width: `${100 / 7}%`, height: 36, alignItems: 'center', justifyContent: 'center', borderRadius: radius.sm },
  dayText: { color: colors.slate800, fontSize: 13, fontWeight: '700' },
  disabled: { color: colors.slate300 },
  inside: { backgroundColor: colors.cyan50 },
  insideText: { color: colors.cyan900 },
  edge: { backgroundColor: colors.cyan700 },
  edgeText: { color: '#fff' },
});
