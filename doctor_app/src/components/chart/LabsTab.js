// Labs tab: the lab investigations Patient Details lists, from the HIS. Results
// waiting for review come first, overdue ones at the top, each with its flagged
// values; a consultant marks a result reviewed here, as the ward does.

import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import { Section, Badge, Button, Banner, Empty, tone } from './parts';

const PRIORITY_TONES = { stat: 'critical', urgent: 'warning', routine: 'muted' };
const STATUS_TONES = { ordered: 'warning', collected: 'info', in_progress: 'indigo', resulted: 'good', cancelled: 'muted' };
const REVIEW_TONES = {
  overdue: 'critical',
  due_soon: 'warning',
  due: 'info',
  awaiting_result: 'muted',
  result_late: 'warning',
  reviewed: 'good',
};
const IN_THE_LAB = ['ordered', 'collected', 'in_progress'];

function plural(count, word) {
  return `${count} ${word}${count === 1 ? '' : 's'}`;
}

function ResultRow({ row }) {
  const color = row.level === 'critical' ? colors.rose700 : row.level === 'abnormal' ? colors.amber700 : colors.slate900;
  return (
    <View style={styles.resultRow}>
      <View style={{ flex: 1, paddingRight: 8 }}>
        <Text style={styles.resultName}>{row.name}</Text>
        {row.range ? <Text style={styles.resultRange}>Range {row.range}{row.unit ? ` ${row.unit}` : ''}</Text> : null}
      </View>
      <Text style={[styles.resultValue, { color }]}>
        {row.value}
        {row.unit ? <Text style={styles.resultUnit}> {row.unit}</Text> : null}
      </Text>
      <View style={styles.flagCell}>
        {row.flag ? <Badge label={row.flag} toneName={row.level === 'critical' ? 'critical' : 'warning'} solid={row.level === 'critical'} /> : null}
      </View>
    </View>
  );
}

function LabCard({ lab, onReview, busy }) {
  const reviewTone = tone(REVIEW_TONES[lab.review_state] ?? 'muted');
  return (
    <View style={[styles.card, lab.review_state === 'overdue' && styles.cardOverdue]}>
      <View style={styles.cardTop}>
        <View style={{ flex: 1, paddingRight: 8 }}>
          <Text style={styles.testName}>{lab.test_name}</Text>
          <Text style={styles.meta}>{[lab.category, lab.specimen].filter(Boolean).join('  ·  ')}</Text>
        </View>
        <Badge label={lab.priority_label} toneName={PRIORITY_TONES[lab.priority] ?? 'muted'} solid={lab.priority === 'stat'} />
      </View>

      <View style={styles.badges}>
        <Badge label={lab.status_label} toneName={STATUS_TONES[lab.status] ?? 'muted'} />
        {lab.flag === 'critical' ? <Badge label="CRITICAL" toneName="critical" solid /> : null}
        {lab.flag === 'abnormal' ? <Badge label="ABNORMAL" toneName="warning" /> : null}
        {lab.sample ? <Badge label="SAMPLE" toneName="muted" /> : null}
      </View>

      {lab.review_label ? (
        <Text style={[styles.review, { color: reviewTone.text }]}>{lab.review_label}</Text>
      ) : null}

      {lab.results.length ? (
        <View style={styles.results}>
          {lab.results.map((row, i) => <ResultRow key={`${lab.id}-${i}`} row={row} />)}
        </View>
      ) : null}
      {lab.comment ? <Text style={styles.comment}>{lab.comment}</Text> : null}

      <Text style={styles.footer}>
        Ordered {lab.ordered_label}
        {lab.ordered_by ? ` by ${lab.ordered_by}` : ''}
        {lab.resulted_label ? `  ·  Resulted ${lab.resulted_label}` : lab.collected_label ? `  ·  Collected ${lab.collected_label}` : ''}
        {lab.order_no ? `  ·  ${lab.order_no}` : ''}
      </Text>

      {lab.can_review ? (
        <Button
          label="Mark reviewed"
          small
          busy={busy}
          onPress={() => onReview(lab)}
          style={styles.reviewButton}
        />
      ) : null}
    </View>
  );
}

export default function LabsTab({ labs, onReview }) {
  const [busyId, setBusyId] = useState(null);
  const [error, setError] = useState(null);
  const [showDone, setShowDone] = useState(false);

  if (!labs) {
    return (
      <Section>
        <Empty>Lab investigations are not available from this server yet.</Empty>
      </Section>
    );
  }
  if (!labs.enabled) {
    return (
      <Section>
        <Empty>Lab Investigations is switched off on the ward system (Settings &gt; Patient Additional Info).</Empty>
      </Section>
    );
  }

  const toReview = labs.items.filter((lab) => lab.can_review);
  const inLab = labs.items.filter((lab) => !lab.can_review && IN_THE_LAB.includes(lab.status));
  const done = labs.items.filter((lab) => !lab.can_review && !IN_THE_LAB.includes(lab.status));

  async function review(lab) {
    setBusyId(lab.id);
    setError(null);
    try {
      await onReview(lab.id);
    } catch (e) {
      setError(e?.message ?? 'Could not mark the result reviewed.');
    } finally {
      setBusyId(null);
    }
  }

  const card = (lab) => <LabCard key={lab.id} lab={lab} busy={busyId === lab.id} onReview={review} />;

  return (
    <View>
      {labs.counts.critical > 0 ? (
        <Banner
          level="critical"
          title={`${plural(labs.counts.critical, 'critical result')} to review`}
          detail="A value carries a critical flag from the lab."
        />
      ) : null}
      {labs.counts.overdue > 0 ? (
        <Banner level="critical" title={`${plural(labs.counts.overdue, 'result')} overdue for review`} />
      ) : null}
      {error ? <Banner level="critical" title="Not saved" detail={error} /> : null}

      <Section
        title="TO REVIEW"
        right={<Badge label={`${toReview.length}`} toneName={labs.counts.overdue ? 'critical' : 'indigo'} solid={toReview.length > 0} />}
      >
        {toReview.length ? toReview.map(card) : <Empty>No results waiting for review.</Empty>}
      </Section>

      {inLab.length ? (
        <Section title="IN THE LAB" right={<Badge label={`${inLab.length}`} toneName="muted" />}>
          {inLab.map(card)}
        </Section>
      ) : null}

      {done.length ? (
        <Section
          title="REVIEWED AND OTHERS"
          right={(
            <TouchableOpacity onPress={() => setShowDone((v) => !v)} activeOpacity={0.8}>
              <Text style={styles.toggle}>{showDone ? 'Hide' : `Show ${done.length}`}</Text>
            </TouchableOpacity>
          )}
        >
          {showDone ? done.map(card) : <Empty>{plural(done.length, 'investigation')} reviewed or closed.</Empty>}
        </Section>
      ) : null}

      {!labs.items.length ? (
        <Section>
          <Empty>No lab investigations received from the HIS this admission.</Empty>
        </Section>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    padding: 12,
    marginBottom: 10,
    backgroundColor: '#fff',
  },
  cardOverdue: { borderColor: colors.rose100, backgroundColor: colors.rose50 },
  cardTop: { flexDirection: 'row', alignItems: 'flex-start' },
  testName: { fontSize: 15, fontWeight: '800', color: colors.slate900 },
  meta: { marginTop: 2, fontSize: 12, color: colors.muted },
  badges: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginTop: 8 },
  review: { marginTop: 8, fontSize: 12, fontWeight: '800' },
  results: {
    marginTop: 8,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
  },
  resultRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 7,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
  },
  resultName: { fontSize: 13, fontWeight: '700', color: colors.slate700 },
  resultRange: { marginTop: 1, fontSize: 11, color: colors.mutedSoft },
  resultValue: { fontSize: 14, fontWeight: '800', textAlign: 'right' },
  resultUnit: { fontSize: 11, fontWeight: '600', color: colors.muted },
  flagCell: { width: 42, alignItems: 'flex-end' },
  comment: { marginTop: 8, fontSize: 12, color: colors.slate700, fontStyle: 'italic', lineHeight: 17 },
  footer: { marginTop: 8, fontSize: 11, color: colors.muted, lineHeight: 16 },
  reviewButton: { marginTop: 10, alignSelf: 'flex-start' },
  toggle: { fontSize: 12, fontWeight: '800', color: colors.blue700 },
});
