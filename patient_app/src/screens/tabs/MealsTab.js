import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { colors, radius, shadow } from '../../theme';
import SectionCard from '../../components/SectionCard';
import Pill from '../../components/Pill';
import { api } from '../../data/mockData';

function MealItem({ item, selected, onPick }) {
  const blocked = !item.allowed;
  return (
    <TouchableOpacity
      disabled={blocked}
      activeOpacity={0.85}
      onPress={() => onPick(item.id)}
      style={[
        styles.meal,
        selected && styles.mealSelected,
        blocked && styles.mealBlocked,
      ]}
    >
      <View style={[styles.thumb, { backgroundColor: item.image_color }]}>
        <Text style={styles.thumbEmoji}>{item.emoji}</Text>
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.mealName, blocked && styles.mealNameBlocked]}>
          {item.name}
        </Text>
        <View style={styles.mealMetaRow}>
          <Pill label={`${item.kcal} kcal`} bg={colors.slate100} color={colors.slate700} />
          {item.tags.map((t) => (
            <Pill
              key={t}
              label={t}
              bg={blocked ? colors.rose50 : colors.brand50}
              color={blocked ? colors.rose700 : colors.brand700}
            />
          ))}
        </View>
        {blocked ? (
          <Text style={styles.blockedReason}>🚫 {item.blocked_reason}</Text>
        ) : null}
      </View>
      <View
        style={[
          styles.radio,
          selected && styles.radioActive,
          blocked && { borderColor: colors.slate200 },
        ]}
      >
        {selected ? <View style={styles.radioDot} /> : null}
      </View>
    </TouchableOpacity>
  );
}

export default function MealsTab({ dietProfile, mealMenu, onToast }) {
  // Local selection state — keyed by category id.
  const init = {};
  mealMenu.categories.forEach((cat) => {
    const sel = cat.items.find((i) => i.selected);
    init[cat.id] = sel?.id ?? null;
  });
  const [selected, setSelected] = useState(init);

  const pick = (catId, itemId) => {
    setSelected((s) => ({ ...s, [catId]: itemId }));
    api.selectMeal(itemId);
    onToast?.('Added to your meal order');
  };

  const fluidPct = Math.min(
    100,
    Math.round((dietProfile.fluid_consumed_ml / dietProfile.fluid_target_ml) * 100)
  );

  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <Text style={styles.headerEyebrow}>MEALS</Text>
        <Text style={styles.headerTitle}>{mealMenu.next_meal} — {mealMenu.next_meal_time}</Text>
        <Text style={styles.headerHint}>{mealMenu.date_label}</Text>
      </View>

      <SectionCard eyebrow="YOUR PLAN" title="Diet & restrictions">
        <View style={styles.profileRow}>
          <View style={styles.profileBox}>
            <Text style={styles.profileLabel}>DIET</Text>
            <Text style={styles.profileValue}>{dietProfile.diet_type}</Text>
          </View>
        </View>
        <View style={[styles.profileRow, { marginTop: 8 }]}>
          <View style={{ flex: 1 }}>
            <Text style={styles.profileLabel}>RESTRICTIONS</Text>
            <View style={{ flexDirection: 'row', gap: 6, flexWrap: 'wrap', marginTop: 6 }}>
              {dietProfile.restrictions.map((r) => (
                <Pill key={r} label={r} bg={colors.rose50} color={colors.rose700} />
              ))}
            </View>
          </View>
        </View>

        <View style={styles.fluidWrap}>
          <View style={styles.fluidHeader}>
            <Text style={styles.fluidLabel}>FLUID INTAKE TODAY</Text>
            <Text style={styles.fluidValue}>
              {dietProfile.fluid_consumed_ml} / {dietProfile.fluid_target_ml} ml
            </Text>
          </View>
          <View style={styles.fluidTrack}>
            <View style={[styles.fluidFill, { width: `${fluidPct}%` }]} />
          </View>
        </View>
      </SectionCard>

      <View style={styles.banner}>
        <Text style={styles.bannerEmoji}>✨</Text>
        <Text style={styles.bannerText}>
          We hide foods that conflict with your diet or allergies — only safe options are tappable.
        </Text>
      </View>

      {mealMenu.categories.map((cat) => (
        <SectionCard
          key={cat.id}
          eyebrow={cat.label.toUpperCase()}
          title="Choose one"
          style={{ marginTop: 14 }}
        >
          <View style={{ gap: 10 }}>
            {cat.items.map((it) => (
              <MealItem
                key={it.id}
                item={it}
                selected={selected[cat.id] === it.id}
                onPick={(id) => pick(cat.id, id)}
              />
            ))}
          </View>
        </SectionCard>
      ))}

      <View style={styles.confirmBar}>
        <View style={{ flex: 1 }}>
          <Text style={styles.confirmEyebrow}>YOUR ORDER</Text>
          <Text style={styles.confirmCount}>
            {Object.values(selected).filter(Boolean).length} item(s) selected
          </Text>
        </View>
        <TouchableOpacity
          style={styles.confirmBtn}
          activeOpacity={0.9}
          onPress={() => onToast?.('Meal order sent to kitchen ✓')}
        >
          <Text style={styles.confirmBtnText}>Confirm order</Text>
        </TouchableOpacity>
      </View>

      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 14, paddingBottom: 140 },

  header: { marginBottom: 14 },
  headerEyebrow: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 2.2 },
  headerTitle: { marginTop: 2, fontSize: 22, fontWeight: '800', color: colors.slate900 },
  headerHint: { marginTop: 4, color: colors.muted, fontSize: 12 },

  profileRow: { flexDirection: 'row', gap: 12 },
  profileBox: { flex: 1 },
  profileLabel: {
    fontSize: 10, fontWeight: '800', letterSpacing: 1.4, color: colors.muted,
  },
  profileValue: { marginTop: 4, fontSize: 13, fontWeight: '700', color: colors.slate900 },

  fluidWrap: { marginTop: 14 },
  fluidHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 6,
  },
  fluidLabel: { color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 1.4 },
  fluidValue: { color: colors.slate900, fontSize: 12, fontWeight: '800' },
  fluidTrack: {
    height: 10,
    backgroundColor: colors.slate100,
    borderRadius: 999,
    overflow: 'hidden',
  },
  fluidFill: {
    height: '100%',
    backgroundColor: colors.accent500,
  },

  banner: {
    marginTop: 14,
    flexDirection: 'row',
    gap: 10,
    alignItems: 'center',
    backgroundColor: colors.violet50,
    padding: 12,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.violet100,
  },
  bannerEmoji: { fontSize: 18 },
  bannerText: { flex: 1, color: colors.violet700, fontSize: 12, lineHeight: 17, fontWeight: '600' },

  meal: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 10,
    borderRadius: radius.lg,
    backgroundColor: '#fff',
    borderWidth: 1,
    borderColor: colors.slate200,
  },
  mealSelected: {
    borderColor: colors.brand500,
    backgroundColor: colors.brand50,
  },
  mealBlocked: {
    opacity: 0.65,
    backgroundColor: colors.slate50,
    borderColor: colors.slate200,
  },
  thumb: {
    width: 48,
    height: 48,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  thumbEmoji: { fontSize: 22 },
  mealName: { fontSize: 13, fontWeight: '800', color: colors.slate900 },
  mealNameBlocked: { color: colors.slate600 },
  mealMetaRow: {
    flexDirection: 'row',
    gap: 4,
    marginTop: 6,
    flexWrap: 'wrap',
  },
  blockedReason: {
    marginTop: 6,
    color: colors.rose700,
    fontSize: 11,
    fontWeight: '700',
  },
  radio: {
    width: 22,
    height: 22,
    borderRadius: 999,
    borderWidth: 2,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
  },
  radioActive: {
    borderColor: colors.brand600,
  },
  radioDot: {
    width: 10,
    height: 10,
    borderRadius: 999,
    backgroundColor: colors.brand600,
  },

  confirmBar: {
    marginTop: 16,
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.slate900,
    padding: 14,
    borderRadius: radius.xl,
    ...shadow.md,
  },
  confirmEyebrow: { color: '#cbd5e1', fontSize: 10, fontWeight: '800', letterSpacing: 1.6 },
  confirmCount: { marginTop: 2, color: '#fff', fontSize: 14, fontWeight: '800' },
  confirmBtn: {
    backgroundColor: colors.emerald500,
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: radius.pill,
  },
  confirmBtnText: { color: '#fff', fontWeight: '800' },
});
