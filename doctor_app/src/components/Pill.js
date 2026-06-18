import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { radius } from '../theme';

export default function Pill({ label, bg, color, size = 'sm' }) {
  return (
    <View style={[styles.wrap, { backgroundColor: bg }, size === 'lg' && styles.lg]}>
      <Text style={[styles.text, { color }, size === 'lg' && styles.textLg]}>{label}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: radius.pill,
    alignSelf: 'flex-start',
  },
  lg: {
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  text: {
    fontSize: 10,
    fontWeight: '700',
    letterSpacing: 0.4,
  },
  textLg: {
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0.2,
  },
});
