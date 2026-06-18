import React from 'react';
import { View, Text, StyleSheet } from 'react-native';

export default function Avatar({ initials, color = '#0ea5e9', size = 44, online = false }) {
  return (
    <View style={{ width: size, height: size }}>
      <View
        style={[
          styles.bubble,
          { backgroundColor: color, width: size, height: size, borderRadius: size / 2 },
        ]}
      >
        <Text
          style={[
            styles.text,
            { fontSize: Math.round(size * 0.4), lineHeight: Math.round(size * 0.45) },
          ]}
        >
          {initials}
        </Text>
      </View>
      {online ? (
        <View
          style={[
            styles.dot,
            {
              width: Math.max(8, size * 0.22),
              height: Math.max(8, size * 0.22),
              borderRadius: 999,
              right: 0,
              bottom: 0,
            },
          ]}
        />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  bubble: {
    alignItems: 'center',
    justifyContent: 'center',
  },
  text: {
    color: '#fff',
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  dot: {
    position: 'absolute',
    backgroundColor: '#10b981',
    borderWidth: 2,
    borderColor: '#fff',
  },
});
