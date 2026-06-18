import React from 'react';
import { View, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../theme';

// Simple touch-driven horizontal segmented slider (0 – steps).
// Avoids extra deps. Tap a segment to set the value.
export default function Slider({ value, onChange, steps = 10, color = colors.brand600 }) {
  return (
    <View style={styles.row}>
      {Array.from({ length: steps }).map((_, i) => {
        const pct = ((i + 1) / steps) * 100;
        const active = value >= pct - 100 / steps / 2;
        return (
          <TouchableOpacity
            key={i}
            activeOpacity={0.7}
            onPress={() => onChange?.(Math.round(pct))}
            style={[styles.seg, { backgroundColor: active ? color : colors.slate200 }]}
          />
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: 4,
    height: 14,
    alignItems: 'stretch',
  },
  seg: {
    flex: 1,
    borderRadius: radius.sm,
  },
});
