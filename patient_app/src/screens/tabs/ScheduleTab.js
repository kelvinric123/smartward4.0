import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { colors, radius, shadow } from '../../theme';
import SectionCard from '../../components/SectionCard';
import Pill from '../../components/Pill';

const TYPE_META = {
  doctor: { color: colors.brand600, bg: colors.brand50, emoji: '🩺' },
  therapy: { color: colors.violet600, bg: colors.violet50, emoji: '🤸' },
  med: { color: colors.rose600, bg: colors.rose50, emoji: '💊' },
  meal: { color: colors.amber600, bg: colors.amber50, emoji: '🍽️' },
  check: { color: colors.accent600, bg: colors.accent50, emoji: '📋' },
};

function ScheduleRow({ item }) {
  const meta = TYPE_META[item.type] ?? TYPE_META.check;
  const done = item.status === 'done';
  const next = item.is_next;
  return (
    <View style={styles.row}>
      <View style={styles.timeColumn}>
        <Text style={[styles.time, next && { color: colors.brand700 }]}>{item.time}</Text>
        {next ? <Text style={styles.nextDot}>NOW</Text> : null}
      </View>
      <View style={styles.line}>
        <View
          style={[
            styles.lineDot,
            done && { backgroundColor: colors.emerald500, borderColor: colors.emerald500 },
            next && { backgroundColor: colors.brand500, borderColor: colors.brand500 },
          ]}
        />
        <View style={styles.lineBar} />
      </View>
      <View
        style={[
          styles.event,
          { backgroundColor: meta.bg },
          done && styles.eventDone,
          next && styles.eventNext,
        ]}
      >
        <View style={styles.eventHeader}>
          <Text style={[styles.eventEmoji]}>{meta.emoji}</Text>
          <Text style={[styles.eventTitle, done && styles.eventTitleDone]} numberOfLines={1}>
            {item.title}
          </Text>
          {done ? <Pill label="DONE" bg={colors.emerald500} color="#fff" /> : null}
        </View>
        <Text style={[styles.eventDetail, done && styles.eventDetailDone]} numberOfLines={2}>
          {item.detail}
        </Text>
      </View>
    </View>
  );
}

function DischargeStep({ step, index, last }) {
  return (
    <View style={styles.discharge}>
      <View style={styles.dischargeCol}>
        <View
          style={[
            styles.dischargeDot,
            step.done
              ? { backgroundColor: colors.emerald500, borderColor: colors.emerald500 }
              : { backgroundColor: '#fff', borderColor: colors.slate300 },
          ]}
        >
          {step.done ? <Text style={styles.dischargeTick}>✓</Text> : <Text style={styles.dischargeIdx}>{index + 1}</Text>}
        </View>
        {last ? null : (
          <View
            style={[
              styles.dischargeBar,
              step.done && { backgroundColor: colors.emerald500 },
            ]}
          />
        )}
      </View>
      <View style={{ flex: 1, paddingBottom: 14 }}>
        <Text style={[styles.dischargeLabel, step.done && { color: colors.muted }]}>
          {step.label}
        </Text>
        <View style={styles.dischargeMeta}>
          <Text style={styles.dischargeOwner}>{step.owner}</Text>
          <Text style={styles.dischargeEta}>
            {step.done ? `Cleared ${step.done_at}` : `Expected ${step.eta}`}
          </Text>
        </View>
      </View>
    </View>
  );
}

export default function ScheduleTab({ todaySchedule, dischargeChecklist, visitor }) {
  const [tab, setTab] = useState('today');
  const done = dischargeChecklist.steps.filter((s) => s.done).length;
  const total = dischargeChecklist.steps.length;
  const pct = Math.round((done / total) * 100);

  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <Text style={styles.headerEyebrow}>SCHEDULE</Text>
        <Text style={styles.headerTitle}>Your day at a glance</Text>
      </View>

      <View style={styles.switcher}>
        {[
          { id: 'today', label: 'Today' },
          { id: 'discharge', label: 'Discharge' },
          { id: 'visitors', label: 'Visitors' },
        ].map((t) => {
          const active = tab === t.id;
          return (
            <TouchableOpacity
              key={t.id}
              onPress={() => setTab(t.id)}
              activeOpacity={0.85}
              style={[styles.switchBtn, active && styles.switchBtnActive]}
            >
              <Text style={[styles.switchText, active && styles.switchTextActive]}>
                {t.label}
              </Text>
            </TouchableOpacity>
          );
        })}
      </View>

      {tab === 'today' ? (
        <SectionCard eyebrow="TIMELINE" title="Today">
          <View>
            {todaySchedule.map((s) => (
              <ScheduleRow key={s.id} item={s} />
            ))}
          </View>
        </SectionCard>
      ) : null}

      {tab === 'discharge' ? (
        <>
          <SectionCard eyebrow="GOING HOME" title="Discharge plan">
            <View style={styles.dischargeHeader}>
              <View style={{ flex: 1 }}>
                <Text style={styles.dischargeDate}>{dischargeChecklist.expected_date_label}</Text>
                <Text style={styles.dischargeTime}>{dischargeChecklist.expected_time_label}</Text>
              </View>
              <View style={styles.progressBubble}>
                <Text style={styles.progressBubbleText}>
                  {done}/{total}
                </Text>
              </View>
            </View>
            <View style={styles.progressTrack}>
              <View style={[styles.progressFill, { width: `${pct}%` }]} />
            </View>
            <Text style={styles.dischargeHint}>
              Every step below must be cleared before you can be discharged.
            </Text>
          </SectionCard>

          <SectionCard eyebrow="CHECKLIST" title="Clinical sign-offs" style={{ marginTop: 14 }}>
            <View>
              {dischargeChecklist.steps.map((s, i) => (
                <DischargeStep
                  key={s.id}
                  step={s}
                  index={i}
                  last={i === dischargeChecklist.steps.length - 1}
                />
              ))}
            </View>
          </SectionCard>
        </>
      ) : null}

      {tab === 'visitors' ? (
        <SectionCard eyebrow="VISITORS" title="Visiting hours & guests">
          <View style={styles.visitorBox}>
            <Text style={styles.visitorLabel}>VISITING HOURS</Text>
            <Text style={styles.visitorValue}>{visitor.visiting_hours}</Text>
          </View>
          <Text style={[styles.visitorLabel, { marginTop: 14 }]}>EXPECTED TODAY</Text>
          <View style={{ marginTop: 8, gap: 10 }}>
            {visitor.expected_visitors.map((v) => (
              <View key={v.id} style={styles.visitorRow}>
                <View style={styles.visitorAvatar}>
                  <Text style={styles.visitorAvatarText}>{v.name[0]}</Text>
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.visitorName}>{v.name}</Text>
                  <Text style={styles.visitorEta}>{v.eta}</Text>
                </View>
                <Pill label="EXPECTED" bg={colors.brand50} color={colors.brand700} />
              </View>
            ))}
          </View>
        </SectionCard>
      ) : null}

      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 14, paddingBottom: 120 },

  header: { marginBottom: 14 },
  headerEyebrow: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 2.2 },
  headerTitle: { marginTop: 2, fontSize: 22, fontWeight: '800', color: colors.slate900 },

  switcher: {
    flexDirection: 'row',
    backgroundColor: '#fff',
    padding: 6,
    borderRadius: radius.pill,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    marginBottom: 14,
    ...shadow.sm,
  },
  switchBtn: {
    flex: 1,
    paddingVertical: 10,
    alignItems: 'center',
    borderRadius: radius.pill,
  },
  switchBtnActive: {
    backgroundColor: colors.slate900,
  },
  switchText: { color: colors.slate600, fontWeight: '800', fontSize: 12 },
  switchTextActive: { color: '#fff' },

  // Today timeline
  row: { flexDirection: 'row', gap: 8 },
  timeColumn: {
    width: 56,
    paddingTop: 6,
    alignItems: 'flex-end',
  },
  time: { fontWeight: '800', color: colors.slate800, fontSize: 12 },
  nextDot: {
    marginTop: 2,
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.4,
    color: colors.brand600,
  },
  line: {
    width: 18,
    alignItems: 'center',
  },
  lineDot: {
    width: 12,
    height: 12,
    borderRadius: 999,
    borderWidth: 2,
    borderColor: colors.slate300,
    backgroundColor: '#fff',
    marginTop: 8,
    zIndex: 2,
  },
  lineBar: {
    flex: 1,
    width: 2,
    backgroundColor: colors.slate200,
    marginTop: -2,
  },
  event: {
    flex: 1,
    padding: 12,
    borderRadius: radius.lg,
    marginBottom: 10,
  },
  eventDone: { opacity: 0.7 },
  eventNext: {
    borderWidth: 1.5,
    borderColor: colors.brand500,
  },
  eventHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  eventEmoji: { fontSize: 16 },
  eventTitle: {
    flex: 1,
    color: colors.slate900,
    fontWeight: '800',
    fontSize: 13,
  },
  eventTitleDone: {
    color: colors.slate600,
    textDecorationLine: 'line-through',
  },
  eventDetail: {
    marginTop: 4,
    color: colors.slate700,
    fontSize: 11,
    lineHeight: 16,
  },
  eventDetailDone: { color: colors.muted },

  // Discharge
  dischargeHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
  },
  dischargeDate: { color: colors.slate900, fontSize: 16, fontWeight: '800' },
  dischargeTime: { color: colors.muted, fontSize: 12, marginTop: 2 },
  progressBubble: {
    backgroundColor: colors.slate900,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
  },
  progressBubbleText: { color: '#fff', fontWeight: '800', fontSize: 12 },
  progressTrack: {
    height: 8,
    borderRadius: 999,
    backgroundColor: colors.slate100,
    overflow: 'hidden',
  },
  progressFill: { height: '100%', backgroundColor: colors.emerald500 },
  dischargeHint: {
    marginTop: 10,
    color: colors.muted,
    fontSize: 12,
  },

  discharge: {
    flexDirection: 'row',
    gap: 10,
  },
  dischargeCol: {
    alignItems: 'center',
    width: 28,
  },
  dischargeDot: {
    width: 28,
    height: 28,
    borderRadius: 999,
    borderWidth: 2,
    alignItems: 'center',
    justifyContent: 'center',
  },
  dischargeTick: { color: '#fff', fontWeight: '800', fontSize: 14 },
  dischargeIdx: { color: colors.slate700, fontWeight: '800', fontSize: 12 },
  dischargeBar: {
    flex: 1,
    width: 2,
    backgroundColor: colors.slate200,
    marginVertical: 2,
  },
  dischargeLabel: {
    color: colors.slate900,
    fontSize: 13,
    fontWeight: '800',
  },
  dischargeMeta: {
    marginTop: 2,
    flexDirection: 'row',
    gap: 10,
    alignItems: 'baseline',
  },
  dischargeOwner: {
    color: colors.brand700,
    fontSize: 11,
    fontWeight: '800',
  },
  dischargeEta: {
    color: colors.muted,
    fontSize: 11,
  },

  // Visitors
  visitorBox: {
    backgroundColor: colors.brand50,
    padding: 12,
    borderRadius: radius.lg,
  },
  visitorLabel: {
    color: colors.muted,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
  },
  visitorValue: {
    marginTop: 4,
    color: colors.brand700,
    fontSize: 14,
    fontWeight: '800',
  },
  visitorRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 10,
    borderRadius: radius.lg,
    backgroundColor: colors.slate50,
  },
  visitorAvatar: {
    width: 36,
    height: 36,
    borderRadius: 999,
    backgroundColor: colors.violet500,
    alignItems: 'center',
    justifyContent: 'center',
  },
  visitorAvatarText: { color: '#fff', fontWeight: '800' },
  visitorName: { fontSize: 13, fontWeight: '800', color: colors.slate900 },
  visitorEta: { fontSize: 11, color: colors.muted, marginTop: 2 },
});
