import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Switch } from 'react-native';
import { colors, radius, shadow } from '../../theme';
import SectionCard from '../../components/SectionCard';
import Slider from '../../components/Slider';
import Stepper from '../../components/Stepper';
import { api } from '../../data/mockData';

export default function RoomTab({ roomControls, onToast }) {
  const [scene, setScene] = useState(roomControls.lighting.active_scene);
  const [brightness, setBrightness] = useState(roomControls.lighting.brightness);
  const [temp, setTemp] = useState(roomControls.temperature.target_c);
  const [blinds, setBlinds] = useState(roomControls.blinds.position);
  const [tv, setTv] = useState(roomControls.tv.is_on);
  const [volume, setVolume] = useState(roomControls.tv.volume);
  const [dnd, setDnd] = useState(roomControls.do_not_disturb);

  const pickScene = (id) => {
    setScene(id);
    api.setLightingScene(id);
    onToast?.(`Lighting set to ${id}`);
  };

  const setLight = (v) => {
    setBrightness(v);
    api.setLightingBrightness(v);
  };

  const setTemperature = (v) => {
    setTemp(v);
    api.setTemperature(v);
  };

  const setBlindsPos = (v) => {
    setBlinds(v);
    api.setBlinds(v);
  };

  const toggleTv = (v) => {
    setTv(v);
    api.toggleTv(v);
    onToast?.(v ? 'TV on' : 'TV off');
  };

  const setVol = (v) => {
    setVolume(v);
    api.setTvVolume(v);
  };

  const toggleDnd = (v) => {
    setDnd(v);
    api.toggleDoNotDisturb(v);
    onToast?.(v ? 'Do not disturb on' : 'Do not disturb off');
  };

  return (
    <ScrollView contentContainerStyle={styles.scroll} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <Text style={styles.headerEyebrow}>SMART ROOM</Text>
        <Text style={styles.headerTitle}>Make your room comfortable</Text>
        <Text style={styles.headerHint}>
          Adjust lighting, temperature and blinds without ringing the nurse.
        </Text>
      </View>

      {/* Lighting */}
      <SectionCard eyebrow="LIGHTING" title="Mood & brightness">
        <View style={styles.sceneRow}>
          {roomControls.lighting.scenes.map((s) => {
            const active = scene === s.id;
            return (
              <TouchableOpacity
                key={s.id}
                onPress={() => pickScene(s.id)}
                activeOpacity={0.85}
                style={[styles.scene, active && styles.sceneActive]}
              >
                <Text style={styles.sceneEmoji}>{s.icon}</Text>
                <Text style={[styles.sceneLabel, active && styles.sceneLabelActive]}>
                  {s.label}
                </Text>
              </TouchableOpacity>
            );
          })}
        </View>

        <View style={{ marginTop: 16 }}>
          <View style={styles.sliderHeader}>
            <Text style={styles.sliderLabel}>Brightness</Text>
            <Text style={styles.sliderValue}>{brightness}%</Text>
          </View>
          <Slider value={brightness} onChange={setLight} steps={10} color={colors.amber500} />
        </View>
      </SectionCard>

      {/* Temperature */}
      <SectionCard eyebrow="CLIMATE" title="Room temperature" style={{ marginTop: 14 }}>
        <View style={styles.tempRow}>
          <View>
            <Text style={styles.tempLabel}>TARGET</Text>
            <Text style={styles.tempBig}>
              {temp}
              <Text style={styles.tempUnit}>°C</Text>
            </Text>
            <Text style={styles.tempCurrent}>
              Now {roomControls.temperature.current_c}°C
            </Text>
          </View>
          <Stepper
            value={temp}
            onChange={setTemperature}
            min={roomControls.temperature.min}
            max={roomControls.temperature.max}
            suffix="°C"
          />
        </View>
      </SectionCard>

      {/* Blinds */}
      <SectionCard eyebrow="BLINDS" title="Motorized blinds" style={{ marginTop: 14 }}>
        <View style={styles.blindsRow}>
          <Text style={styles.blindsIcon}>🪟</Text>
          <View style={{ flex: 1 }}>
            <View style={styles.sliderHeader}>
              <Text style={styles.sliderLabel}>
                {blinds === 0 ? 'Closed' : blinds === 100 ? 'Fully open' : `${blinds}% open`}
              </Text>
              <Text style={styles.sliderValue}>{blinds}%</Text>
            </View>
            <Slider value={blinds} onChange={setBlindsPos} steps={10} color={colors.accent600} />
          </View>
        </View>
        <View style={styles.blindsActions}>
          <TouchableOpacity onPress={() => setBlindsPos(0)} style={styles.miniBtn}>
            <Text style={styles.miniBtnText}>Close</Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={() => setBlindsPos(50)} style={styles.miniBtn}>
            <Text style={styles.miniBtnText}>Half</Text>
          </TouchableOpacity>
          <TouchableOpacity onPress={() => setBlindsPos(100)} style={styles.miniBtn}>
            <Text style={styles.miniBtnText}>Open</Text>
          </TouchableOpacity>
        </View>
      </SectionCard>

      {/* Entertainment */}
      <SectionCard eyebrow="ENTERTAINMENT" title="Television" style={{ marginTop: 14 }}>
        <View style={styles.tvRow}>
          <View style={{ flex: 1 }}>
            <Text style={styles.tvLabel}>Television</Text>
            <Text style={styles.tvSub}>Channel guide and casting available when on.</Text>
          </View>
          <Switch
            value={tv}
            onValueChange={toggleTv}
            trackColor={{ true: colors.brand500, false: colors.slate300 }}
          />
        </View>
        {tv ? (
          <View style={{ marginTop: 12 }}>
            <View style={styles.sliderHeader}>
              <Text style={styles.sliderLabel}>Volume</Text>
              <Text style={styles.sliderValue}>{volume}%</Text>
            </View>
            <Slider value={volume} onChange={setVol} steps={10} color={colors.violet500} />
          </View>
        ) : null}
      </SectionCard>

      {/* DND */}
      <SectionCard eyebrow="PRIVACY" title="Do not disturb" style={{ marginTop: 14 }}>
        <View style={styles.tvRow}>
          <View style={{ flex: 1 }}>
            <Text style={styles.tvLabel}>Hold non-urgent visits</Text>
            <Text style={styles.tvSub}>
              Staff will still respond to nurse calls and urgent checks.
            </Text>
          </View>
          <Switch
            value={dnd}
            onValueChange={toggleDnd}
            trackColor={{ true: colors.rose500, false: colors.slate300 }}
          />
        </View>
      </SectionCard>

      <View style={{ height: 24 }} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 14, paddingBottom: 120 },

  header: { marginBottom: 14 },
  headerEyebrow: {
    color: colors.muted, fontSize: 10, fontWeight: '800', letterSpacing: 2.2,
  },
  headerTitle: { marginTop: 2, fontSize: 22, fontWeight: '800', color: colors.slate900 },
  headerHint: { marginTop: 4, color: colors.muted, fontSize: 12 },

  sceneRow: {
    flexDirection: 'row',
    gap: 8,
  },
  scene: {
    flex: 1,
    backgroundColor: colors.slate50,
    paddingVertical: 14,
    borderRadius: radius.lg,
    alignItems: 'center',
    borderWidth: 1,
    borderColor: 'transparent',
  },
  sceneActive: {
    backgroundColor: colors.brand50,
    borderColor: colors.brand200,
  },
  sceneEmoji: { fontSize: 22 },
  sceneLabel: {
    marginTop: 6,
    fontSize: 11,
    fontWeight: '800',
    color: colors.slate700,
  },
  sceneLabelActive: {
    color: colors.brand700,
  },

  sliderHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  sliderLabel: {
    fontSize: 12,
    fontWeight: '700',
    color: colors.slate700,
  },
  sliderValue: {
    fontSize: 12,
    fontWeight: '800',
    color: colors.slate900,
  },

  tempRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  tempLabel: {
    color: colors.muted,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.4,
  },
  tempBig: {
    marginTop: 4,
    fontSize: 40,
    fontWeight: '800',
    color: colors.slate900,
    lineHeight: 44,
  },
  tempUnit: {
    fontSize: 18,
    color: colors.muted,
  },
  tempCurrent: {
    color: colors.muted,
    fontSize: 12,
    marginTop: 2,
  },

  blindsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
  },
  blindsIcon: { fontSize: 30 },
  blindsActions: {
    marginTop: 12,
    flexDirection: 'row',
    gap: 8,
  },
  miniBtn: {
    flex: 1,
    backgroundColor: colors.slate100,
    paddingVertical: 8,
    borderRadius: radius.pill,
    alignItems: 'center',
  },
  miniBtnText: {
    color: colors.slate800,
    fontSize: 12,
    fontWeight: '800',
  },

  tvRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  tvLabel: { fontSize: 14, fontWeight: '800', color: colors.slate900 },
  tvSub: { marginTop: 2, color: colors.muted, fontSize: 12 },
});
