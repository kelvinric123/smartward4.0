import React from 'react';
import {
  Modal,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { colors, radius } from '../theme';
import Pill from './Pill';

function ewsBg(ews) {
  if (ews >= 7) return { bg: colors.rose600, color: '#fff' };
  if (ews >= 5) return { bg: colors.rose500, color: '#fff' };
  if (ews >= 3) return { bg: colors.amber500, color: '#fff' };
  return { bg: colors.emerald500, color: '#fff' };
}

export default function CriticalListModal({ visible, onClose, beds, onSelect }) {
  const critical = (beds ?? [])
    .filter((b) => b.ews_has_vitals && b.ews != null && b.ews >= 5)
    .sort((a, b) => (b.ews ?? 0) - (a.ews ?? 0));

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.sheet}>
          <View style={styles.sheetHandle} />
          <View style={styles.sheetHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.sheetEyebrow}>CRITICAL PATIENTS</Text>
              <Text style={styles.sheetTitle}>EWS ≥ 5 · under your care</Text>
              <Text style={styles.sheetMeta}>
                {critical.length} patient{critical.length === 1 ? '' : 's'} needing urgent review
              </Text>
            </View>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn} activeOpacity={0.85}>
              <Text style={styles.closeBtnText}>Close</Text>
            </TouchableOpacity>
          </View>

          {critical.length === 0 ? (
            <View style={styles.empty}>
              <Text style={styles.emptyTitle}>No critical patients</Text>
              <Text style={styles.emptyMeta}>
                None of your beds currently have an EWS of 5 or higher.
              </Text>
            </View>
          ) : (
            <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
              {critical.map((b) => {
                const tone = ewsBg(b.ews);
                return (
                  <TouchableOpacity
                    key={b.id}
                    activeOpacity={0.85}
                    onPress={() => onSelect?.(b)}
                    style={styles.row}
                  >
                    <View style={[styles.ewsBadge, { backgroundColor: tone.bg }]}>
                      <Text style={[styles.ewsLabel, { color: tone.color }]}>EWS</Text>
                      <Text style={[styles.ewsValue, { color: tone.color }]}>{b.ews}</Text>
                    </View>
                    <View style={{ flex: 1, paddingHorizontal: 12 }}>
                      <Text style={styles.name} numberOfLines={1}>{b.patient_name}</Text>
                      <Text style={styles.meta} numberOfLines={1}>
                        Bed {b.number} · {b.ward_name}
                      </Text>
                      <Text style={styles.dx} numberOfLines={2}>
                        {b.primary_diagnosis ?? '—'}
                      </Text>
                      <View style={styles.pillRow}>
                        {b.pending_review ? (
                          <Pill label="REVIEW DUE" bg={colors.amber100} color={colors.amber700} />
                        ) : null}
                        {b.pending_orders > 0 ? (
                          <Pill
                            label={`${b.pending_orders} ORDER${b.pending_orders > 1 ? 'S' : ''}`}
                            bg={colors.indigo100}
                            color={colors.indigo700}
                          />
                        ) : null}
                        {b.is_outside ? (
                          <Pill label="OFF WARD" bg={colors.slate200} color={colors.slate700} />
                        ) : null}
                      </View>
                    </View>
                    <Text style={styles.chev}>›</Text>
                  </TouchableOpacity>
                );
              })}
            </ScrollView>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(2,6,23,0.55)',
    justifyContent: 'flex-end',
  },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '85%',
    paddingBottom: 16,
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 44,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.slate300,
    marginTop: 8,
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingHorizontal: 18,
    paddingTop: 12,
    paddingBottom: 8,
  },
  sheetEyebrow: {
    color: colors.rose600,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  sheetTitle: {
    marginTop: 2,
    color: colors.slate900,
    fontSize: 18,
    fontWeight: '800',
  },
  sheetMeta: {
    marginTop: 2,
    color: colors.muted,
    fontSize: 11,
  },
  closeBtn: {
    backgroundColor: colors.slate900,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: radius.md,
  },
  closeBtnText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
  },
  scroll: {
    paddingHorizontal: 14,
    paddingBottom: 24,
    gap: 10,
  },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    padding: 12,
  },
  ewsBadge: {
    width: 60,
    height: 60,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  ewsLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.6,
  },
  ewsValue: {
    fontSize: 22,
    fontWeight: '900',
  },
  name: {
    fontSize: 15,
    fontWeight: '800',
    color: colors.slate900,
  },
  meta: {
    marginTop: 2,
    fontSize: 11,
    color: colors.muted,
  },
  dx: {
    marginTop: 4,
    fontSize: 12,
    color: colors.slate700,
  },
  pillRow: {
    marginTop: 6,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 4,
  },
  chev: {
    color: colors.muted,
    fontSize: 26,
    fontWeight: '700',
    paddingLeft: 4,
  },
  empty: {
    padding: 24,
    alignItems: 'center',
  },
  emptyTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.slate900,
  },
  emptyMeta: {
    marginTop: 6,
    fontSize: 12,
    color: colors.muted,
    textAlign: 'center',
  },
});
