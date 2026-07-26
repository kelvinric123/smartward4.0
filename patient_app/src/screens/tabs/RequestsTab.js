import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  TouchableOpacity,
  Modal,
  ActivityIndicator,
} from 'react-native';
import { colors, radius, shadow } from '../../theme';
import SectionCard from '../../components/SectionCard';
import Pill from '../../components/Pill';
import { fetchPatientRequests, submitPatientRequest } from '../../api/endpoints';

const POLL_MS = 10000;
// How long a demo call waits before the pretend nurse answers.
const DEMO_RESPONSE_MS = 6000;

function timeLabel(date = new Date()) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function CategoryCard({ category, onPress, disabled }) {
  return (
    <TouchableOpacity
      onPress={() => onPress(category)}
      activeOpacity={0.9}
      disabled={disabled}
      style={[
        styles.card,
        category.urgent && { borderColor: colors.rose200 ?? '#fecaca' },
        disabled && { opacity: 0.6 },
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

export default function RequestsTab({
  requestCategories,
  openInitial = false,
  onConsumeOpen,
  bedId = null,
  demo = true,
}) {
  const [active, setActive] = useState(null);
  const [history, setHistory] = useState([]);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState(null);
  const demoTimers = useRef([]);

  useEffect(() => {
    if (openInitial) {
      // No-op — quick FAB just lands us on this tab.
      onConsumeOpen?.();
    }
  }, [openInitial]);

  // Look up the patient-facing metadata (emoji, colour, routing) for a
  // category id that came back from the server.
  const decorate = useCallback(
    (categoryId) => requestCategories.find((c) => c.id === categoryId) ?? null,
    [requestCategories]
  );

  const loadRequests = useCallback(async () => {
    if (demo || !bedId) return;
    try {
      const rows = await fetchPatientRequests(bedId);
      setHistory(
        rows.map((r) => {
          const meta = decorate(r.category);
          return {
            id: String(r.id),
            category: r.category,
            label: meta?.label ?? r.category,
            color: meta?.color ?? colors.brand600,
            emoji: meta?.emoji ?? '🛎️',
            routes_to: meta?.routes_to ?? 'Nursing team',
            sent_at: r.created_at_label,
            responded: r.responded,
            responded_by: r.responded_by,
            responded_at: r.responded_at_label,
          };
        })
      );
    } catch (e) {
      // Keep whatever we already have; the next poll retries.
    }
  }, [demo, bedId, decorate]);

  // Live mode: poll so a nurse's response shows up on its own.
  useEffect(() => {
    if (demo || !bedId) return undefined;
    loadRequests();
    const timer = setInterval(loadRequests, POLL_MS);
    return () => clearInterval(timer);
  }, [demo, bedId, loadRequests]);

  // Demo mode: clean up any pending fake-response timers on unmount.
  useEffect(() => {
    return () => {
      demoTimers.current.forEach(clearTimeout);
      demoTimers.current = [];
    };
  }, []);

  const confirm = async () => {
    if (!active || sending) return;
    setError(null);

    if (demo || !bedId) {
      const id = `demo-${Date.now()}`;
      setHistory((h) => [
        {
          id,
          category: active.id,
          label: active.label,
          color: active.color,
          emoji: active.emoji,
          routes_to: active.routes_to,
          sent_at: timeLabel(),
          responded: false,
        },
        ...h,
      ]);
      // Demo mode still shows the full round trip, just without a server.
      const t = setTimeout(() => {
        setHistory((h) =>
          h.map((row) =>
            row.id === id
              ? {
                  ...row,
                  responded: true,
                  responded_by: 'Sr. Maria Lim',
                  responded_at: timeLabel(),
                }
              : row
          )
        );
      }, DEMO_RESPONSE_MS);
      demoTimers.current.push(t);
      setActive(null);
      return;
    }

    setSending(true);
    try {
      await submitPatientRequest(bedId, {
        category: active.id,
        label: active.label,
        urgent: !!active.urgent,
      });
      setActive(null);
      await loadRequests();
    } catch (e) {
      setError(e?.message ?? 'Could not send your request. Please try again.');
    } finally {
      setSending(false);
    }
  };

  const waiting = history.filter((h) => !h.responded);
  const latestAnswered = history.find((h) => h.responded);

  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <Text style={styles.headerEyebrow}>SMART CALL</Text>
        <Text style={styles.headerTitle}>What do you need?</Text>
        <Text style={styles.headerHint}>
          Pick a category — your request goes straight to the right person, not the whole ward.
        </Text>
      </View>

      {/* Status banner — the patient's answer to "did anyone see my call?" */}
      {waiting.length > 0 ? (
        <View style={[styles.banner, styles.bannerWaiting]}>
          <ActivityIndicator size="small" color={colors.amber700} />
          <View style={{ flex: 1, marginLeft: 12 }}>
            <Text style={[styles.bannerTitle, { color: colors.amber700 }]}>
              {waiting.length === 1
                ? 'Your call has been sent'
                : `${waiting.length} calls sent`}
            </Text>
            <Text style={styles.bannerBody}>
              The nurses' station has been notified. Someone will be with you shortly.
            </Text>
          </View>
        </View>
      ) : latestAnswered ? (
        <View style={[styles.banner, styles.bannerAnswered]}>
          <Text style={styles.bannerEmoji}>✅</Text>
          <View style={{ flex: 1, marginLeft: 12 }}>
            <Text style={[styles.bannerTitle, { color: colors.emerald700 }]}>
              {latestAnswered.responded_by
                ? `${latestAnswered.responded_by} responded`
                : 'A nurse responded'}
            </Text>
            <Text style={styles.bannerBody}>
              Your "{latestAnswered.label}" request was acknowledged
              {latestAnswered.responded_at ? ` at ${latestAnswered.responded_at}` : ''}.
            </Text>
          </View>
        </View>
      ) : null}

      {error ? <Text style={styles.errorText}>{error}</Text> : null}

      <View style={styles.grid}>
        {requestCategories.map((c) => (
          <CategoryCard key={c.id} category={c} onPress={setActive} disabled={sending} />
        ))}
      </View>

      <SectionCard eyebrow="HISTORY" title="Your recent requests" style={{ marginTop: 16 }}>
        {history.length === 0 ? (
          <Text style={styles.empty}>No requests yet today.</Text>
        ) : (
          <View style={{ gap: 10 }}>
            {history.map((h) => (
              <View key={h.id} style={styles.historyRow}>
                <View style={[styles.historyDot, { backgroundColor: h.color }]} />
                <View style={{ flex: 1 }}>
                  <Text style={styles.historyLabel}>{h.label}</Text>
                  <Text style={styles.historyMeta}>
                    {h.responded
                      ? `Answered${h.responded_by ? ` by ${h.responded_by}` : ''}${
                          h.responded_at ? ` · ${h.responded_at}` : ''
                        }`
                      : `Sent ${h.sent_at} → ${h.routes_to}`}
                  </Text>
                </View>
                {h.responded ? (
                  <Pill label="RESPONDED" bg={colors.emerald100} color={colors.emerald700} />
                ) : (
                  <Pill label="WAITING" bg={colors.amber100} color={colors.amber700} />
                )}
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
                  <TouchableOpacity
                    style={styles.modalCancel}
                    onPress={() => setActive(null)}
                    disabled={sending}
                  >
                    <Text style={styles.modalCancelText}>Cancel</Text>
                  </TouchableOpacity>
                  <TouchableOpacity
                    style={[
                      styles.modalConfirm,
                      { backgroundColor: active.color },
                      sending && { opacity: 0.7 },
                    ]}
                    onPress={confirm}
                    disabled={sending}
                  >
                    <Text style={styles.modalConfirmText}>
                      {sending ? 'Sending…' : 'Send request'}
                    </Text>
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

  banner: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 14,
    borderRadius: radius.lg,
    borderWidth: 1,
    marginBottom: 14,
  },
  bannerWaiting: {
    backgroundColor: colors.amber50,
    borderColor: colors.amber100,
  },
  bannerAnswered: {
    backgroundColor: colors.emerald50,
    borderColor: colors.emerald100,
  },
  bannerEmoji: { fontSize: 22 },
  bannerTitle: { fontSize: 14, fontWeight: '800' },
  bannerBody: {
    marginTop: 2,
    color: colors.slate700,
    fontSize: 12,
    lineHeight: 17,
  },
  errorText: {
    marginBottom: 12,
    color: colors.rose600,
    fontSize: 12,
    fontWeight: '600',
  },

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
