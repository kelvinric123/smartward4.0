// Lab investigations: the HIS lab orders and their results, what is flagged,
// and when each result should be reviewed - the ward dashboard's Lab
// Investigations tab. A result is marked reviewed from here.

import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import { Banner, Button, Card, EmptyNote, SectionTitle, Tag, shared, tone } from '../ui';

const REVIEW_TONE = {
  overdue: 'critical',
  due_soon: 'warning',
  due: 'info',
  awaiting_result: 'muted',
  result_late: 'warning',
  reviewed: 'good',
};
const PRIORITY_TONE = { stat: 'critical', urgent: 'warning', routine: 'muted' };
const STATUS_TONE = { ordered: 'warning', collected: 'info', in_progress: 'info', resulted: 'good', cancelled: 'muted' };

export default function LabsTab({ chart, perform }) {
  const labs = chart.labs;
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState(null);

  if (!labs?.enabled) {
    return (
      <Card>
        <EmptyNote text="Lab Investigations are switched off for the ward (Settings › Patient Additional Info on the ward dashboard)." />
      </Card>
    );
  }

  const c = labs.counts;

  async function review(lab) {
    setBusyId(lab.id);
    setError(null);
    const result = await perform((client) => client.reviewLab(lab.id));
    setBusyId(null);
    if (!result.ok) setError(result.error);
  }

  return (
    <View>
      <SectionTitle
        eyebrow="LAB INVESTIGATIONS"
        title={`From the HIS (${labs.items.length})`}
        right={c.overdue ? <Tag label={`${c.overdue} OVERDUE`} toneName="critical" solid /> : null}
      />

      {labs.sample ? (
        <Banner
          toneName="warning"
          title="Sample data is on"
          text="Rows marked SAMPLE are demo data showing how HIS lab orders appear. They are not this patient's results."
        />
      ) : null}

      {labs.items.length ? (
        <View style={[shared.chipWrap, { marginBottom: 10 }]}>
          <Tag label={`${c.awaiting_review} awaiting review`} toneName={c.overdue ? 'critical' : c.awaiting_review ? 'warning' : 'muted'} />
          {c.critical ? <Tag label={`${c.critical} critical unreviewed`} toneName="critical" solid /> : null}
          <Tag label={`${c.pending} pending in lab`} toneName="muted" />
        </View>
      ) : null}

      {error ? <Banner toneName="critical" title="Not saved" text={error} /> : null}

      {labs.items.length === 0 ? (
        <Card>
          <EmptyNote text="No lab investigations received from the HIS for this patient." />
        </Card>
      ) : (
        labs.items.map((lab) => (
          <LabCard key={lab.id} lab={lab} busy={busyId === lab.id} onReview={() => review(lab)} />
        ))
      )}

      {labs.items.length ? (
        <Text style={styles.footnote}>
          Without a review time from the HIS, a result is due for review 1 h (STAT), 4 h (Urgent) or 24 h (Routine) after it is resulted.
        </Text>
      ) : null}
    </View>
  );
}

function LabCard({ lab, busy, onReview }) {
  const [showAll, setShowAll] = useState(false);
  const flagged = lab.results.filter((r) => r.level);
  const rt = tone(REVIEW_TONE[lab.review_state] ?? 'muted');

  return (
    <Card style={lab.review_state === 'overdue' && { borderColor: colors.rose100 }}>
      <View style={styles.head}>
        <View style={{ flex: 1 }}>
          <Text style={styles.name}>{lab.test_name}</Text>
          <Text style={styles.sub}>
            {[lab.category, lab.test_code, lab.specimen].filter(Boolean).join(' · ')}
          </Text>
        </View>
        <View style={styles.headTags}>
          {lab.sample ? <Tag label="SAMPLE" toneName="warning" solid /> : null}
          <Tag label={lab.priority_label.toUpperCase()} toneName={PRIORITY_TONE[lab.priority] ?? 'muted'} solid={lab.priority === 'stat'} />
        </View>
      </View>

      <Text style={styles.meta}>
        Ordered {lab.ordered_label}
        {lab.ordered_by ? ` by ${lab.ordered_by}` : ''} · {lab.order_no}
      </Text>

      <View style={[shared.chipWrap, { marginTop: 8, alignItems: 'center' }]}>
        <Tag label={lab.status_label} toneName={STATUS_TONE[lab.status] ?? 'muted'} />
        {lab.collected_label ? <Text style={styles.meta}>Collected {lab.collected_label}</Text> : null}
        {lab.resulted_label ? <Text style={styles.meta}>· Resulted {lab.resulted_label}</Text> : null}
      </View>

      {lab.results.length ? (
        <View style={styles.results}>
          <View style={[shared.chipWrap, { alignItems: 'center' }]}>
            {lab.flag === 'critical' ? (
              <Tag label="CRITICAL" toneName="critical" solid />
            ) : lab.flag === 'abnormal' ? (
              <Tag label="ABNORMAL" toneName="warning" />
            ) : (
              <Tag label="NORMAL" toneName="good" />
            )}
          </View>
          {/* Flagged values always show; the full panel on demand */}
          {flagged.map((r, i) => (
            <Text key={i} style={[styles.flagged, r.level === 'critical' ? styles.critical : styles.abnormal]}>
              {r.name} {r.value} {r.unit} ({r.flag})
            </Text>
          ))}
          <TouchableOpacity activeOpacity={0.7} onPress={() => setShowAll((v) => !v)}>
            <Text style={styles.toggle}>{showAll ? 'Hide results' : `All ${lab.results.length} results`}</Text>
          </TouchableOpacity>
          {showAll
            ? lab.results.map((r, i) => (
                <View key={i} style={styles.resultRow}>
                  <Text style={styles.resultName} numberOfLines={1}>{r.name}</Text>
                  <Text style={[styles.resultValue, r.level === 'critical' ? styles.critical : r.level ? styles.abnormal : null]}>
                    {r.value} {r.unit}{r.flag ? ` (${r.flag})` : ''}
                  </Text>
                  <Text style={styles.resultRange}>{r.range}</Text>
                </View>
              ))
            : null}
        </View>
      ) : (
        <Text style={[styles.meta, { marginTop: 8, fontStyle: 'italic' }]}>
          {lab.status === 'cancelled' ? 'Cancelled' : 'No result yet'}
        </Text>
      )}

      {lab.comment ? <Text style={styles.comment}>{lab.comment}</Text> : null}

      {lab.review_label ? (
        <View style={[styles.review, { backgroundColor: rt.bg, borderColor: rt.border }]}>
          <Text style={[styles.reviewText, { color: rt.text }]}>{lab.review_label}</Text>
          {lab.can_review ? <Button label="Mark reviewed" kind="success" small busy={busy} onPress={onReview} /> : null}
        </View>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  headTags: { alignItems: 'flex-end', gap: 4 },
  name: { color: colors.slate900, fontSize: 15, fontWeight: '800' },
  sub: { marginTop: 2, color: colors.slate600, fontSize: 11, fontWeight: '600' },
  meta: { marginTop: 4, color: colors.muted, fontSize: 11 },
  results: { marginTop: 10, borderTopWidth: 1, borderTopColor: colors.slate100, paddingTop: 8, gap: 3 },
  flagged: { fontSize: 13 },
  critical: { color: colors.rose700, fontWeight: '800' },
  abnormal: { color: colors.amber700, fontWeight: '700' },
  toggle: { marginTop: 4, color: colors.cyan700, fontSize: 12, fontWeight: '800' },
  resultRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 2 },
  resultName: { flex: 1, color: colors.slate600, fontSize: 12 },
  resultValue: { color: colors.slate900, fontSize: 12, fontWeight: '700' },
  resultRange: { width: 76, textAlign: 'right', color: colors.mutedSoft, fontSize: 11 },
  comment: { marginTop: 8, color: colors.slate600, fontSize: 12, fontStyle: 'italic' },
  review: {
    marginTop: 10,
    borderWidth: 1,
    borderRadius: radius.sm,
    paddingHorizontal: 10,
    paddingVertical: 8,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 8,
  },
  reviewText: { flex: 1, fontSize: 12, fontWeight: '800' },
  footnote: { marginBottom: 12, color: colors.mutedSoft, fontSize: 11 },
});
