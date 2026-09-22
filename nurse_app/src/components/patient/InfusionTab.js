// Infusions, as the ward dashboard's infusion panel shows them: live from the
// Qmed Infusion Engine when it is in use. (Blood transfusion has its own tab.)

import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors } from '../../theme';
import { Banner, Card, EmptyNote, Progress, SectionTitle, Tag, shared } from '../ui';

const STATUS_TONE = {
  alarming: 'critical',
  running: 'good',
  paused: 'warning',
  stopped: 'muted',
  pending: 'info',
  completed: 'muted',
};

export default function InfusionTab({ chart }) {
  const inf = chart.infusions;
  const [showCompleted, setShowCompleted] = useState(false);

  return (
    <View>
      {inf.error ? <Banner toneName="warning" title="Infusion engine offline" text={inf.error} /> : null}

      <SectionTitle
        eyebrow={inf.source === 'engine' ? 'LIVE FROM THE PUMPS' : 'INFUSIONS'}
        title={`Current (${inf.active.length})`}
      />

      {inf.active.length === 0 ? (
        <Card>
          <EmptyNote text="No infusion running for this patient." />
        </Card>
      ) : (
        inf.active.map((item) => <InfusionCard key={item.key} item={item} />)
      )}

      {inf.completed.length > 0 ? (
        <>
          <TouchableOpacity activeOpacity={0.7} onPress={() => setShowCompleted((v) => !v)}>
            <SectionTitle
              eyebrow="THIS STAY"
              title={`Completed infusions (${inf.completed.length})`}
              right={<Text style={styles.toggle}>{showCompleted ? 'Hide' : 'Show'}</Text>}
            />
          </TouchableOpacity>
          {showCompleted ? inf.completed.map((item) => <InfusionCard key={item.key} item={item} compact />) : null}
        </>
      ) : null}
    </View>
  );
}

function InfusionCard({ item, compact }) {
  const alarming = item.status === 'alarming';

  return (
    <Card style={alarming && { borderColor: colors.rose100, backgroundColor: '#fffafb' }}>
      <View style={styles.head}>
        <View style={{ flex: 1 }}>
          <Text style={styles.name}>{item.medication}</Text>
          <Text style={shared.meta}>
            {item.concentration ? `${item.concentration} · ` : ''}
            {item.pump ? (/pump/i.test(item.pump) ? item.pump : `Pump ${item.pump}`) : 'No pump name'}
            {item.live ? ' · live' : ''}
          </Text>
        </View>
        <View style={{ alignItems: 'flex-end', gap: 4 }}>
          <Tag label={item.status_label.toUpperCase()} toneName={STATUS_TONE[item.status] ?? 'default'} solid={alarming} />
          {item.is_warning && !alarming ? <Tag label="WARNING" toneName="warning" /> : null}
        </View>
      </View>

      {item.alarm_message ? (
        <Text style={styles.alarm}>
          {item.alarm_priority ? `${item.alarm_priority}: ` : ''}
          {item.alarm_message}
        </Text>
      ) : null}

      {!compact && item.progress_percent != null ? (
        <View style={{ marginTop: 10 }}>
          <Progress percent={item.progress_percent} toneName={alarming ? 'critical' : 'good'} />
        </View>
      ) : null}

      <View style={styles.grid}>
        <Cell label="RATE" value={item.flow_rate != null ? `${item.flow_rate} mL/h` : '—'} />
        <Cell
          label="INFUSED"
          value={
            item.infused_volume != null
              ? `${item.infused_volume}${item.total_volume ? ` / ${item.total_volume}` : ''} mL`
              : '—'
          }
        />
        {compact ? (
          <Cell label="ENDED" value={item.completed_label ?? '—'} />
        ) : (
          <Cell label="LEFT" value={item.remaining_volume != null ? `${item.remaining_volume} mL` : '—'} />
        )}
      </View>
      {!compact ? (
        <View style={styles.grid}>
          <Cell label="TIME LEFT" value={item.remaining_time ?? '—'} />
          <Cell label="ENDS" value={item.ends_label ?? '—'} />
          <Cell label="UPDATED" value={item.updated_label ?? '—'} />
        </View>
      ) : null}
      {item.dose_rate ? <Text style={[shared.meta, { marginTop: 6 }]}>Dose rate {item.dose_rate}</Text> : null}
    </Card>
  );
}

function Cell({ label, value }) {
  return (
    <View style={{ flex: 1 }}>
      <Text style={styles.cellLabel}>{label}</Text>
      <Text style={styles.cellValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  head: { flexDirection: 'row', alignItems: 'flex-start', gap: 8 },
  name: { color: colors.slate900, fontSize: 15, fontWeight: '800' },
  alarm: { marginTop: 8, color: colors.rose700, fontSize: 12, fontWeight: '700' },
  grid: { flexDirection: 'row', gap: 8, marginTop: 10 },
  cellLabel: { color: colors.muted, fontSize: 9, fontWeight: '800', letterSpacing: 1.2 },
  cellValue: { marginTop: 2, color: colors.slate900, fontSize: 12, fontWeight: '700' },
  toggle: { color: colors.cyan700, fontSize: 12, fontWeight: '800' },
});
