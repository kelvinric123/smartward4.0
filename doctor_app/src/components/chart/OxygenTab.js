// Oxygen tab: what the patient is on now (changed on the ward, or recorded
// with the vital signs), the SpO2 target and the latest SpO2 against it, how
// the oxygen has progressed this admission, and every setting. Read-only, as
// the ward changes the oxygen at the bedside; the consultant asks for a change
// with an order.

import React, { useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, radius } from '../../theme';
import { Section, Badge, Chip, Button, Banner, Empty } from './parts';

// Device colours, cool to warm as the support rises (as on the ward dashboard)
export const DEVICE_COLORS = {
  room_air: '#9ca3af',
  nasal_cannula: '#38bdf8',
  simple_mask: '#22d3ee',
  venturi_mask: '#2dd4bf',
  non_rebreather: '#f59e0b',
  high_flow_mask: '#fb923c',
  hfnc: '#f97316',
  cpap: '#f43f5e',
  bipap: '#e11d48',
  tracheostomy: '#a78bfa',
  ventilator: '#dc2626',
  other: '#64748b',
};

export function deviceColor(device) {
  return DEVICE_COLORS[device] ?? DEVICE_COLORS.other;
}

const FLOW_COLOR = '#0369a1';
const FIO2_COLOR = '#047857';
const SPO2_COLOR = colors.blue600;
const SPO2_STATE_COLORS = { below: colors.rose600, above: colors.amber500 };

const HOUR = 3600000;
const RANGES = [
  { key: '24h', label: '24h', hours: 24 },
  { key: '72h', label: '72h', hours: 72 },
  { key: '7d', label: '7 days', hours: 168 },
  { key: 'all', label: 'All', hours: null },
];

// Times arrive as the hospital's wall clock in "UTC" milliseconds, so the
// chart reads ward time whatever the phone's own timezone
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const pad = (n) => String(n).padStart(2, '0');
const clock = (ms) => {
  const d = new Date(ms);
  return `${pad(d.getUTCHours())}:${pad(d.getUTCMinutes())}`;
};
const day = (ms) => {
  const d = new Date(ms);
  return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]}`;
};

// The axes stay the same width on both charts, so their times line up
const PAD_L = 34;
const PAD_R = 34;

/** What to put in the order composer for an oxygen change. */
export function oxygenOrderText(oxygen) {
  const target = oxygen?.target?.label ?? '94–98%';
  if (oxygen?.current?.on_oxygen) {
    return `Oxygen: wean as tolerated, keep SpO₂ ${target}.`;
  }
  return `Oxygen: start if SpO₂ falls below ${oxygen?.target?.min ?? 94}%, target ${target}.`;
}

function timeWindow(chart, rangeKey) {
  const hours = RANGES.find((r) => r.key === rangeKey)?.hours ?? null;
  const times = chart.steps.map((s) => s.t).concat(chart.spo2.map((p) => p.t));
  const last = Math.max(chart.now, ...times);
  const first = hours ? last - hours * HOUR : Math.min(...times);
  // The window ends just past now
  const padding = Math.max((last - first) * 0.02, 10 * 60000);
  const min = hours ? first : first - padding;
  const max = last + padding;
  return { min, max, span: max - min };
}

/** Whole-hour ticks, as few as fit the width. */
function timeTicks(w, plotWidth) {
  const maxTicks = Math.max(2, Math.floor(plotWidth / 62));
  const step = [1, 2, 3, 6, 12, 24, 48, 96].map((h) => h * HOUR).find((s) => w.span / s <= maxTicks) ?? 168 * HOUR;
  const ticks = [];
  for (let t = Math.ceil(w.min / step) * step; t <= w.max; t += step) {
    let label = clock(t) === '00:00' ? day(t) : clock(t);
    if (w.span > 30 * HOUR) label = `${day(t)}\n${clock(t)}`;
    ticks.push({ t, label });
  }
  return ticks;
}

// One straight line drawn as a thin rotated View (the app has no SVG)
function LineSegment({ x1, y1, x2, y2, color, width = 2 }) {
  const dx = x2 - x1;
  const dy = y2 - y1;
  const length = Math.sqrt(dx * dx + dy * dy);
  if (length === 0) return null;
  const angle = (Math.atan2(dy, dx) * 180) / Math.PI;
  return (
    <View
      style={{
        position: 'absolute',
        left: (x1 + x2) / 2 - length / 2,
        top: (y1 + y2) / 2 - width / 2,
        width: length,
        height: width,
        backgroundColor: color,
        borderRadius: width / 2,
        transform: [{ rotate: `${angle}deg` }],
      }}
    />
  );
}

/** A plot area with the time axis under it; children draw in plot coordinates. */
function Plot({ width, height, padTop, w, yTicks, yTicksRight, children, header }) {
  const plotW = width - PAD_L - PAD_R;
  const plotH = height - padTop - 30;
  const ticks = timeTicks(w, plotW);
  const x = (t) => ((t - w.min) / (w.max - w.min)) * plotW;

  return (
    <View style={{ width, height }}>
      {header ? header({ x, plotW }) : null}
      {yTicks.map((tick) => (
        <Text key={`l-${tick.label}`} style={[styles.axisLabel, { left: 0, width: PAD_L - 5, textAlign: 'right', top: padTop + tick.y - 6 }]}>
          {tick.label}
        </Text>
      ))}
      {(yTicksRight ?? []).map((tick) => (
        <Text key={`r-${tick.label}`} style={[styles.axisLabel, { right: 0, width: PAD_R - 5, textAlign: 'left', top: padTop + tick.y - 6 }]}>
          {tick.label}
        </Text>
      ))}
      <View style={[styles.plot, { left: PAD_L, top: padTop, width: plotW, height: plotH }]}>
        {yTicks.map((tick) => (
          <View key={`g-${tick.label}`} style={[styles.grid, { top: tick.y }]} />
        ))}
        {children({ x, plotW, plotH })}
      </View>
      {ticks.map((tick) => (
        <Text
          key={`t-${tick.t}`}
          style={[styles.axisLabel, styles.timeLabel, { top: padTop + plotH + 4, left: PAD_L + x(tick.t) - 30 }]}
        >
          {tick.label}
        </Text>
      ))}
    </View>
  );
}

function Spo2Chart({ width, w, chart, segments }) {
  const points = chart.spo2.filter((p) => p.t >= w.min && p.t <= w.max);
  const lows = chart.spo2.map((p) => p.y).concat(segments.filter((s) => s.target).map((s) => s.target[0]));
  const bottom = Math.max(50, Math.floor(Math.min(90, ...lows) - 2));
  const height = 150;
  const plotH = height - 8 - 30;
  const y = (v) => plotH - ((Math.min(100, Math.max(bottom, v)) - bottom) / (100 - bottom)) * plotH;
  const step = 100 - bottom <= 12 ? 4 : 100 - bottom <= 24 ? 6 : 10;
  const yTicks = [];
  for (let v = 100; v >= bottom; v -= step) yTicks.push({ label: String(v), y: y(v) });

  return (
    <Plot width={width} height={height} padTop={8} w={w} yTicks={yTicks}>
      {({ x, plotW }) => (
        <>
          {/* The target in force, shaded where it applied */}
          {segments.map((seg, i) => {
            if (!seg.target) return null;
            const left = Math.max(0, x(seg.t));
            const right = Math.min(plotW, x(seg.end));
            if (right - left < 1) return null;
            const top = y(seg.target[1]);
            return (
              <View
                key={`band-${i}`}
                style={[styles.targetBand, { left, width: right - left, top, height: Math.max(1, y(seg.target[0]) - top) }]}
              />
            );
          })}
          {points.slice(1).map((p, i) => (
            <LineSegment key={`s-${i}`} x1={x(points[i].t)} y1={y(points[i].y)} x2={x(p.t)} y2={y(p.y)} color={SPO2_COLOR} />
          ))}
          {points.map((p, i) => {
            const size = p.state ? 9 : 7;
            return (
              <View
                key={`p-${i}`}
                style={[
                  styles.dot,
                  { left: x(p.t) - size / 2, top: y(p.y) - size / 2, width: size, height: size, borderRadius: size / 2 },
                  { backgroundColor: SPO2_STATE_COLORS[p.state] ?? SPO2_COLOR },
                ]}
              />
            );
          })}
          {points.length === 0 ? <Text style={styles.plotEmpty}>No SpO₂ recorded in this period</Text> : null}
        </>
      )}
    </Plot>
  );
}

function SupportChart({ width, w, segments, showFio2 }) {
  const height = 160;
  const padTop = 20;
  const plotH = height - padTop - 30;
  const highest = Math.max(6, ...segments.map((s) => s.flow ?? 0));
  const flowTop = highest <= 20 ? Math.ceil(highest / 2) * 2 : Math.ceil(highest / 10) * 10;
  const yFlow = (v) => plotH - (v / flowTop) * plotH;
  const yFio2 = (v) => plotH - ((v - 20) / 80) * plotH;
  const yTicks = [0, flowTop / 2, flowTop].map((v) => ({ label: String(v), y: yFlow(v) }));
  const yTicksRight = showFio2 ? [20, 60, 100].map((v) => ({ label: `${v}%`, y: yFio2(v) })) : [];
  const visible = segments.filter((seg) => seg.end >= w.min && seg.t <= w.max);

  return (
    <Plot
      width={width}
      height={height}
      padTop={padTop}
      w={w}
      yTicks={yTicks}
      yTicksRight={yTicksRight}
      header={({ x, plotW }) => visible.map((seg, i) => {
        // The device's name in the strip above the plot, where there is room
        const left = Math.max(0, x(seg.t));
        const right = Math.min(plotW, x(seg.end));
        if (right - left < 1) return null;
        return (
          <React.Fragment key={`strip-${i}`}>
            <View style={[styles.strip, { left: PAD_L + left, width: right - left, top: padTop - 3, backgroundColor: deviceColor(seg.device) }]} />
            {right - left >= seg.abbr.length * 6 + 6 ? (
              <Text style={[styles.stripLabel, { left: PAD_L + left + 2, top: padTop - 16, color: deviceColor(seg.device) }]}>
                {seg.abbr}
              </Text>
            ) : null}
          </React.Fragment>
        );
      })}
    >
      {({ x, plotW }) => (
        <>
          {visible.map((seg, i) => {
            const left = Math.max(0, x(seg.t));
            const right = Math.min(plotW, x(seg.end));
            if (right - left < 1) return null;
            return (
              <View
                key={`dev-${i}`}
                style={[styles.deviceBand, { left, width: right - left, backgroundColor: `${deviceColor(seg.device)}24` }]}
              />
            );
          })}
          {showFio2 ? <Holds segments={segments} valueKey="fio2" x={x} yOf={yFio2} color={FIO2_COLOR} thickness={2} /> : null}
          <Holds segments={segments} valueKey="flow" x={x} yOf={yFlow} color={FLOW_COLOR} thickness={3} />
          {visible.length === 0 ? <Text style={styles.plotEmpty}>No oxygen recorded in this period</Text> : null}
        </>
      )}
    </Plot>
  );
}

/** One setting's value as flat lines over each time it held, joined by steps. */
function Holds({ segments, valueKey, x, yOf, color, thickness }) {
  return segments.map((seg, i) => {
    const value = seg[valueKey];
    if (value == null) return null;
    const left = x(seg.t);
    const right = x(seg.end);
    const top = yOf(value);
    const next = segments[i + 1];
    const nextValue = next ? next[valueKey] : null;
    return (
      <React.Fragment key={`${valueKey}-${i}`}>
        <View style={{ position: 'absolute', left, width: Math.max(1, right - left), top: top - thickness / 2, height: thickness, backgroundColor: color }} />
        <View style={[styles.stepDot, { left: left - 3.5, top: top - 3.5, backgroundColor: color }]} />
        {nextValue != null ? (
          <View
            style={{
              position: 'absolute',
              left: right - thickness / 2,
              width: thickness,
              top: Math.min(top, yOf(nextValue)),
              height: Math.max(thickness, Math.abs(yOf(nextValue) - top)),
              backgroundColor: color,
            }}
          />
        ) : null}
      </React.Fragment>
    );
  });
}

function Progression({ chart }) {
  const [range, setRange] = useState(chart.range === '72h' ? '72h' : 'all');
  const [width, setWidth] = useState(0);
  const segments = chart.steps.map((step, i) => ({
    ...step,
    end: i + 1 < chart.steps.length ? chart.steps[i + 1].t : chart.now,
  }));
  const w = timeWindow(chart, range);
  const showFio2 = segments.some((s) => s.fio2 != null && s.fio2 > 21);
  const devices = [];
  segments
    .filter((seg) => seg.end >= w.min && seg.t <= w.max)
    .forEach((seg) => {
      if (!devices.some((d) => d.device === seg.device)) devices.push(seg);
    });

  return (
    <Section title="PROGRESSION THIS ADMISSION">
      <View style={styles.chips}>
        {RANGES.map((r) => (
          <Chip key={r.key} label={r.label} selected={range === r.key} onPress={() => setRange(r.key)} />
        ))}
      </View>
      <View onLayout={(e) => setWidth(Math.floor(e.nativeEvent.layout.width))}>
        {width > 0 ? (
          <>
            <Text style={styles.chartTitle}>SpO₂ <Text style={styles.chartHint}>· green band: the target</Text></Text>
            <Spo2Chart width={width} w={w} chart={chart} segments={segments} />
            <Text style={[styles.chartTitle, { marginTop: 10 }]}>Oxygen given <Text style={styles.chartHint}>· shaded by device</Text></Text>
            <SupportChart width={width} w={w} segments={segments} showFio2={showFio2} />
          </>
        ) : null}
      </View>
      <View style={styles.legend}>
        <View style={styles.legendItem}>
          <View style={[styles.legendLine, { backgroundColor: FLOW_COLOR }]} />
          <Text style={styles.legendText}>Flow L/min</Text>
        </View>
        {showFio2 ? (
          <View style={styles.legendItem}>
            <View style={[styles.legendLine, { backgroundColor: FIO2_COLOR }]} />
            <Text style={styles.legendText}>FiO₂ %</Text>
          </View>
        ) : null}
        {devices.map((seg) => (
          <View key={seg.device} style={styles.legendItem}>
            <View style={[styles.legendSwatch, { backgroundColor: deviceColor(seg.device) }]} />
            <Text style={styles.legendText}>{seg.abbr}</Text>
          </View>
        ))}
      </View>
      <Text style={styles.footnote}>
        Room air counts as 0 L/min at 21%. SpO₂ points turn red below the target, and amber above it while on oxygen.
      </Text>
    </Section>
  );
}

function Tile({ label, value, sub, toneName, valueColor }) {
  return (
    <View style={[styles.tile, toneName === 'critical' && styles.tileCritical, toneName === 'warning' && styles.tileWarning]}>
      <Text style={styles.tileLabel}>{label}</Text>
      <Text style={[styles.tileValue, valueColor && { color: valueColor }]} numberOfLines={1}>{value}</Text>
      {sub ? <Text style={styles.tileSub} numberOfLines={2}>{sub}</Text> : null}
    </View>
  );
}

function HistoryRow({ entry }) {
  return (
    <View style={[styles.historyRow, entry.current && styles.historyCurrent]}>
      <View style={[styles.historyDot, { backgroundColor: entry.voided ? colors.slate300 : deviceColor(entry.device) }]} />
      <View style={{ flex: 1 }}>
        <View style={styles.historyTop}>
          <Text style={[styles.historyLabel, entry.voided && styles.struck]}>
            {entry.label}
            {entry.settings ? <Text style={styles.historySettings}>  {entry.settings}</Text> : null}
          </Text>
          {entry.current ? <Badge label="NOW" toneName="info" solid /> : null}
        </View>
        <Text style={styles.historyMeta}>
          {entry.time_label}
          {entry.duration_label ? `  ·  ${entry.duration_label}` : ''}
          {entry.target_label ? `  ·  target ${entry.target_label}` : ''}
        </Text>
        <Text style={styles.historyMeta}>
          {entry.source === 'therapy' ? 'Set on the ward' : 'Recorded with the vital signs'}
          {entry.by ? ` by ${entry.by}` : ''}
          {entry.spo2 != null ? `  ·  SpO₂ ${entry.spo2}%` : ''}
        </Text>
        {entry.notes ? <Text style={styles.historyNotes}>{entry.notes}</Text> : null}
        {entry.voided ? <Text style={styles.voided}>Struck out: {entry.void_reason}</Text> : null}
      </View>
    </View>
  );
}

export default function OxygenTab({ oxygen, onOrder }) {
  if (!oxygen) {
    return (
      <Section>
        <Empty>Oxygen therapy is not available from this server yet.</Empty>
      </Section>
    );
  }

  const current = oxygen.current;
  const latest = oxygen.latest_spo2;
  const hasChart = oxygen.chart && (oxygen.chart.steps.length > 0 || oxygen.chart.spo2.length > 0);

  return (
    <View>
      {oxygen.alert === 'critical' && latest ? (
        <Banner
          level="critical"
          title="SpO₂ below the target"
          detail={`${latest.value}% at ${latest.time_label}${latest.on ? ` on ${latest.on}` : ''}, against ${oxygen.target?.label ?? 'the target'}.`}
        />
      ) : null}
      {oxygen.alert === 'warning' && latest ? (
        <Banner
          level="warning"
          title="SpO₂ above the target on oxygen"
          detail={`${latest.value}% at ${latest.time_label}${latest.on ? ` on ${latest.on}` : ''}, against ${oxygen.target?.label ?? 'the target'}.`}
        />
      ) : null}

      <Section
        title="OXYGEN NOW"
        right={current ? (
          <Badge label={current.on_oxygen ? 'ON O₂' : 'ROOM AIR'} toneName={current.on_oxygen ? 'info' : 'muted'} solid={current.on_oxygen} />
        ) : null}
      >
        {current ? (
          <>
            <View style={styles.nowRow}>
              <View style={[styles.nowDot, { backgroundColor: deviceColor(current.device) }]} />
              <Text style={styles.nowLabel}>{current.label}</Text>
            </View>
            <Text style={[styles.nowSettings, !current.on_oxygen && { color: colors.slate600 }]}>
              {current.settings ?? 'Settings not recorded'}
            </Text>
            <Text style={styles.meta}>
              Since {current.since_label} ({current.duration_label})  ·  {current.source === 'therapy' ? 'set on the ward' : 'from the vital signs'}
              {current.by ? ` by ${current.by}` : ''}
            </Text>
            {current.notes ? <Text style={styles.notes}>{current.notes}</Text> : null}
          </>
        ) : (
          <Empty>No oxygen recorded this admission. The ward records it on the Oxygen Therapy tab or with the vital signs.</Empty>
        )}
      </Section>

      <View style={styles.tiles}>
        <Tile label="SpO₂ TARGET" value={oxygen.target?.label ?? 'Not set'} sub={oxygen.target?.note} />
        <Tile
          label="LATEST SpO₂"
          value={latest ? `${latest.value}%` : '--'}
          valueColor={latest?.state === 'below' ? colors.rose700 : undefined}
          toneName={oxygen.alert}
          sub={latest
            ? `${latest.time_label}${latest.on ? ` on ${latest.on}` : ''}${latest.stale ? ' · before the last change' : ''}`
            : 'From the vital signs'}
        />
        <Tile
          label="ON OXYGEN"
          value={oxygen.on_oxygen_duration_label ?? (current ? 'Off' : '--')}
          sub={oxygen.on_oxygen_since_label ? `Since ${oxygen.on_oxygen_since_label}` : current ? `Room air since ${current.since_label}` : null}
        />
      </View>

      <Button label="Order an oxygen change" kind="secondary" onPress={() => onOrder?.(oxygenOrderText(oxygen))} style={{ marginBottom: 6 }} />
      <Text style={styles.orderHint}>The ward changes the oxygen at the bedside; your order goes to the nurse on duty.</Text>

      {hasChart ? <Progression chart={oxygen.chart} /> : null}

      <Section title="CHANGES THIS ADMISSION">
        {oxygen.history.length ? (
          oxygen.history.map((entry) => <HistoryRow key={entry.key} entry={entry} />)
        ) : (
          <Empty>Nothing recorded yet. Changes made on the ward and oxygen recorded with the vital signs are listed here.</Empty>
        )}
      </Section>
    </View>
  );
}

const styles = StyleSheet.create({
  nowRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  nowDot: { width: 12, height: 12, borderRadius: 6 },
  nowLabel: { flex: 1, fontSize: 16, fontWeight: '800', color: colors.slate900 },
  nowSettings: { marginTop: 4, fontSize: 22, fontWeight: '800', color: FLOW_COLOR },
  meta: { marginTop: 6, fontSize: 12, color: colors.muted, lineHeight: 17 },
  notes: { marginTop: 6, fontSize: 12, color: colors.slate700, fontStyle: 'italic' },
  tiles: { flexDirection: 'row', gap: 8, marginBottom: 12 },
  tile: {
    flex: 1,
    backgroundColor: colors.card,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: 10,
  },
  tileCritical: { backgroundColor: colors.rose50, borderColor: colors.rose100 },
  tileWarning: { backgroundColor: colors.amber50, borderColor: colors.amber100 },
  tileLabel: { fontSize: 9, fontWeight: '800', letterSpacing: 1, color: colors.muted },
  tileValue: { marginTop: 4, fontSize: 18, fontWeight: '800', color: colors.slate900 },
  tileSub: { marginTop: 2, fontSize: 10, color: colors.muted, lineHeight: 14 },
  orderHint: { fontSize: 11, color: colors.muted, textAlign: 'center', marginBottom: 12 },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 6, marginBottom: 10 },
  chartTitle: { fontSize: 12, fontWeight: '800', color: colors.slate700, marginBottom: 4 },
  chartHint: { fontWeight: '600', color: colors.mutedSoft },
  plot: {
    position: 'absolute',
    overflow: 'hidden',
    backgroundColor: '#f8fbff',
    borderLeftWidth: 1,
    borderBottomWidth: 1,
    borderColor: 'rgba(15,23,42,0.2)',
  },
  grid: { position: 'absolute', left: 0, right: 0, height: 1, backgroundColor: 'rgba(15,23,42,0.06)' },
  axisLabel: { position: 'absolute', fontSize: 9, fontWeight: '700', color: colors.muted },
  timeLabel: { width: 60, textAlign: 'center', lineHeight: 11 },
  targetBand: {
    position: 'absolute',
    backgroundColor: 'rgba(16,185,129,0.14)',
    borderTopWidth: 1,
    borderBottomWidth: 1,
    borderColor: 'rgba(5,150,105,0.45)',
  },
  dot: { position: 'absolute', borderWidth: 1, borderColor: '#fff' },
  deviceBand: { position: 'absolute', top: 0, bottom: 0 },
  strip: { position: 'absolute', height: 3 },
  stripLabel: { position: 'absolute', fontSize: 9, fontWeight: '800' },
  stepDot: { position: 'absolute', width: 7, height: 7, borderRadius: 3.5, borderWidth: 1, borderColor: '#fff' },
  plotEmpty: { position: 'absolute', top: '40%', left: 0, right: 0, textAlign: 'center', fontSize: 12, color: colors.muted },
  legend: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, marginTop: 10 },
  legendItem: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  legendLine: { width: 14, height: 3, borderRadius: 2 },
  legendSwatch: { width: 10, height: 10, borderRadius: 3 },
  legendText: { fontSize: 11, fontWeight: '700', color: colors.slate600 },
  footnote: { marginTop: 8, fontSize: 11, color: colors.muted, lineHeight: 16 },
  historyRow: {
    flexDirection: 'row',
    gap: 10,
    paddingVertical: 10,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
  },
  historyCurrent: { backgroundColor: colors.blue50, marginHorizontal: -14, paddingHorizontal: 14 },
  historyDot: { width: 10, height: 10, borderRadius: 5, marginTop: 4 },
  historyTop: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 8 },
  historyLabel: { flex: 1, fontSize: 14, fontWeight: '800', color: colors.slate900 },
  historySettings: { fontWeight: '700', color: colors.slate600 },
  historyMeta: { marginTop: 2, fontSize: 12, color: colors.muted },
  historyNotes: { marginTop: 4, fontSize: 12, color: colors.slate700, fontStyle: 'italic' },
  struck: { textDecorationLine: 'line-through', color: colors.mutedSoft },
  voided: { marginTop: 4, fontSize: 12, fontWeight: '700', color: colors.rose700 },
});
