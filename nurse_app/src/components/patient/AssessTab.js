// Assessment scales: the scales the patient's ward uses (Morse, Braden, pain,
// ...) with the latest score, its band and when the next one is due - the
// ward dashboard's clinical indicator tabs. Scales scored item by item, or by
// a total, are scored here; a screen asked question by question (the C-SSRS)
// and monitor readings are recorded on the ward dashboard.

import React, { useEffect, useState } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import Sheet from '../Sheet';
import { Button, Card, Chip, EmptyNote, SectionTitle, Tag, shared, tone } from '../ui';

const BAND_TONE = { low: 'good', moderate: 'warning', high: 'critical' };
const DUE_TONE = { overdue: 'critical', due: 'warning', ok: 'muted' };

function bandFor(scale, score) {
  return scale.bands.find((b) => (b.min == null || score >= b.min) && (b.max == null || score <= b.max)) ?? null;
}

export default function AssessTab({ chart, perform }) {
  const a = chart.assessments;
  const [scoring, setScoring] = useState(null);

  if (!a.scales.length) {
    return (
      <Card>
        <EmptyNote text="This ward has no assessment scales set up (Ward Types on the ward dashboard)." />
      </Card>
    );
  }

  return (
    <View>
      <SectionTitle
        eyebrow="ASSESSMENT SCALES"
        title={`This ward's scales (${a.scales.length})`}
        right={
          a.counts.overdue ? (
            <Tag label={`${a.counts.overdue} OVERDUE`} toneName="critical" solid />
          ) : a.counts.due ? (
            <Tag label={`${a.counts.due} DUE`} toneName="warning" />
          ) : null
        }
      />
      {a.scales.map((scale) => (
        <ScaleCard key={scale.id} scale={scale} onScore={() => setScoring(scale)} />
      ))}
      <ScoreSheet scale={scoring} onClose={() => setScoring(null)} perform={perform} />
    </View>
  );
}

function ScaleCard({ scale, onScore }) {
  const [showHistory, setShowHistory] = useState(false);
  const latest = scale.latest;
  const m = scale.monitoring;
  const dt = tone(DUE_TONE[m?.state] ?? 'muted');

  return (
    <Card style={m?.state === 'overdue' && { borderColor: colors.rose100 }}>
      <View style={styles.head}>
        <View style={{ flex: 1 }}>
          <Text style={styles.name}>{scale.name}</Text>
          <Text style={styles.sub}>{scale.category}</Text>
        </View>
        <Tag label={scale.code} toneName="muted" />
      </View>

      {latest ? (
        <View style={styles.latest}>
          {latest.score != null ? <Text style={styles.score}>{latest.score}</Text> : null}
          <View style={{ flex: 1 }}>
            {latest.band_label ? <Tag label={latest.band_label} toneName={BAND_TONE[latest.band_tone] ?? 'muted'} solid={latest.band_tone === 'high'} /> : null}
            {latest.breakdown ? <Text style={styles.breakdown}>{latest.breakdown}</Text> : null}
            <Text style={shared.meta}>
              {latest.time_label}
              {latest.by ? ` · ${latest.by}` : ''}
            </Text>
          </View>
        </View>
      ) : (
        <Text style={[shared.meta, { marginTop: 8 }]}>Not scored yet this admission.</Text>
      )}
      {latest?.notes ? <Text style={styles.notes}>{latest.notes}</Text> : null}

      {m ? (
        <View style={[styles.due, { backgroundColor: dt.bg, borderColor: dt.border }]}>
          <Text style={[styles.dueText, { color: dt.text }]}>{m.label}</Text>
          <Text style={[shared.meta, { color: dt.text }]}>Every {m.interval} · due {m.due_label}</Text>
        </View>
      ) : null}

      <View style={styles.actions}>
        {scale.history.length > 1 ? (
          <TouchableOpacity activeOpacity={0.7} onPress={() => setShowHistory((v) => !v)}>
            <Text style={styles.toggle}>{showHistory ? 'Hide earlier' : `Earlier (${scale.history.length - 1})`}</Text>
          </TouchableOpacity>
        ) : (
          <View />
        )}
        {scale.can_score ? (
          <Button label="Score now" small onPress={onScore} />
        ) : (
          <Text style={shared.meta}>Recorded on the ward dashboard</Text>
        )}
      </View>

      {showHistory
        ? scale.history.slice(1).map((h) => (
            <View key={h.id} style={styles.historyRow}>
              <Text style={styles.historyTime}>{h.time_label}</Text>
              <Text style={styles.historyText} numberOfLines={2}>
                {[h.score != null ? `${h.score}` : null, h.band_label, h.breakdown, h.by].filter(Boolean).join(' · ')}
              </Text>
            </View>
          ))
        : null}
    </Card>
  );
}

function ScoreSheet({ scale, onClose, perform }) {
  const [answers, setAnswers] = useState([]);
  const [score, setScore] = useState('');
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!scale) return;
    setAnswers(scale.items.map(() => null));
    setScore('');
    setNotes('');
    setError(null);
  }, [scale]);

  if (!scale) return <Sheet visible={false} onClose={onClose} />;

  const itemised = scale.kind === 'scored';
  const answered = answers.filter((v) => v != null).length;
  const total = itemised ? answers.reduce((n, v) => n + (v ?? 0), 0) : score === '' ? null : Number(score);
  const complete = itemised ? answered === scale.items.length : score !== '';
  const band = complete && total != null ? bandFor(scale, total) : null;
  const choices = !itemised && scale.score_max != null && scale.score_max <= 10
    ? Array.from({ length: scale.score_max - (scale.score_min ?? 0) + 1 }, (_, i) => (scale.score_min ?? 0) + i)
    : null;

  async function save() {
    if (!complete) {
      setError(itemised ? 'Score every item before saving.' : 'Enter a score before saving.');
      return;
    }
    setBusy(true);
    setError(null);
    const body = itemised ? { item_scores: answers, notes: notes.trim() || null } : { score: Number(score), notes: notes.trim() || null };
    const result = await perform((c) => c.scoreAssessment(scale.id, body));
    setBusy(false);
    if (result.ok) onClose();
    else setError(result.error);
  }

  return (
    <Sheet
      visible
      busy={busy}
      onClose={onClose}
      eyebrow={`SCORE ${scale.code}`}
      title={scale.name}
      subtitle={scale.purpose}
      footer={
        <>
          <Button label="Cancel" kind="secondary" flex onPress={onClose} disabled={busy} />
          <Button label={complete ? `Save ${total}${band ? ` · ${band.label}` : ''}` : 'Save score'} flex busy={busy} onPress={save} />
        </>
      }
    >
      {itemised
        ? scale.items.map((item, i) => (
            <View key={i}>
              <Text style={shared.label}>
                {i + 1}. {item.name.toUpperCase()}
                {item.abbr ? ` (${item.abbr})` : ''}
              </Text>
              <View style={shared.chipWrap}>
                {item.options.map((o) => (
                  <Chip
                    key={`${o.value}-${o.label}`}
                    small
                    label={`${o.label} · ${o.value}`}
                    selected={answers[i] === o.value}
                    onPress={() => setAnswers((prev) => prev.map((v, j) => (j === i ? o.value : v)))}
                  />
                ))}
              </View>
            </View>
          ))
        : (
          <>
            <Text style={shared.label}>
              SCORE{scale.score_max != null ? ` (${scale.score_min ?? 0} TO ${scale.score_max})` : ''}
            </Text>
            {choices ? (
              <View style={shared.chipWrap}>
                {choices.map((v) => (
                  <Chip key={v} small label={`${v}`} selected={score === String(v)} onPress={() => setScore(String(v))} />
                ))}
              </View>
            ) : (
              <TextInput
                style={shared.input}
                value={score}
                onChangeText={(t) => setScore(t.replace(/[^0-9]/g, ''))}
                keyboardType="number-pad"
                placeholder="Total score"
                placeholderTextColor={colors.mutedSoft}
              />
            )}
          </>
        )}

      <View style={styles.preview}>
        <Text style={styles.previewText}>
          {itemised ? `${answered} of ${scale.items.length} items · total ${total}` : total != null ? `Score ${total}` : 'No score yet'}
        </Text>
        {band ? <Tag label={band.label} toneName={BAND_TONE[band.tone] ?? 'muted'} solid={band.tone === 'high'} /> : null}
      </View>
      {scale.note ? <Text style={shared.hint}>{scale.note}</Text> : null}

      <Text style={shared.label}>NOTE (OPTIONAL)</Text>
      <TextInput
        style={shared.input}
        value={notes}
        onChangeText={setNotes}
        maxLength={1000}
        placeholder="Anything to note"
        placeholderTextColor={colors.mutedSoft}
      />
      {error ? <Text style={shared.error}>{error}</Text> : null}
    </Sheet>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  name: { color: colors.slate900, fontSize: 15, fontWeight: '800' },
  sub: { marginTop: 2, color: colors.slate600, fontSize: 11, fontWeight: '600' },
  latest: { marginTop: 10, flexDirection: 'row', alignItems: 'center', gap: 12 },
  score: { color: colors.slate900, fontSize: 28, fontWeight: '800', minWidth: 40 },
  breakdown: { marginTop: 4, color: colors.slate700, fontSize: 12, fontWeight: '700' },
  notes: { marginTop: 6, color: colors.slate600, fontSize: 12, fontStyle: 'italic' },
  due: { marginTop: 10, borderWidth: 1, borderRadius: radius.sm, paddingHorizontal: 10, paddingVertical: 7 },
  dueText: { fontSize: 12, fontWeight: '800' },
  actions: { marginTop: 10, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8 },
  toggle: { color: colors.cyan700, fontSize: 12, fontWeight: '800' },
  historyRow: { flexDirection: 'row', gap: 8, paddingVertical: 4, borderTopWidth: 1, borderTopColor: colors.slate100, marginTop: 4 },
  historyTime: { width: 74, color: colors.slate500, fontSize: 11, fontWeight: '700' },
  historyText: { flex: 1, color: colors.slate700, fontSize: 11 },
  preview: { marginTop: 14, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 8, backgroundColor: colors.slate100, borderRadius: radius.sm, padding: 10 },
  previewText: { color: colors.slate700, fontSize: 13, fontWeight: '800' },
});
