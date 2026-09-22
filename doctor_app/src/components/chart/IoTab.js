// I/O chart tab: one chart day's intake and output against the fluid plan,
// what the nurses charted (and what came in by itself from pumps, doses and
// blood units), signs of fluid overload and the last few days.

import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import { Badge, Banner, Button, Empty, Section, formatMl, signedMl } from './parts';

const LIMIT_TONES = {
  ok: colors.emerald500,
  near: colors.amber500,
  over: colors.rose600,
};

function Total({ label, value, color }) {
  return (
    <View style={styles.total}>
      <Text style={styles.totalLabel}>{label}</Text>
      <Text style={[styles.totalValue, color && { color }]}>{value}</Text>
    </View>
  );
}

function LimitBar({ limit }) {
  const pct = Math.min(100, limit.percent);
  const barColor = LIMIT_TONES[limit.state] ?? colors.blue600;
  return (
    <View style={styles.limitWrap}>
      <View style={styles.limitTrack}>
        <View style={[styles.limitFill, { width: `${pct}%`, backgroundColor: barColor }]} />
      </View>
      <Text style={styles.limitText}>
        {formatMl(limit.taken)} of {formatMl(limit.limit)} ({limit.percent}%)
        {limit.state === 'over' ? `  ·  ${formatMl(limit.over_by)} over` : `  ·  ${formatMl(limit.remaining)} left`}
      </Text>
    </View>
  );
}

export default function IoTab({ io, onDay, onRestrict, busy }) {
  const { day, totals, plan, limit, urine } = io;
  const balanceColor = totals.balance > 0 ? colors.blue700 : totals.balance < 0 ? colors.amber700 : colors.slate900;

  return (
    <View>
      <View style={styles.dayRow}>
        <TouchableOpacity
          style={[styles.dayBtn, !day.previous && styles.dayBtnOff]}
          disabled={!day.previous || busy}
          onPress={() => onDay(day.previous)}
          activeOpacity={0.8}
        >
          <Text style={styles.dayBtnText}>‹</Text>
        </TouchableOpacity>
        <View style={{ flex: 1, alignItems: 'center' }}>
          <Text style={styles.dayEyebrow}>CHART DAY</Text>
          <Text style={styles.dayLabel}>{day.label}</Text>
        </View>
        <TouchableOpacity
          style={[styles.dayBtn, !day.next && styles.dayBtnOff]}
          disabled={!day.next || busy}
          onPress={() => onDay(day.next)}
          activeOpacity={0.8}
        >
          <Text style={styles.dayBtnText}>›</Text>
        </TouchableOpacity>
      </View>

      {io.alerts.map((alert, i) => (
        <Banner key={`${alert.title}-${i}`} level={alert.level} title={alert.title} detail={alert.detail} />
      ))}

      <View style={styles.totalsRow}>
        <Total label="IN" value={formatMl(totals.intake)} color={colors.blue700} />
        <Total label="OUT" value={formatMl(totals.output)} color={colors.amber700} />
        <Total label="BALANCE" value={signedMl(totals.balance)} color={balanceColor} />
        <Total label="URINE" value={formatMl(totals.urine)} />
      </View>

      <Section
        title="FLUID PLAN"
        right={plan?.from_order ? <Badge label="FROM A CONSULTANT ORDER" toneName="indigo" /> : null}
      >
        {plan ? (
          <>
            <Text style={styles.planSummary}>{plan.summary}</Text>
            {plan.notes ? <Text style={styles.planNotes}>{plan.notes}</Text> : null}
            <Text style={styles.meta}>{plan.set_label}</Text>
          </>
        ) : (
          <Empty>No fluid restriction or urine target set.</Empty>
        )}
        {limit ? <LimitBar limit={limit} /> : null}
        {urine ? (
          <Text style={[styles.urineText, urine.state === 'low' && { color: colors.rose700 }]}>
            Urine {urine.average != null ? `averaging ${urine.average} mL/h` : 'not averaged yet'} against at least {urine.min} mL/h
            {urine.state === 'pending' ? ' (judged after 4 h on the ward)' : ''}
          </Text>
        ) : null}
        {day.is_current ? (
          <Button
            label={plan?.intake_limit_ml ? 'Change the fluid restriction' : 'Order a fluid restriction'}
            kind="secondary"
            small
            onPress={onRestrict}
            style={{ marginTop: 10, alignSelf: 'flex-start' }}
          />
        ) : null}
      </Section>

      {io.by_type.length ? (
        <Section title="BY TYPE">
          <View style={styles.typeWrap}>
            {io.by_type.map((t) => (
              <View key={`${t.direction}-${t.type}`} style={[styles.typeChip, t.direction === 'output' && styles.typeChipOut]}>
                <Text style={styles.typeLabel}>{t.label}</Text>
                <Text style={styles.typeValue}>{formatMl(t.volume)}</Text>
              </View>
            ))}
          </View>
        </Section>
      ) : null}

      <Section title="ENTRIES" right={<Text style={styles.meta}>Newest first</Text>}>
        {io.entries.length === 0 ? (
          <Empty>Nothing charted for this day.</Empty>
        ) : (
          io.entries.map((e) => (
            <View key={e.id} style={styles.entry}>
              <Text style={[styles.entryTime, e.voided && styles.struck]}>{e.time_label}</Text>
              <View style={{ flex: 1, paddingHorizontal: 10 }}>
                <Text style={[styles.entryType, e.voided && styles.struck]} numberOfLines={2}>
                  {e.type_label}
                  {e.description ? ` · ${e.description}` : ''}
                </Text>
                <View style={styles.entryMetaRow}>
                  {e.auto ? <Badge label={`AUTO · ${e.auto.toUpperCase()}`} toneName="info" /> : null}
                  {e.by ? <Text style={styles.meta}>{e.by}</Text> : null}
                </View>
                {e.voided ? <Text style={styles.voidText}>Struck out: {e.void_reason}</Text> : null}
              </View>
              <Text
                style={[
                  styles.entryVolume,
                  { color: e.direction === 'intake' ? colors.blue700 : colors.amber700 },
                  e.voided && styles.struck,
                ]}
              >
                {e.direction === 'intake' ? '+' : '-'}{Number(e.volume_ml).toLocaleString('en-US')}
              </Text>
            </View>
          ))
        )}
      </Section>

      <Section title="SIGNS OF FLUID OVERLOAD">
        {io.overload ? (
          <>
            <Text style={[styles.planSummary, io.overload.urgent && { color: colors.rose700 }]}>{io.overload.edema}</Text>
            {io.overload.signs.length ? <Text style={styles.planNotes}>{io.overload.signs.join(', ')}</Text> : null}
            {io.overload.weight_kg != null ? (
              <Text style={styles.planNotes}>
                Weight {io.overload.weight_kg.toFixed(1)} kg
                {io.weight?.change != null ? ` (${io.weight.change > 0 ? '+' : ''}${io.weight.change.toFixed(1)} kg since the last)` : ''}
              </Text>
            ) : null}
            <Text style={styles.meta}>
              Checked {io.overload.time_label}{io.overload.by ? ` by ${io.overload.by}` : ''}
            </Text>
          </>
        ) : (
          <Empty>No overload check recorded this stay.</Empty>
        )}
      </Section>

      {io.days.length ? (
        <Section title="RECENT DAYS" right={io.stay_balance != null ? <Text style={styles.meta}>Stay {signedMl(io.stay_balance)}</Text> : null}>
          <View style={styles.dayHead}>
            <Text style={[styles.dayCell, styles.dayCellFirst, styles.dayHeadText]}>DAY</Text>
            <Text style={[styles.dayCell, styles.dayHeadText]}>IN</Text>
            <Text style={[styles.dayCell, styles.dayHeadText]}>OUT</Text>
            <Text style={[styles.dayCell, styles.dayHeadText]}>BALANCE</Text>
          </View>
          {io.days.map((d) => (
            <View key={d.key} style={styles.dayLine}>
              <Text style={[styles.dayCell, styles.dayCellFirst]} numberOfLines={1}>{d.label}</Text>
              <Text style={[styles.dayCell, d.over && { color: colors.rose700, fontWeight: '800' }]}>
                {Number(d.intake).toLocaleString('en-US')}
              </Text>
              <Text style={styles.dayCell}>{Number(d.output).toLocaleString('en-US')}</Text>
              <Text style={styles.dayCell}>{signedMl(d.balance).replace(' mL', '')}</Text>
            </View>
          ))}
        </Section>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  dayRow: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: 8,
    marginBottom: 12,
  },
  dayBtn: {
    width: 40,
    height: 40,
    borderRadius: 12,
    backgroundColor: colors.blue50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dayBtnOff: { opacity: 0.3 },
  dayBtnText: { fontSize: 22, fontWeight: '800', color: colors.blue700, marginTop: -2 },
  dayEyebrow: { fontSize: 9, fontWeight: '800', letterSpacing: 1.6, color: colors.mutedSoft },
  dayLabel: { fontSize: 14, fontWeight: '800', color: colors.slate900, marginTop: 2 },
  totalsRow: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  total: {
    flex: 1,
    backgroundColor: colors.card,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingVertical: 10,
    paddingHorizontal: 8,
  },
  totalLabel: { fontSize: 9, fontWeight: '800', letterSpacing: 1.4, color: colors.mutedSoft },
  totalValue: { marginTop: 4, fontSize: 14, fontWeight: '800', color: colors.slate900 },
  planSummary: { fontSize: 14, fontWeight: '800', color: colors.slate900 },
  planNotes: { marginTop: 4, fontSize: 12, color: colors.slate700, lineHeight: 17 },
  meta: { marginTop: 4, fontSize: 11, color: colors.muted },
  limitWrap: { marginTop: 10 },
  limitTrack: { height: 10, borderRadius: 5, backgroundColor: colors.slate100, overflow: 'hidden' },
  limitFill: { height: 10, borderRadius: 5 },
  limitText: { marginTop: 6, fontSize: 12, fontWeight: '700', color: colors.slate700 },
  urineText: { marginTop: 8, fontSize: 12, fontWeight: '600', color: colors.slate700 },
  typeWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  typeChip: {
    borderRadius: radius.sm,
    backgroundColor: colors.blue50,
    borderWidth: 1,
    borderColor: colors.blue100,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  typeChipOut: { backgroundColor: colors.amber50, borderColor: colors.amber100 },
  typeLabel: { fontSize: 10, fontWeight: '700', color: colors.slate600 },
  typeValue: { fontSize: 13, fontWeight: '800', color: colors.slate900 },
  entry: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingVertical: 8,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
  },
  entryTime: { width: 44, fontSize: 12, fontWeight: '700', color: colors.slate600 },
  entryType: { fontSize: 13, fontWeight: '600', color: colors.slate900 },
  entryMetaRow: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: 6, marginTop: 2 },
  entryVolume: { fontSize: 14, fontWeight: '800', minWidth: 56, textAlign: 'right' },
  struck: { textDecorationLine: 'line-through', color: colors.mutedSoft },
  voidText: { marginTop: 2, fontSize: 11, color: colors.rose600 },
  dayHead: { flexDirection: 'row', paddingBottom: 6, borderBottomWidth: 1, borderBottomColor: colors.slate100 },
  dayHeadText: { fontSize: 9, fontWeight: '800', letterSpacing: 1.2, color: colors.mutedSoft },
  dayLine: { flexDirection: 'row', paddingVertical: 7, borderBottomWidth: 1, borderBottomColor: colors.slate100 },
  dayCell: { flex: 1, fontSize: 12, fontWeight: '600', color: colors.slate800, textAlign: 'right' },
  dayCellFirst: { flex: 1.4, textAlign: 'left' },
});
