import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { colors, radius, shadow } from '../../theme';
import SectionCard from '../../components/SectionCard';
import Pill from '../../components/Pill';
import Avatar from '../../components/Avatar';

function NextEvent({ event }) {
  if (!event) return null;
  return (
    <View style={styles.nextEvent}>
      <View style={styles.nextEventLeft}>
        <Text style={styles.nextEventTime}>{event.time}</Text>
        <Text style={styles.nextEventLabel}>NEXT</Text>
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.nextEventTitle}>{event.title}</Text>
        <Text style={styles.nextEventDetail} numberOfLines={2}>
          {event.detail}
        </Text>
      </View>
    </View>
  );
}

function GoalRow({ goal }) {
  return (
    <View style={styles.goalRow}>
      <View style={[styles.tick, goal.done ? styles.tickDone : styles.tickPending]}>
        {goal.done ? <Text style={styles.tickMark}>✓</Text> : null}
      </View>
      <Text style={[styles.goalText, goal.done && styles.goalTextDone]}>{goal.label}</Text>
    </View>
  );
}

export default function HomeTab({
  patient,
  recoveryOverview,
  todaySchedule,
  careTeam,
  notifications,
  onOpenRequest,
  onOpenCareTeam,
  onOpenSchedule,
}) {
  const nextEvent = todaySchedule.find((s) => s.status === 'upcoming') ?? null;
  const onShift = careTeam.filter((c) => c.on_shift);
  const goalsDone = recoveryOverview.goals_today.filter((g) => g.done).length;
  const goalsTotal = recoveryOverview.goals_today.length;
  const progressPct = Math.round((goalsDone / goalsTotal) * 100);

  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      {/* Hello card */}
      <View style={styles.hello}>
        <Text style={styles.helloEyebrow}>GOOD MORNING</Text>
        <Text style={styles.helloName}>{patient.preferred_name} 👋</Text>
        <Text style={styles.helloMeta}>
          Day {recoveryOverview.day_of_stay} · Expected discharge {recoveryOverview.expected_discharge}
        </Text>
        <NextEvent event={nextEvent} />
      </View>

      {/* Quick actions */}
      <View style={styles.quickRow}>
        <TouchableOpacity style={[styles.quick, { backgroundColor: colors.rose50 }]} onPress={onOpenRequest}>
          <Text style={styles.quickEmoji}>🛎️</Text>
          <Text style={[styles.quickLabel, { color: colors.rose700 }]}>Call nurse</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.quick, { backgroundColor: colors.brand50 }]} onPress={onOpenCareTeam}>
          <Text style={styles.quickEmoji}>👥</Text>
          <Text style={[styles.quickLabel, { color: colors.brand700 }]}>Care team</Text>
        </TouchableOpacity>
        <TouchableOpacity style={[styles.quick, { backgroundColor: colors.emerald50 }]} onPress={onOpenSchedule}>
          <Text style={styles.quickEmoji}>📅</Text>
          <Text style={[styles.quickLabel, { color: colors.emerald700 }]}>Schedule</Text>
        </TouchableOpacity>
      </View>

      {/* Today’s goals */}
      <SectionCard
        eyebrow="TODAY"
        title="Your recovery goals"
        action={
          <View style={styles.progressBubble}>
            <Text style={styles.progressBubbleText}>
              {goalsDone}/{goalsTotal}
            </Text>
          </View>
        }
      >
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, { width: `${progressPct}%` }]} />
        </View>
        <View style={{ marginTop: 12, gap: 8 }}>
          {recoveryOverview.goals_today.map((g) => (
            <GoalRow key={g.id} goal={g} />
          ))}
        </View>
        <View style={styles.note}>
          <Text style={styles.noteText}>{recoveryOverview.progress_note}</Text>
        </View>
      </SectionCard>

      {/* On shift now */}
      <SectionCard eyebrow="WALKING IN" title="Who is on shift" style={{ marginTop: 14 }}>
        <View style={styles.shiftRow}>
          {onShift.slice(0, 4).map((c) => (
            <View key={c.id} style={styles.shiftItem}>
              <Avatar initials={c.photo_initials} color={c.color} size={48} online />
              <Text style={styles.shiftName} numberOfLines={1}>
                {c.name.split(' ').slice(-1)[0]}
              </Text>
              <Text style={styles.shiftRole} numberOfLines={1}>
                {c.role}
              </Text>
            </View>
          ))}
        </View>
        <TouchableOpacity style={styles.linkBtn} onPress={onOpenCareTeam}>
          <Text style={styles.linkBtnText}>See full care team →</Text>
        </TouchableOpacity>
      </SectionCard>

      {/* Notifications */}
      <SectionCard eyebrow="UPDATES" title="Inbox" style={{ marginTop: 14 }}>
        <View style={{ gap: 10 }}>
          {notifications.map((n) => (
            <View key={n.id} style={styles.notifRow}>
              <View
                style={[
                  styles.notifDot,
                  {
                    backgroundColor:
                      n.type === 'success'
                        ? colors.emerald500
                        : n.type === 'message'
                        ? colors.violet500
                        : colors.brand500,
                  },
                ]}
              />
              <View style={{ flex: 1 }}>
                <View style={styles.notifHeader}>
                  <Text style={styles.notifTitle}>{n.title}</Text>
                  <Text style={styles.notifTime}>{n.time_label}</Text>
                </View>
                <Text style={styles.notifBody}>{n.body}</Text>
              </View>
            </View>
          ))}
        </View>
      </SectionCard>

      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 14, paddingBottom: 120 },

  hello: {
    backgroundColor: '#fff',
    borderRadius: radius.xl,
    padding: 18,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    ...shadow.md,
  },
  helloEyebrow: {
    color: colors.muted,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  helloName: {
    marginTop: 4,
    fontSize: 24,
    fontWeight: '800',
    color: colors.slate900,
  },
  helloMeta: {
    marginTop: 2,
    color: colors.muted,
    fontSize: 12,
  },

  nextEvent: {
    marginTop: 14,
    flexDirection: 'row',
    backgroundColor: colors.brand50,
    borderRadius: radius.lg,
    padding: 12,
    alignItems: 'center',
    gap: 14,
  },
  nextEventLeft: {
    alignItems: 'center',
    paddingRight: 12,
    borderRightWidth: 1,
    borderRightColor: 'rgba(30,64,175,0.12)',
  },
  nextEventTime: {
    fontSize: 18,
    fontWeight: '800',
    color: colors.brand700,
  },
  nextEventLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.6,
    color: colors.brand600,
    marginTop: 2,
  },
  nextEventTitle: {
    fontSize: 14,
    fontWeight: '800',
    color: colors.slate900,
  },
  nextEventDetail: {
    marginTop: 2,
    fontSize: 12,
    color: colors.slate600,
  },

  quickRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 14,
  },
  quick: {
    flex: 1,
    paddingVertical: 16,
    borderRadius: radius.lg,
    alignItems: 'center',
    ...shadow.sm,
  },
  quickEmoji: {
    fontSize: 22,
  },
  quickLabel: {
    marginTop: 6,
    fontSize: 12,
    fontWeight: '800',
    letterSpacing: 0.2,
  },

  progressBubble: {
    backgroundColor: colors.slate900,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: radius.pill,
  },
  progressBubbleText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
  },
  progressTrack: {
    height: 8,
    borderRadius: 999,
    backgroundColor: colors.slate100,
    overflow: 'hidden',
  },
  progressFill: {
    height: '100%',
    backgroundColor: colors.emerald500,
  },

  goalRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  tick: {
    width: 22,
    height: 22,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tickDone: {
    backgroundColor: colors.emerald500,
  },
  tickPending: {
    borderWidth: 1.5,
    borderColor: colors.slate300,
    backgroundColor: '#fff',
  },
  tickMark: {
    color: '#fff',
    fontWeight: '800',
    fontSize: 12,
  },
  goalText: {
    flex: 1,
    color: colors.slate800,
    fontSize: 13,
    fontWeight: '600',
  },
  goalTextDone: {
    color: colors.muted,
    textDecorationLine: 'line-through',
  },

  note: {
    marginTop: 14,
    padding: 12,
    backgroundColor: colors.slate50,
    borderRadius: radius.md,
    borderLeftWidth: 3,
    borderLeftColor: colors.brand500,
  },
  noteText: {
    color: colors.slate700,
    fontSize: 12,
    lineHeight: 18,
  },

  shiftRow: {
    flexDirection: 'row',
    gap: 12,
    justifyContent: 'space-between',
  },
  shiftItem: {
    flex: 1,
    alignItems: 'center',
  },
  shiftName: {
    marginTop: 6,
    fontSize: 12,
    fontWeight: '800',
    color: colors.slate900,
  },
  shiftRole: {
    fontSize: 10,
    color: colors.muted,
  },
  linkBtn: {
    marginTop: 12,
    alignSelf: 'flex-end',
  },
  linkBtnText: {
    color: colors.brand700,
    fontWeight: '800',
    fontSize: 12,
  },

  notifRow: {
    flexDirection: 'row',
    gap: 10,
  },
  notifDot: {
    width: 8,
    height: 8,
    borderRadius: 999,
    marginTop: 6,
  },
  notifHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  notifTitle: {
    color: colors.slate900,
    fontWeight: '800',
    fontSize: 13,
    flex: 1,
  },
  notifTime: {
    color: colors.muted,
    fontSize: 11,
    fontWeight: '700',
  },
  notifBody: {
    marginTop: 2,
    color: colors.slate600,
    fontSize: 12,
    lineHeight: 17,
  },
});
