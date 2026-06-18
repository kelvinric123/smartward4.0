import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Modal } from 'react-native';
import { colors, radius, shadow } from '../../theme';
import SectionCard from '../../components/SectionCard';
import Pill from '../../components/Pill';
import { api } from '../../data/mockData';

function CategoryCard({ category, onPress }) {
  return (
    <TouchableOpacity
      onPress={() => onPress(category)}
      activeOpacity={0.9}
      style={[
        styles.card,
        category.urgent && { borderColor: colors.rose200 ?? '#fecaca' },
      ]}
    >
      <View style={[styles.bubble, { backgroundColor: category.color }]}>
        <Text style={styles.bubbleEmoji}>{category.emoji}</Text>
      </View>
      <Text style={styles.label}>{category.label}</Text>
      <Text style={styles.routes}>→ {category.routes_to}</Text>
      <View style={styles.etaRow}>
        <Text style={styles.etaLabel}>EST</Text>
        <Text style={styles.etaValue}>{category.estimated_response}</Text>
      </View>
      {category.urgent ? (
        <View style={styles.urgentTag}>
          <Pill label="URGENT" bg={colors.rose600} color="#fff" />
        </View>
      ) : null}
    </TouchableOpacity>
  );
}

export default function RequestsTab({ requestCategories, openInitial = false, onConsumeOpen }) {
  const [active, setActive] = useState(null);
  const [history, setHistory] = useState([]);

  React.useEffect(() => {
    if (openInitial) {
      // No-op — quick FAB just lands us on this tab.
      onConsumeOpen?.();
    }
  }, [openInitial]);

  const confirm = async () => {
    if (!active) return;
    const res = await api.submitRequest(active.id);
    setHistory((h) => [
      { ticket_id: res.ticket_id, category: active, sent_at: new Date().toLocaleTimeString() },
      ...h,
    ]);
    setActive(null);
  };

  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <Text style={styles.headerEyebrow}>SMART CALL</Text>
        <Text style={styles.headerTitle}>What do you need?</Text>
        <Text style={styles.headerHint}>
          Pick a category — your request goes straight to the right person, not the whole ward.
        </Text>
      </View>

      <View style={styles.grid}>
        {requestCategories.map((c) => (
          <CategoryCard key={c.id} category={c} onPress={setActive} />
        ))}
      </View>

      <SectionCard eyebrow="HISTORY" title="Your recent requests" style={{ marginTop: 16 }}>
        {history.length === 0 ? (
          <Text style={styles.empty}>No requests yet today.</Text>
        ) : (
          <View style={{ gap: 10 }}>
            {history.map((h) => (
              <View key={h.ticket_id} style={styles.historyRow}>
                <View style={[styles.historyDot, { backgroundColor: h.category.color }]} />
                <View style={{ flex: 1 }}>
                  <Text style={styles.historyLabel}>{h.category.label}</Text>
                  <Text style={styles.historyMeta}>
                    Sent {h.sent_at} → {h.category.routes_to}
                  </Text>
                </View>
                <Pill label="SENT" bg={colors.emerald100} color={colors.emerald700} />
              </View>
            ))}
          </View>
        )}
      </SectionCard>

      {/* Confirm modal */}
      <Modal visible={!!active} transparent animationType="fade" onRequestClose={() => setActive(null)}>
        <View style={styles.modalBg}>
          <View style={styles.modalCard}>
            {active ? (
              <>
                <View style={[styles.modalBubble, { backgroundColor: active.color }]}>
                  <Text style={styles.modalEmoji}>{active.emoji}</Text>
                </View>
                <Text style={styles.modalTitle}>{active.label}</Text>
                <Text style={styles.modalBody}>{active.description}</Text>
                <View style={styles.modalMeta}>
                  <View style={styles.modalMetaCell}>
                    <Text style={styles.modalMetaLabel}>ROUTED TO</Text>
                    <Text style={styles.modalMetaValue}>{active.routes_to}</Text>
                  </View>
                  <View style={styles.modalMetaCell}>
                    <Text style={styles.modalMetaLabel}>EST. RESPONSE</Text>
                    <Text style={styles.modalMetaValue}>{active.estimated_response}</Text>
                  </View>
                </View>
                <View style={styles.modalActions}>
                  <TouchableOpacity style={styles.modalCancel} onPress={() => setActive(null)}>
                    <Text style={styles.modalCancelText}>Cancel</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[styles.modalConfirm, { backgroundColor: active.color }]}
                    onPress={confirm}
                  >
                    <Text style={styles.modalConfirmText}>Send request</Text>
                  </TouchableOpacity>
                </View>
              </>
            ) : null}
          </View>
        </View>
      </Modal>

      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 14, paddingBottom: 120 },

  header: { marginBottom: 14 },
  headerEyebrow: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 2.2 },
  headerTitle: { marginTop: 2, fontSize: 22, fontWeight: '800', color: colors.slate900 },
  headerHint: { marginTop: 4, color: colors.muted, fontSize: 12 },

  grid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
  },
  card: {
    width: '48.5%',
    backgroundColor: '#fff',
    borderRadius: radius.xl,
    padding: 14,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    ...shadow.sm,
  },
  bubble: {
    width: 44,
    height: 44,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
  },
  bubbleEmoji: { fontSize: 22 },
  label: {
    marginTop: 10,
    fontSize: 14,
    fontWeight: '800',
    color: colors.slate900,
  },
  routes: {
    marginTop: 2,
    fontSize: 11,
    color: colors.muted,
    fontWeight: '600',
  },
  etaRow: {
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'baseline',
    gap: 6,
  },
  etaLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.4,
    color: colors.muted,
  },
  etaValue: {
    fontSize: 12,
    fontWeight: '800',
    color: colors.slate900,
  },
  urgentTag: {
    position: 'absolute',
    top: 10,
    right: 10,
  },

  empty: {
    color: colors.muted,
    fontSize: 12,
    fontStyle: 'italic',
  },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  historyDot: {
    width: 10,
    height: 10,
    borderRadius: 999,
  },
  historyLabel: {
    fontSize: 13,
    fontWeight: '800',
    color: colors.slate900,
  },
  historyMeta: {
    fontSize: 11,
    color: colors.muted,
    marginTop: 2,
  },

  modalBg: {
    flex: 1,
    backgroundColor: 'rgba(15,23,42,0.55)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  modalCard: {
    width: '100%',
    maxWidth: 360,
    backgroundColor: '#fff',
    borderRadius: radius.xxl,
    padding: 20,
    alignItems: 'center',
    ...shadow.lg,
  },
  modalBubble: {
    width: 64,
    height: 64,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modalEmoji: { fontSize: 30 },
  modalTitle: {
    marginTop: 12,
    fontSize: 20,
    fontWeight: '800',
    color: colors.slate900,
  },
  modalBody: {
    marginTop: 6,
    color: colors.muted,
    fontSize: 13,
    textAlign: 'center',
  },
  modalMeta: {
    marginTop: 14,
    flexDirection: 'row',
    gap: 12,
    alignSelf: 'stretch',
  },
  modalMetaCell: {
    flex: 1,
    backgroundColor: colors.slate50,
    borderRadius: radius.md,
    padding: 10,
    alignItems: 'center',
  },
  modalMetaLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.4,
    color: colors.muted,
  },
  modalMetaValue: {
    marginTop: 4,
    fontSize: 12,
    fontWeight: '800',
    color: colors.slate900,
  },
  modalActions: {
    marginTop: 16,
    flexDirection: 'row',
    gap: 10,
    alignSelf: 'stretch',
  },
  modalCancel: {
    flex: 1,
    paddingVertical: 12,
    borderRadius: radius.pill,
    backgroundColor: colors.slate100,
    alignItems: 'center',
  },
  modalCancelText: { color: colors.slate700, fontWeight: '800' },
  modalConfirm: {
    flex: 1.5,
    paddingVertical: 12,
    borderRadius: radius.pill,
    alignItems: 'center',
  },
  modalConfirmText: { color: '#fff', fontWeight: '800' },
});
