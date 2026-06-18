import React, { useMemo, useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { colors, radius, shadow } from '../../theme';
import Avatar from '../../components/Avatar';
import Pill from '../../components/Pill';

const FILTERS = [
  { id: 'all', label: 'All' },
  { id: 'on', label: 'On shift now' },
  { id: 'doctor', label: 'Doctors' },
  { id: 'nurse', label: 'Nurses' },
];

function roleGroup(role = '') {
  const r = role.toLowerCase();
  if (r.includes('doctor') || r.includes('physician') || r.includes('specialist')) return 'doctor';
  if (r.includes('nurse')) return 'nurse';
  return 'other';
}

export default function CareTeamTab({ careTeam }) {
  const [filter, setFilter] = useState('all');
  const [openId, setOpenId] = useState(null);

  const filtered = useMemo(() => {
    return careTeam.filter((c) => {
      if (filter === 'all') return true;
      if (filter === 'on') return c.on_shift;
      return roleGroup(c.role) === filter;
    });
  }, [careTeam, filter]);

  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <Text style={styles.headerEyebrow}>YOUR CARE TEAM</Text>
        <Text style={styles.headerTitle}>Who is looking after you</Text>
        <Text style={styles.headerHint}>
          Tap a person to see what they do and when they will visit you next.
        </Text>
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={styles.filterRow}
      >
        {FILTERS.map((f) => {
          const active = filter === f.id;
          return (
            <TouchableOpacity
              key={f.id}
              onPress={() => setFilter(f.id)}
              style={[styles.filter, active && styles.filterActive]}
              activeOpacity={0.85}
            >
              <Text style={[styles.filterText, active && styles.filterTextActive]}>{f.label}</Text>
            </TouchableOpacity>
          );
        })}
      </ScrollView>

      <View style={{ gap: 12 }}>
        {filtered.map((c) => {
          const open = openId === c.id;
          return (
            <TouchableOpacity
              key={c.id}
              onPress={() => setOpenId(open ? null : c.id)}
              activeOpacity={0.9}
              style={styles.card}
            >
              <View style={styles.cardRow}>
                <Avatar initials={c.photo_initials} color={c.color} size={56} online={c.on_shift} />
                <View style={{ flex: 1, marginLeft: 12 }}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <Text style={styles.name} numberOfLines={1}>
                      {c.name}
                    </Text>
                    {c.on_shift ? (
                      <Pill label="ON SHIFT" bg={colors.emerald100} color={colors.emerald700} />
                    ) : (
                      <Pill label="OFF" bg={colors.slate100} color={colors.slate500} />
                    )}
                  </View>
                  <Text style={styles.role} numberOfLines={1}>
                    {c.role} · {c.specialty}
                  </Text>
                  <View style={styles.nextRow}>
                    <Text style={styles.nextLabel}>NEXT</Text>
                    <Text style={styles.nextValue}>{c.next_visit}</Text>
                  </View>
                </View>
                <Text style={styles.chevron}>{open ? '▴' : '▾'}</Text>
              </View>

              {open ? (
                <View style={styles.expand}>
                  <Text style={styles.bio}>{c.bio}</Text>
                  <View style={styles.actionsRow}>
                    <View style={[styles.tag, { backgroundColor: colors.brand50 }]}>
                      <Text style={[styles.tagText, { color: colors.brand700 }]}>
                        Speak with this person via nurse call
                      </Text>
                    </View>
                  </View>
                </View>
              ) : null}
            </TouchableOpacity>
          );
        })}
      </View>

      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 14, paddingBottom: 120 },
  header: {
    marginBottom: 14,
  },
  headerEyebrow: {
    color: colors.muted,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  headerTitle: {
    marginTop: 2,
    fontSize: 22,
    fontWeight: '800',
    color: colors.slate900,
  },
  headerHint: {
    marginTop: 4,
    color: colors.muted,
    fontSize: 12,
  },

  filterRow: {
    gap: 8,
    paddingVertical: 4,
    marginBottom: 12,
  },
  filter: {
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: colors.slate100,
  },
  filterActive: {
    backgroundColor: colors.slate900,
  },
  filterText: {
    color: colors.slate600,
    fontSize: 12,
    fontWeight: '700',
  },
  filterTextActive: {
    color: '#fff',
  },

  card: {
    backgroundColor: '#fff',
    borderRadius: radius.xl,
    padding: 14,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    ...shadow.sm,
  },
  cardRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  name: {
    fontSize: 15,
    fontWeight: '800',
    color: colors.slate900,
  },
  role: {
    fontSize: 12,
    color: colors.muted,
    marginTop: 2,
  },
  nextRow: {
    marginTop: 8,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  nextLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
    color: colors.brand600,
  },
  nextValue: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.slate800,
  },
  chevron: {
    color: colors.mutedSoft,
    fontSize: 18,
    fontWeight: '800',
    paddingLeft: 8,
  },
  expand: {
    marginTop: 12,
    paddingTop: 12,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
  },
  bio: {
    color: colors.slate700,
    fontSize: 13,
    lineHeight: 19,
  },
  actionsRow: {
    marginTop: 10,
    flexDirection: 'row',
    gap: 8,
  },
  tag: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: radius.pill,
  },
  tagText: {
    fontSize: 11,
    fontWeight: '800',
  },
});
