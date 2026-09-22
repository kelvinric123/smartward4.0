// Overview: what needs doing for this patient (each line opens its tab),
// the latest vitals, and the patient's details and alerts.

import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { colors, radius } from '../../theme';
import { Card, EmptyNote, Row, Tag, shared, tone } from '../ui';

function ewsTone(ews) {
  if (ews == null) return 'muted';
  if (ews >= 5) return 'critical';
  if (ews >= 3) return 'warning';
  return 'good';
}

/** The "needs attention" lines, most pressing first. */
export function attentionItems(chart) {
  const b = chart.badges;
  const items = [];

  const tf = chart.transfusions;
  (tf?.exceptions ?? [])
    .filter((e) => e.level === 'critical')
    .forEach((e) => items.push({ tab: 'transfusion', toneName: 'critical', title: `${e.title} (unit ${e.unit})`, text: e.detail }));
  (tf?.running ?? []).forEach((unit) =>
    items.push({
      tab: 'transfusion',
      toneName: 'warning',
      title: `Blood unit ${unit.unit_number} running`,
      text: `${unit.product}${unit.end_label ? ` · due to finish ${unit.end_label}` : ''}`,
    })
  );
  (tf?.pending ?? []).forEach((unit) =>
    items.push({
      tab: 'transfusion',
      toneName: 'info',
      title: unit.can_start ? `Unit ${unit.unit_number} ready to start` : `Unit ${unit.unit_number}: bedside checks ${unit.steps_done} of 4`,
    })
  );

  if (b.orders_stat) items.push({ tab: 'orders', toneName: 'critical', title: `${b.orders_stat} STAT ${b.orders_stat === 1 ? 'order' : 'orders'} open` });
  if (b.meds_overdue) items.push({ tab: 'meds', toneName: 'critical', title: `${b.meds_overdue} ${b.meds_overdue === 1 ? 'dose' : 'doses'} overdue` });
  if (b.infusion_alarms) items.push({ tab: 'infusion', toneName: 'critical', title: `${b.infusion_alarms} infusion ${b.infusion_alarms === 1 ? 'alarm' : 'alarms'}` });
  if (b.alerts_pending) items.push({ tab: 'alerts', toneName: 'warning', title: `${b.alerts_pending} unanswered ${b.alerts_pending === 1 ? 'call or alert' : 'calls or alerts'}` });
  (chart.io.day.is_current ? chart.io.status.alerts : []).forEach((alert) =>
    items.push({ tab: 'io', toneName: alert.level === 'critical' ? 'critical' : 'warning', title: alert.title, text: alert.detail })
  );
  const routine = b.orders_open - b.orders_stat;
  if (routine > 0) {
    items.push({
      tab: 'orders',
      toneName: 'info',
      title: `${routine} ${b.orders_stat ? 'more ' : ''}open ${routine === 1 ? 'order' : 'orders'}`,
      text: b.orders_mine ? `${b.orders_mine} of ${b.orders_open} open orders are with you this shift` : null,
    });
  }
  if (b.meds_due_soon) items.push({ tab: 'meds', toneName: 'warning', title: `${b.meds_due_soon} ${b.meds_due_soon === 1 ? 'dose' : 'doses'} due within 30 min` });
  if (b.care_plan_due) {
    items.push({
      tab: 'plan',
      toneName: 'info',
      title: `${b.care_plan_due} care plan ${b.care_plan_due === 1 ? 'diagnosis' : 'diagnoses'} to evaluate this shift`,
      text: 'Met, partly met or not met, in the Nursing plan tab',
    });
  }

  return items;
}

export default function OverviewTab({ chart, goTab }) {
  const p = chart.patient;
  const v = chart.vitals;
  const latest = v.latest;
  const items = attentionItems(chart);

  return (
    <View>
      <Card title="NEEDS ATTENTION">
        {items.length === 0 ? (
          <Text style={styles.allClear}>Nothing waiting. All caught up.</Text>
        ) : (
          items.map((item, i) => {
            const t = tone(item.toneName);
            return (
              <TouchableOpacity key={i} activeOpacity={0.75} onPress={() => goTab(item.tab)} style={[styles.item, { backgroundColor: t.bg, borderColor: t.border }]}>
                <View style={[styles.itemDot, { backgroundColor: t.solid }]} />
                <View style={{ flex: 1 }}>
                  <Text style={[styles.itemTitle, { color: t.text }]}>{item.title}</Text>
                  {item.text ? <Text style={[styles.itemText, { color: t.text }]}>{item.text}</Text> : null}
                </View>
                <Text style={[styles.itemGo, { color: t.text }]}>›</Text>
              </TouchableOpacity>
            );
          })
        )}
      </Card>

      <View style={styles.vitals}>
        <View style={styles.vitalsHead}>
          <Text style={styles.vitalsTitle}>LATEST VITALS</Text>
          {latest?.ews != null ? <Tag label={`EWS ${latest.ews}`} toneName={ewsTone(latest.ews)} solid /> : null}
        </View>
        {latest ? (
          <>
            <View style={styles.vitalGrid}>
              <Vital label="BP" value={latest.bp} />
              <Vital label="PULSE" value={latest.pulse} />
              <Vital label="TEMP" value={latest.temperature ? `${latest.temperature}°C` : null} />
              <Vital label="SPO2" value={latest.spo2 ? `${latest.spo2}%` : null} />
              <Vital label="RESP" value={latest.respiratory_rate} />
              <Vital label="HGT" value={v.hgt ? v.hgt.value : null} />
            </View>
            <Text style={styles.vitalsMeta}>
              {latest.time_label}
              {latest.oxygen ? ` · on ${latest.oxygen}` : ''}
              {latest.by ? ` · ${latest.by}` : ''}
            </Text>
          </>
        ) : (
          <Text style={styles.vitalsMeta}>No vital signs recorded yet.</Text>
        )}
      </View>

      {v.recent.length > 1 ? (
        <Card title="EARLIER READINGS">
          {v.recent.slice(1).map((r) => (
            <View key={r.id} style={styles.readingRow}>
              <Text style={styles.readingTime}>{r.time_label}</Text>
              <Text style={styles.readingValues} numberOfLines={1}>
                {[r.bp && `BP ${r.bp}`, r.pulse && `HR ${r.pulse}`, r.temperature && `T ${r.temperature}`, r.spo2 && `SpO₂ ${r.spo2}%`, r.respiratory_rate && `RR ${r.respiratory_rate}`]
                  .filter(Boolean)
                  .join(' · ')}
              </Text>
              {r.ews != null ? <Tag label={`${r.ews}`} toneName={ewsTone(r.ews)} /> : null}
            </View>
          ))}
        </Card>
      ) : null}

      <Card title="ALERTS ON RECORD">
        <Text style={styles.subLabel}>Allergies</Text>
        {p.allergies.length === 0 ? (
          <EmptyNote text="No known allergies recorded" />
        ) : (
          <View style={[shared.chipWrap, { marginBottom: 6 }]}>
            {p.allergies.map((a, i) => (
              <Tag key={i} label={a.name} toneName={a.resolved ? 'muted' : 'critical'} solid={!a.resolved} />
            ))}
          </View>
        )}
        <Row label="Diet" value={[p.nbm ? 'NIL BY MOUTH' : null, p.diet].filter(Boolean).join(' · ') || 'Regular diet'} valueTone={p.nbm ? 'critical' : undefined} />
        {p.feeding ? <Row label="Feeding" value={p.feeding} /> : null}
        {p.diet_orders ? <Row label="Diet orders" value={p.diet_orders} /> : null}
        <Row label="Fall risk" value={p.fall_risk ?? 'None'} valueTone={p.fall_risk === 'High' || p.fall_risk === 'FR Alert Active' ? 'critical' : undefined} />
        <Row label="Isolation" value={p.isolation ?? 'None'} valueTone={p.isolation ? 'warning' : undefined} />
        <Row label="Level of care" value={p.nursing_level} />
        {p.hgt ? <Row label="HGT monitoring" value={p.hgt} /> : null}
      </Card>

      <Card title="ADMISSION">
        <Row label="Consultant" value={p.consultant} />
        {p.anaesthetist ? <Row label="Anaesthetist" value={p.anaesthetist} /> : null}
        {p.primary_nurse ? <Row label="Primary nurse" value={p.primary_nurse} /> : null}
        <Row label="Admitted" value={p.admitted_label} />
        <Row label="Stay" value={p.stay_label} />
        <Row label="Status" value={p.status_label} valueTone={p.status === 'pending_discharge' ? 'warning' : undefined} />
        {p.expected_discharge_label ? <Row label="Expected discharge" value={p.expected_discharge_label} /> : null}
      </Card>
    </View>
  );
}

function Vital({ label, value }) {
  return (
    <View style={styles.vital}>
      <Text style={styles.vitalLabel}>{label}</Text>
      <Text style={styles.vitalValue}>{value ?? '--'}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  allClear: { color: colors.emerald700, fontSize: 13, fontWeight: '700' },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderWidth: 1,
    borderRadius: radius.md,
    paddingHorizontal: 10,
    paddingVertical: 9,
    marginTop: 6,
  },
  itemDot: { width: 8, height: 8, borderRadius: 4 },
  itemTitle: { fontSize: 13, fontWeight: '800' },
  itemText: { marginTop: 2, fontSize: 11 },
  itemGo: { fontSize: 20, fontWeight: '800' },
  vitals: { backgroundColor: colors.slate950, borderRadius: radius.lg, padding: 12, marginBottom: 12 },
  vitalsHead: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 8 },
  vitalsTitle: { color: colors.cyan100, fontSize: 10, fontWeight: '800', letterSpacing: 2 },
  vitalGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  vital: { width: '31.5%', backgroundColor: 'rgba(255,255,255,0.08)', borderRadius: radius.md, padding: 8 },
  vitalLabel: { color: '#cbd5e1', fontSize: 9, fontWeight: '700', letterSpacing: 1.4 },
  vitalValue: { marginTop: 4, color: '#fff', fontSize: 14, fontWeight: '800' },
  vitalsMeta: { marginTop: 8, color: '#cbd5e1', fontSize: 11 },
  readingRow: { flexDirection: 'row', alignItems: 'center', gap: 8, paddingVertical: 5 },
  readingTime: { width: 78, color: colors.slate500, fontSize: 11, fontWeight: '700' },
  readingValues: { flex: 1, color: colors.slate700, fontSize: 11 },
  subLabel: { color: colors.muted, fontSize: 12, marginBottom: 6 },
});
