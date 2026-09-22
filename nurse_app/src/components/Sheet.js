// Bottom sheet for the chart's forms (record a dose, add an I/O entry, ...).
// Same look as the login screen's settings sheet. Tapping the backdrop or
// the Android back button closes it, unless it is busy saving.

import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  Modal,
  TouchableOpacity,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors } from '../theme';

export default function Sheet({ visible, eyebrow, title, subtitle, onClose, busy, footer, children }) {
  const insets = useSafeAreaInsets();
  const close = () => {
    if (!busy) onClose?.();
  };

  return (
    <Modal visible={visible} animationType="slide" transparent statusBarTranslucent onRequestClose={close}>
      <KeyboardAvoidingView
        style={styles.backdrop}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <TouchableOpacity style={styles.backdropTap} activeOpacity={1} onPress={close} />
        <View style={styles.sheet}>
          <View style={styles.handle} />
          <ScrollView
            keyboardShouldPersistTaps="handled"
            contentContainerStyle={styles.body}
            showsVerticalScrollIndicator={false}
          >
            {eyebrow ? <Text style={styles.eyebrow}>{eyebrow}</Text> : null}
            {title ? <Text style={styles.title}>{title}</Text> : null}
            {subtitle ? <Text style={styles.subtitle}>{subtitle}</Text> : null}
            {children}
          </ScrollView>
          {footer ? (
            <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) }]}>{footer}</View>
          ) : (
            <View style={{ height: Math.max(insets.bottom, 12) }} />
          )}
        </View>
      </KeyboardAvoidingView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(2,6,23,0.55)',
    justifyContent: 'flex-end',
  },
  backdropTap: {
    flex: 1,
  },
  sheet: {
    maxHeight: '88%',
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
  },
  handle: {
    alignSelf: 'center',
    width: 44,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.slate300,
    marginTop: 8,
  },
  body: {
    padding: 18,
    paddingBottom: 12,
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
    fontSize: 18,
    fontWeight: '800',
  },
  subtitle: {
    marginTop: 6,
    color: colors.muted,
    fontSize: 12,
    lineHeight: 17,
  },
  footer: {
    flexDirection: 'row',
    gap: 10,
    paddingHorizontal: 18,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    backgroundColor: colors.surface,
  },
});
