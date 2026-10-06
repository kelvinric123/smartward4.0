import React, { useMemo, useState } from 'react';
import {
  Modal,
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  ScrollView,
} from 'react-native';
import { colors, radius } from '../theme';

// Medical reference ranges and styling, used to render the "normal" band
// behind the line and to color the y-axis labels.
const METRICS = {
  pulse_rate: {
    label: 'Pulse Rate',
    short: 'Pulse',
    unit: 'bpm',
    color: '#e11d48',
    band: [60, 100],
    domainPad: 15,
    yTicks: 5,
    decimals: 0,
  },
  systolic_bp: {
    label: 'Blood Pressure',
    short: 'BP',
    unit: 'mmHg',
    color: '#1d4ed8',
    diaColor: '#60a5fa',
    band: [90, 140],
    diaBand: [60, 90],
    pair: true,
    domainPad: 15,
    yTicks: 5,
    decimals: 0,
  },
  spo2: {
    label: 'SpO2 (Oxygen Saturation)',
    short: 'SpO2',
    unit: '%',
    color: '#0ea5e9',
    band: [95, 100],
    domainPad: 3,
    yMin: 80,
    yMax: 100,
    yTicks: 5,
    decimals: 0,
  },
  respiratory_rate: {
    label: 'Respiratory Rate',
    short: 'Resp',
    unit: '/min',
    color: '#d97706',
    band: [12, 20],
    domainPad: 4,
    yTicks: 5,
    decimals: 0,
  },
  temperature: {
    label: 'Temperature',
    short: 'Temp',
    unit: '°C',
    color: '#a855f7',
    band: [36.0, 37.5],
    domainPad: 0.5,
    yTicks: 5,
    decimals: 1,
  },
  ews: {
    label: 'Early Warning Score',
    short: 'EWS',
    unit: '',
    color: '#dc2626',
    band: [0, 2],
    yMin: 0,
    yMax: 10,
    yTicks: 6,
    decimals: 0,
  },
};

const METRIC_ORDER = ['pulse_rate', 'systolic_bp', 'spo2', 'respiratory_rate', 'temperature', 'ews'];

const CHART_W = 320;
const CHART_H = 200;
const PAD_L = 38;
const PAD_R = 14;
const PAD_T = 12;
const PAD_B = 28;
const PLOT_W = CHART_W - PAD_L - PAD_R;
const PLOT_H = CHART_H - PAD_T - PAD_B;

function fmt(v, d) {
  if (v == null) return '--';
  return d ? Number(v).toFixed(d) : String(v);
}

function buildScale(values, meta) {
  const cleaned = values.filter((v) => v != null).map(Number);
  let min = meta.yMin;
  let max = meta.yMax;
  if (min == null || max == null) {
    const raw = [...cleaned, ...(meta.band ?? []), ...(meta.diaBand ?? [])];
    let mn = Math.min(...raw);
    let mx = Math.max(...raw);
    if (mn === mx) { mn -= 1; mx += 1; }
    const pad = meta.domainPad ?? (mx - mn) * 0.15;
    min = mn - pad;
    max = mx + pad;
  }
  return { min, max };
}

// Convert a (point, value) pair to plot coordinates.
function project(i, n, value, scale) {
  const x = PAD_L + (n === 1 ? PLOT_W / 2 : (i / (n - 1)) * PLOT_W);
  const t = (value - scale.min) / (scale.max - scale.min || 1);
  const y = PAD_T + PLOT_H - Math.max(0, Math.min(1, t)) * PLOT_H;
  return { x, y };
}

// Single line segment drawn as a rotated thin View, anchored from its centre.
function LineSegment({ x1, y1, x2, y2, color, width = 2 }) {
  const dx = x2 - x1;
  const dy = y2 - y1;
  const length = Math.sqrt(dx * dx + dy * dy);
  if (length === 0) return null;
  const angle = (Math.atan2(dy, dx) * 180) / Math.PI;
  const cx = (x1 + x2) / 2;
  const cy = (y1 + y2) / 2;
  return (
    <View
      style={{
        position: 'absolute',
        left: cx - length / 2,
        top: cy - width / 2,
        width: length,
        height: width,
        backgroundColor: color,
        borderRadius: width / 2,
        transform: [{ rotate: `${angle}deg` }],
      }}
    />
  );
}

function Series({ points, color }) {
  return (
    <>
      {points.slice(0, -1).map((p, i) => {
        const q = points[i + 1];
        return <LineSegment key={`s-${i}`} x1={p.x} y1={p.y} x2={q.x} y2={q.y} color={color} />;
      })}
      {points.map((p, i) => (
        <View
          key={`d-${i}`}
          style={{
            position: 'absolute',
            left: p.x - 5,
            top: p.y - 5,
            width: 10,
            height: 10,
            borderRadius: 5,
            backgroundColor: '#fff',
            borderWidth: 2,
            borderColor: color,
          }}
        />
      ))}
    </>
  );
}

function Chart({ metricKey, history }) {
  const meta = METRICS[metricKey];
  const n = history.length;

  const primaryValues = history.map((h) =>
    metricKey === 'systolic_bp' ? h.systolic_bp : h[metricKey]
  );
  const secondaryValues = meta.pair ? history.map((h) => h.diastolic_bp) : null;

  const scaleSource = meta.pair
    ? [...primaryValues, ...secondaryValues]
    : primaryValues;
  const scale = useMemo(() => buildScale(scaleSource, meta), [metricKey, history]);

  // Y-axis ticks
  const ticks = [];
  const tickCount = meta.yTicks ?? 5;
  for (let i = 0; i < tickCount; i++) {
    const t = i / (tickCount - 1);
    const value = scale.min + (scale.max - scale.min) * (1 - t);
    const y = PAD_T + t * PLOT_H;
    ticks.push({ value, y });
  }

  // Normal-range band
  const bandTop = project(0, 1, meta.band?.[1] ?? scale.max, scale).y;
  const bandBottom = project(0, 1, meta.band?.[0] ?? scale.min, scale).y;
  const diaBandTop = meta.diaBand ? project(0, 1, meta.diaBand[1], scale).y : null;
  const diaBandBottom = meta.diaBand ? project(0, 1, meta.diaBand[0], scale).y : null;

  const primaryPoints = primaryValues.map((v, i) => ({ ...project(i, n, v, scale), value: v }));
  const secondaryPoints = secondaryValues
    ? secondaryValues.map((v, i) => ({ ...project(i, n, v, scale), value: v }))
    : null;

  return (
    <View style={styles.chartWrap}>
      <View style={{ width: CHART_W, height: CHART_H, backgroundColor: '#f0f7ff', borderRadius: 12, overflow: 'hidden' }}>
        {/* Normal-range band (primary) */}
        {meta.band ? (
          <View
            style={{
              position: 'absolute',
              left: PAD_L,
              right: PAD_R,
              top: bandTop,
              height: Math.max(0, bandBottom - bandTop),
              backgroundColor: 'rgba(16, 185, 129, 0.12)',
              borderTopWidth: 1,
              borderBottomWidth: 1,
              borderColor: 'rgba(16, 185, 129, 0.35)',
              borderStyle: 'dashed',
            }}
          />
        ) : null}
        {/* Normal-range band (diastolic) */}
        {meta.diaBand ? (
          <View
            style={{
              position: 'absolute',
              left: PAD_L,
              right: PAD_R,
              top: diaBandTop,
              height: Math.max(0, diaBandBottom - diaBandTop),
              backgroundColor: 'rgba(96, 165, 250, 0.10)',
              borderTopWidth: 1,
              borderBottomWidth: 1,
              borderColor: 'rgba(96, 165, 250, 0.30)',
              borderStyle: 'dashed',
            }}
          />
        ) : null}

        {/* Horizontal grid lines + Y-axis labels */}
        {ticks.map((t, i) => (
          <View key={`t-${i}`}>
            <View
              style={{
                position: 'absolute',
                left: PAD_L,
                right: PAD_R,
                top: t.y,
                height: 1,
                backgroundColor: 'rgba(15,23,42,0.06)',
              }}
            />
            <Text
              style={{
                position: 'absolute',
                left: 0,
                top: t.y - 7,
                width: PAD_L - 4,
                textAlign: 'right',
                fontSize: 9,
                color: colors.muted,
                fontWeight: '700',
              }}
            >
              {fmt(t.value, meta.decimals)}
            </Text>
          </View>
        ))}

        {/* Plot border */}
        <View
          style={{
            position: 'absolute',
            left: PAD_L,
            top: PAD_T,
            width: PLOT_W,
            height: PLOT_H,
            borderLeftWidth: 1,
            borderBottomWidth: 1,
            borderColor: 'rgba(15,23,42,0.2)',
          }}
        />

        {/* Secondary series (diastolic) drawn first */}
        {secondaryPoints ? <Series points={secondaryPoints} color={meta.diaColor} /> : null}
        {/* Primary series */}
        <Series points={primaryPoints} color={meta.color} />

        {/* Last-point value bubble */}
        {primaryPoints.length > 0 ? (
          (() => {
            const last = primaryPoints[primaryPoints.length - 1];
            const lastSec = secondaryPoints?.[secondaryPoints.length - 1];
            const txt = meta.pair
              ? `${fmt(last.value, 0)}/${fmt(lastSec?.value, 0)}`
              : fmt(last.value, meta.decimals);
            const w = txt.length * 7 + 18;
            const left = Math.min(CHART_W - PAD_R - w, Math.max(PAD_L, last.x - w / 2));
            const top = Math.max(PAD_T, last.y - 26);
            return (
              <View
                style={{
                  position: 'absolute',
                  left,
                  top,
                  paddingHorizontal: 6,
                  paddingVertical: 2,
                  backgroundColor: meta.color,
                  borderRadius: 6,
                }}
              >
                <Text style={{ color: '#fff', fontSize: 11, fontWeight: '800' }}>
                  {txt}{meta.unit ? ` ${meta.unit}` : ''}
                </Text>
              </View>
            );
          })()
        ) : null}

        {/* X-axis labels */}
        {history.map((h, i) => {
          const { x } = project(i, n, 0, scale);
          const label = (h.recorded_at_label ?? '').split(' ').slice(-1)[0];
          return (
            <Text
              key={`xl-${i}`}
              style={{
                position: 'absolute',
                top: PAD_T + PLOT_H + 6,
                left: x - 22,
                width: 44,
                textAlign: 'center',
                fontSize: 9,
                color: colors.muted,
                fontWeight: '700',
              }}
              numberOfLines={1}
            >
              {label}
            </Text>
          );
        })}
      </View>

      {/* Legend */}
      <View style={styles.legend}>
        <View style={styles.legendItem}>
          <View style={[styles.legendSwatch, { backgroundColor: meta.color }]} />
          <Text style={styles.legendText}>
            {meta.pair ? 'Systolic' : meta.short}
          </Text>
        </View>
        {meta.pair ? (
          <View style={styles.legendItem}>
            <View style={[styles.legendSwatch, { backgroundColor: meta.diaColor }]} />
            <Text style={styles.legendText}>Diastolic</Text>
          </View>
        ) : null}
        {meta.band ? (
          <View style={styles.legendItem}>
            <View
              style={[
                styles.legendSwatch,
                {
                  backgroundColor: 'rgba(16,185,129,0.25)',
                  borderColor: 'rgba(16,185,129,0.6)',
                  borderWidth: 1,
                  borderStyle: 'dashed',
                },
              ]}
            />
            <Text style={styles.legendText}>
              Normal {meta.band[0]}-{meta.band[1]}{meta.unit ? ` ${meta.unit}` : ''}
            </Text>
          </View>
        ) : null}
      </View>
    </View>
  );
}

function StatsRow({ metricKey, history }) {
  const meta = METRICS[metricKey];
  const values = history.map((h) =>
    metricKey === 'systolic_bp' ? h.systolic_bp : h[metricKey]
  ).filter((v) => v != null).map(Number);
  if (values.length === 0) return null;
  const last = values[values.length - 1];
  const prev = values[values.length - 2];
  const min = Math.min(...values);
  const max = Math.max(...values);
  const avg = values.reduce((a, b) => a + b, 0) / values.length;
  const delta = prev != null ? last - prev : null;
  const deltaColor = delta == null
    ? colors.muted
    : Math.abs(delta) < 0.05
      ? colors.muted
      : delta > 0
        ? colors.rose600
        : colors.emerald600;
  const deltaTxt = delta == null
    ? '—'
    : Math.abs(delta) < 0.05
      ? '—'
      : `${delta > 0 ? '▲' : '▼'} ${fmt(Math.abs(delta), meta.decimals)}`;
  return (
    <View style={styles.statsRow}>
      <View style={styles.statBox}>
        <Text style={styles.statLabel}>CURRENT</Text>
        <Text style={[styles.statValue, { color: meta.color }]}>
          {fmt(last, meta.decimals)}
        </Text>
        <Text style={styles.statUnit}>{meta.unit}</Text>
      </View>
      <View style={styles.statBox}>
        <Text style={styles.statLabel}>CHANGE</Text>
        <Text style={[styles.statValue, { color: deltaColor }]}>{deltaTxt}</Text>
        <Text style={styles.statUnit}>vs previous</Text>
      </View>
      <View style={styles.statBox}>
        <Text style={styles.statLabel}>MIN</Text>
        <Text style={styles.statValue}>{fmt(min, meta.decimals)}</Text>
        <Text style={styles.statUnit}>{meta.unit}</Text>
      </View>
      <View style={styles.statBox}>
        <Text style={styles.statLabel}>MAX</Text>
        <Text style={styles.statValue}>{fmt(max, meta.decimals)}</Text>
        <Text style={styles.statUnit}>{meta.unit}</Text>
      </View>
      <View style={styles.statBox}>
        <Text style={styles.statLabel}>AVG</Text>
        <Text style={styles.statValue}>{fmt(avg, meta.decimals === 0 ? 0 : 1)}</Text>
        <Text style={styles.statUnit}>{meta.unit}</Text>
      </View>
    </View>
  );
}

export default function VitalsTrendModal({ visible, onClose, bed, initialMetric }) {
  const [metric, setMetric] = useState(initialMetric ?? 'systolic_bp');

  React.useEffect(() => {
    if (visible && initialMetric) setMetric(initialMetric);
  }, [visible, initialMetric]);

  const history = bed?.vitals_history ?? [];
  const meta = METRICS[metric];

  return (
    <Modal visible={visible} animationType="slide" transparent onRequestClose={onClose}>
      <View style={styles.backdrop}>
        <View style={styles.sheet}>
          <View style={styles.sheetHandle} />
          <View style={styles.sheetHeader}>
            <View style={{ flex: 1 }}>
              <Text style={styles.sheetEyebrow}>VITALS TREND</Text>
              <Text style={styles.sheetTitle} numberOfLines={1}>
                {bed?.patient_name ?? 'Patient'} · Bed {bed?.number}
              </Text>
              <Text style={styles.sheetMeta}>
                {history.length} readings · last {history[history.length - 1]?.recorded_at_label ?? '—'}
              </Text>
            </View>
            <TouchableOpacity onPress={onClose} style={styles.closeBtn} activeOpacity={0.85}>
              <Text style={styles.closeBtnText}>Close</Text>
            </TouchableOpacity>
          </View>

          {history.length === 0 ? (
            <View style={styles.empty}>
              <Text style={styles.emptyTitle}>No vitals history available</Text>
              <Text style={styles.emptyMeta}>
                Trend data will appear here once recorded readings are synced from the bedside.
              </Text>
            </View>
          ) : (
            <>
              <View style={styles.tabBar}>
                <ScrollView
                  horizontal
                  showsHorizontalScrollIndicator={false}
                  contentContainerStyle={styles.tabRow}
                >
                  {METRIC_ORDER.map((k) => {
                    const m = METRICS[k];
                    const active = k === metric;
                    return (
                      <TouchableOpacity
                        key={k}
                        activeOpacity={0.85}
                        onPress={() => setMetric(k)}
                        style={[
                          styles.tab,
                          active && { backgroundColor: m.color, borderColor: m.color },
                        ]}
                      >
                        <Text style={[styles.tabText, active && { color: '#fff' }]}>
                          {m.short}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </ScrollView>
              </View>

              <ScrollView
                contentContainerStyle={styles.scroll}
                showsVerticalScrollIndicator={false}
              >
                <View style={styles.metricHeader}>
                  <View style={[styles.metricDot, { backgroundColor: meta.color }]} />
                  <View>
                    <Text style={styles.metricTitle}>{meta.label}</Text>
                    <Text style={styles.metricSubtitle}>
                      Reference range {meta.band ? `${meta.band[0]}–${meta.band[1]} ${meta.unit}` : 'n/a'}
                    </Text>
                  </View>
                </View>

                <Chart metricKey={metric} history={history} />

                <StatsRow metricKey={metric} history={history} />

                <View style={styles.tableCard}>
                  <Text style={styles.tableTitle}>READINGS</Text>
                  {history.slice().reverse().map((h, i) => (
                    <View key={h.id ?? i} style={styles.tableRow}>
                      <Text style={styles.tableTime} numberOfLines={1}>
                        {h.recorded_at_label}
                      </Text>
                      <Text style={styles.tableCell}>P {h.pulse_rate ?? '--'}</Text>
                      <Text style={styles.tableCell}>
                        BP {h.systolic_bp ?? '--'}/{h.diastolic_bp ?? '--'}
                      </Text>
                      <Text style={styles.tableCell}>SpO2 {h.spo2 ?? '--'}</Text>
                      <Text style={styles.tableCell}>
                        T {h.temperature != null ? Number(h.temperature).toFixed(1) : '--'}
                      </Text>
                      <Text style={[styles.tableCell, styles.tableEws]}>EWS {h.ews ?? '--'}</Text>
                    </View>
                  ))}
                </View>
              </ScrollView>
            </>
          )}
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(2,6,23,0.55)',
    justifyContent: 'flex-end',
  },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    maxHeight: '94%',
    paddingBottom: 16,
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 44,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.slate300,
    marginTop: 8,
  },
  sheetHeader: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingHorizontal: 18,
    paddingTop: 12,
    paddingBottom: 8,
  },
  sheetEyebrow: {
    color: colors.cyan700,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  sheetTitle: {
    marginTop: 2,
    color: colors.slate900,
    fontSize: 18,
    fontWeight: '800',
  },
  sheetMeta: {
    marginTop: 2,
    color: colors.muted,
    fontSize: 11,
  },
  closeBtn: {
    backgroundColor: colors.slate900,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: radius.md,
  },
  closeBtnText: {
    color: '#fff',
    fontSize: 12,
    fontWeight: '800',
  },

  tabBar: {
    height: 56,
    flexGrow: 0,
    flexShrink: 0,
  },
  tabRow: {
    paddingHorizontal: 14,
    paddingVertical: 10,
    gap: 6,
    alignItems: 'center',
  },
  tab: {
    minHeight: 36,
    paddingHorizontal: 14,
    paddingVertical: 8,
    borderRadius: 999,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.slate200,
    justifyContent: 'center',
    alignItems: 'center',
  },
  tabText: {
    fontSize: 12,
    lineHeight: 16,
    fontWeight: '800',
    color: colors.slate700,
    letterSpacing: 0.3,
    includeFontPadding: false,
  },

  scroll: {
    paddingHorizontal: 14,
    paddingBottom: 24,
    gap: 12,
  },

  metricHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingHorizontal: 4,
    marginTop: 4,
  },
  metricDot: {
    width: 14,
    height: 14,
    borderRadius: 7,
  },
  metricTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: colors.slate900,
  },
  metricSubtitle: {
    fontSize: 11,
    color: colors.muted,
    marginTop: 1,
  },

  chartWrap: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    padding: 12,
    alignItems: 'center',
  },
  legend: {
    marginTop: 10,
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 10,
    alignSelf: 'flex-start',
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  legendSwatch: {
    width: 14,
    height: 8,
    borderRadius: 2,
  },
  legendText: {
    fontSize: 11,
    color: colors.slate700,
    fontWeight: '600',
  },

  statsRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  statBox: {
    flex: 1,
    minWidth: 90,
    backgroundColor: colors.card,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    padding: 10,
  },
  statLabel: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 1.4,
    color: colors.muted,
  },
  statValue: {
    marginTop: 4,
    fontSize: 18,
    fontWeight: '800',
    color: colors.slate900,
  },
  statUnit: {
    fontSize: 10,
    color: colors.muted,
    marginTop: 1,
  },

  tableCard: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(15,23,42,0.06)',
    padding: 12,
  },
  tableTitle: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
    color: colors.muted,
    marginBottom: 6,
  },
  tableRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    paddingVertical: 6,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    gap: 6,
  },
  tableTime: {
    width: '100%',
    fontSize: 11,
    fontWeight: '700',
    color: colors.slate700,
  },
  tableCell: {
    fontSize: 11,
    color: colors.slate700,
    backgroundColor: colors.slate100,
    borderRadius: 6,
    paddingHorizontal: 6,
    paddingVertical: 2,
  },
  tableEws: {
    backgroundColor: colors.rose100,
    color: colors.rose700,
    fontWeight: '800',
  },

  empty: {
    padding: 24,
    alignItems: 'center',
  },
  emptyTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.slate900,
  },
  emptyMeta: {
    marginTop: 6,
    fontSize: 12,
    color: colors.muted,
    textAlign: 'center',
  },
});
