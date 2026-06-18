import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../theme';

export default function Stepper({ value, onChange, min = 0, max = 100, step = 1, suffix }) {
  const dec = () => onChange?.(Math.max(min, value - step));
  const inc = () => onChange?.(Math.min(max, value + step));
  return (
    <View style={styles.wrap}>
      <TouchableOpacity onPress={dec} style={styles.btn} activeOpacity={0.8}>
        <Text style={styles.btnText}>−</Text>
      </TouchableOpacity>
      <Text style={styles.value}>
        {value}
        {suffix ? <Text style={styles.suffix}>{suffix}</Text> : null}
      </Text>
      <TouchableOpacity onPress={inc} style={styles.btn} activeOpacity={0.8}>
        <Text style={styles.btnText}>+</Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.slate100,
    borderRadius: radius.pill,
    paddingHorizontal: 6,
    paddingVertical: 4,
  },
  btn: {
    width: 32,
    height: 32,
    borderRadius: 999,
    backgroundColor: '#fff',
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: {
    color: colors.slate800,
    fontSize: 18,
    fontWeight: '800',
    lineHeight: 20,
  },
  value: {
    minWidth: 60,
    textAlign: 'center',
    color: colors.slate900,
    fontWeight: '800',
    fontSize: 16,
  },
  suffix: {
    color: colors.muted,
    fontSize: 12,
    fontWeight: '700',
  },
});
