// Sample patient charts for the demo login, in the same shape the server
// sends (DoctorAppPatientChart), and the demo dashboard's bed figures worked
// out from them (demoBedFromChart), as the server works its out from the same
// records. Orders written or cancelled and lab results reviewed in the demo are
// kept in memory, and a fluid restriction updates the demo fluid plan, so the
// whole flow can be tried without a server and the bed cards follow along.

import { demoNow, demoTime, resetDemoClock, stampOf, clockOf } from './demoClock';

const pad = (n) => String(n).padStart(2, '0');

// The sample history, timed back from the demo session's "now" (src/data/demoClock.js)
function at(minutesAgo) {
  return new Date(demoNow() - minutesAgo * 60000);
}

function time(minutesAgo) {
  return clockOf(at(minutesAgo));
}

function stamp(minutesAgo) {
  return stampOf(at(minutesAgo));
}

// What the consultant does in the demo happens at the real time
const timeNow = () => clockOf(new Date());
const stampNow = () => stampOf(new Date());

/** A moment as the hospital's wall clock in "UTC" milliseconds, the way the server sends chart times. */
function wallMs(ms) {
  return ms - new Date(ms).getTimezoneOffset() * 60000;
}

const TYPE_LABELS = {
  intake: { oral: 'Oral', iv: 'IV fluid', tube_feed: 'Tube feed', blood: 'Blood product', iv_med: 'IV medication', other: 'Other intake' },
  output: { urine: 'Urine', drain: 'Drain', vomit: 'Vomit', ng_aspirate: 'NG aspirate', stool: 'Stool', other: 'Other output' },
};

const URGENCY_LABELS = { stat: 'STAT', urgent: 'Urgent', routine: 'Routine' };

function formatMl(ml) {
  return `${Number(ml).toLocaleString('en-US')} mL`;
}

function restrictionSummary(limit, urineMin) {
  const parts = [];
  if (limit != null) parts.push(`Fluid restriction ${Number(limit).toLocaleString('en-US')} mL per day`);
  if (urineMin != null) parts.push(`${parts.length ? 'urine' : 'Urine'} at least ${urineMin} mL/h`);
  return parts.length ? parts.join(', ') : null;
}

function planSummary(limit, urineMin) {
  const parts = [];
  if (limit != null) parts.push(`Intake up to ${Number(limit).toLocaleString('en-US')} mL per day`);
  if (urineMin != null) parts.push(`${parts.length ? 'urine' : 'Urine'} at least ${urineMin} mL/h`);
  return parts.length ? parts.join(', ') : 'No limits';
}

function entry(id, minutesAgo, direction, type, volume, description, extra = {}) {
  return {
    id,
    minutes_ago: minutesAgo,
    time_label: time(minutesAgo),
    direction,
    type,
    type_label: TYPE_LABELS[direction][type],
    description,
    volume_ml: volume,
    auto: null,
    by: 'SN Aisyah',
    voided: false,
    void_reason: null,
    ...extra,
  };
}

// ------------------------------------------------------------- oxygen

const DEVICES = {
  room_air: ['Room Air', 'RA'],
  nasal_cannula: ['Nasal Cannula / Prongs', 'NP'],
  simple_mask: ['Simple Face Mask', 'FM'],
  venturi_mask: ['Venturi Mask', 'VM'],
  non_rebreather: ['Non-Rebreather Mask', 'NRM'],
  hfnc: ['High Flow Nasal Cannula (HFNC)', 'HFNC'],
};

const TARGET_NOTES = { '94-98': 'Most patients', '88-92': 'Risk of hypercapnia, e.g. COPD' };

// Each demo patient's oxygen as the ward recorded it: changes made on the
// Oxygen Therapy tab, and oxygen recorded with a vital signs reading ("vitals").
// Times are written as of 16 Jun 10:00 (src/data/demoClock.js), like the vital
// signs in src/data/mockData.js, whose SpO2 the chart shows. Patients not
// listed have no oxygen recorded.
const OXYGEN_DEMO = {
  // Pneumonia: weaned from a reservoir mask, turned up again with this morning's vitals
  5001: {
    target: [94, 98],
    settings: [
      { at: '13 Jun 02:30', device: 'non_rebreather', flow: 15, by: 'SN Rahman', notes: 'From ED, SpO₂ 86% on room air' },
      { at: '13 Jun 14:00', device: 'simple_mask', flow: 8, by: 'SN Aisyah', notes: 'Weaning, SpO₂ stable' },
      { at: '14 Jun 09:00', device: 'nasal_cannula', flow: 2, by: 'SN Aisyah' },
      { at: '16 Jun 09:42', device: 'nasal_cannula', flow: 4, source: 'vitals', by: 'SN Aisyah' },
    ],
    voided: [{ at: '14 Jun 10:00', device: 'hfnc', flow: 40, fio2: 40, by: 'SN Aisyah', reason: 'Entered for the wrong patient' }],
  },
  // Sepsis, worse overnight: stepped up from room air to a reservoir mask
  5003: {
    target: [94, 98],
    settings: [
      { at: '15 Jun 21:00', device: 'room_air', source: 'vitals', by: 'SN Mei Ling' },
      { at: '16 Jun 01:00', device: 'nasal_cannula', flow: 2, by: 'SN Mei Ling', notes: 'SpO₂ 93% on room air' },
      { at: '16 Jun 05:00', device: 'nasal_cannula', flow: 4, by: 'SN Mei Ling' },
      { at: '16 Jun 07:30', device: 'simple_mask', flow: 8, source: 'vitals', by: 'SN Aisyah' },
      { at: '16 Jun 08:30', device: 'non_rebreather', flow: 15, by: 'SN Aisyah', notes: 'Escalated, sepsis review called' },
    ],
  },
  // Post-op, weaned off this morning
  5005: {
    target: [94, 98],
    settings: [
      { at: '14 Jun 08:00', device: 'nasal_cannula', flow: 3, by: 'SN Rahman', notes: 'Post-op' },
      { at: '15 Jun 21:00', device: 'nasal_cannula', flow: 2, source: 'vitals', by: 'SN Rahman' },
      { at: '16 Jun 05:00', device: 'nasal_cannula', flow: 1, by: 'SN Aisyah', notes: 'Weaning' },
      { at: '16 Jun 07:30', device: 'room_air', by: 'SN Aisyah', notes: 'Weaned off' },
    ],
  },
  // On room air throughout, as recorded with the vitals
  6001: {
    target: null,
    settings: [{ at: '15 Jun 21:00', device: 'room_air', source: 'vitals', by: 'SN Kumar' }],
  },
  // COPD on high-flow after ICU: a lower target, being weaned
  7001: {
    target: [88, 92],
    settings: [
      { at: '12 Jun 08:00', device: 'hfnc', flow: 50, fio2: 60, by: 'SN Kumar', notes: 'Stepped down from ICU on high-flow' },
      { at: '14 Jun 14:00', device: 'hfnc', flow: 40, fio2: 45, by: 'SN Kumar' },
      { at: '16 Jun 05:00', device: 'hfnc', flow: 35, fio2: 35, by: 'SN Kumar', notes: 'Weaning' },
    ],
  },
};

/** "2 d 3 h", "3 h 20 min", "45 min", as the server words it. */
function durationLabel(minutes) {
  const d = Math.floor(minutes / 1440);
  const h = Math.floor((minutes % 1440) / 60);
  const m = minutes % 60;
  if (d) return `${d} d${h ? ` ${h} h` : ''}`;
  if (h) return `${h} h${m ? ` ${m} min` : ''}`;
  return `${m} min`;
}

function describeOxygen(setting) {
  const [label, abbr] = DEVICES[setting.device] ?? [setting.device, setting.device.toUpperCase()];
  const onOxygen = setting.device !== 'room_air';
  const parts = [
    setting.flow != null ? `${setting.flow} L/min` : null,
    setting.fio2 != null ? `FiO₂ ${setting.fio2}%` : null,
  ].filter(Boolean);
  return {
    label,
    abbr,
    onOxygen,
    short: abbr + (onOxygen ? (setting.flow != null ? ` ${setting.flow}L` : setting.fio2 != null ? ` ${setting.fio2}%` : '') : ''),
    settings: onOxygen ? parts.join(', ') || null : 'No supplemental O₂',
  };
}

/**
 * The O₂ tab for a demo bed, worked out the way OxygenTherapyChart does: the
 * settings in order, the latest one in force now, and SpO2 from the bed's own
 * vital signs, each read against the oxygen in force when it was taken.
 */
function demoOxygen(bed) {
  const demo = OXYGEN_DEMO[bed.patient_id ?? bed.id] ?? { target: null, settings: [] };
  const target = demo.target;
  const nowMs = demoNow();
  const readings = (bed.vitals_history ?? [])
    .filter((row) => row.spo2 != null && row.recorded_at)
    .map((row) => ({ t: Date.parse(row.recorded_at), value: row.spo2 }))
    .sort((a, b) => a.t - b.t);

  const steps = demo.settings.map((s, i) => {
    const t = demoTime(s.at).getTime();
    const next = demo.settings[i + 1];
    const end = next ? demoTime(next.at).getTime() : nowMs;
    return { ...s, ...describeOxygen(s), t, minutes: Math.round((end - t) / 60000), last: !next };
  });
  const current = steps[steps.length - 1] ?? null;

  // A reading timed the same moment as a change on the ward is taken as just before it
  const settingFor = (t) => {
    let found = null;
    steps.forEach((s) => {
      if (s.t < t || (s.t === t && s.source === 'vitals')) found = s;
    });
    return found;
  };
  const stateOf = (value, setting) => {
    if (!target) return null;
    if (value < target[0]) return 'below';
    return value > target[1] && setting?.onOxygen ? 'above' : null;
  };
  const points = readings.map((r) => ({ ...r, setting: settingFor(r.t) }));
  const latest = points[points.length - 1] ?? null;
  // Taken before the oxygen last changed, it says little about the oxygen now
  const stale = latest && current ? latest.setting !== current : false;
  const state = latest && !stale ? stateOf(latest.value, latest.setting) : null;
  let since = null;
  for (let i = steps.length - 1; i >= 0 && steps[i].onOxygen; i--) since = steps[i];

  const targetLabel = target ? `${target[0]}–${target[1]}%` : null;
  const entry = (s, voided) => ({
    t: s.t,
    key: `${voided ? 'void' : s.source === 'vitals' ? 'reading' : 'change'}-${bed.patient_id}-${s.t}`,
    time_label: stampOf(new Date(s.t)),
    device: s.device,
    label: s.label,
    short: s.short,
    settings: s.onOxygen ? s.settings : null,
    on_oxygen: s.onOxygen,
    target_label: targetLabel,
    duration_label: voided ? null : `${durationLabel(s.minutes)}${s.last ? ' so far' : ''}`,
    current: !voided && s.last,
    source: s.source ?? 'therapy',
    by: s.by ?? null,
    notes: s.notes ?? null,
    spo2: s.source === 'vitals' ? readings.find((r) => r.t === s.t)?.value ?? null : null,
    voided,
    void_reason: voided ? s.reason : null,
  });
  const history = steps.map((s) => entry(s, false))
    .concat((demo.voided ?? []).map((v) => entry({ ...v, ...describeOxygen(v), t: demoTime(v.at).getTime() }, true)))
    .sort((a, b) => b.t - a.t)
    .map(({ t, ...rest }) => rest);
  const earliest = Math.min(nowMs, ...steps.map((s) => s.t), ...readings.map((r) => r.t));

  return {
    current: current ? {
      device: current.device,
      label: current.label,
      abbr: current.abbr,
      short: current.short,
      settings: current.settings,
      on_oxygen: current.onOxygen,
      since_label: stampOf(new Date(current.t)),
      duration_label: durationLabel(current.minutes),
      source: current.source ?? 'therapy',
      by: current.by ?? null,
      notes: current.source === 'vitals' ? null : current.notes ?? null,
    } : null,
    target: target
      ? { min: target[0], max: target[1], label: targetLabel, note: TARGET_NOTES[`${target[0]}-${target[1]}`] ?? null }
      : null,
    latest_spo2: latest ? {
      value: latest.value,
      time_label: stampOf(new Date(latest.t)),
      on: latest.setting?.short ?? null,
      state,
      stale,
    } : null,
    alert: state === 'below' ? 'critical' : state === 'above' ? 'warning' : null,
    on_oxygen_since_label: since ? stampOf(new Date(since.t)) : null,
    on_oxygen_duration_label: since ? durationLabel(Math.round((nowMs - since.t) / 60000)) : null,
    history,
    chart: {
      now: wallMs(nowMs),
      steps: steps.map((s) => ({
        t: wallMs(s.t),
        device: s.device,
        abbr: s.abbr,
        label: s.label,
        settings: s.settings,
        // Room air adds no flow and is 21% oxygen
        flow: s.onOxygen ? s.flow ?? null : 0,
        fio2: s.onOxygen ? s.fio2 ?? null : 21,
        target,
      })),
      spo2: points.map((p) => ({ t: wallMs(p.t), y: p.value, on: p.setting?.short ?? null, state: stateOf(p.value, p.setting) })),
      range: nowMs - earliest > 72 * 3600000 ? '72h' : 'all',
    },
  };
}

// --------------------------------------------------- lab investigations

const IN_THE_LAB = ['ordered', 'collected', 'in_progress'];

function result(name, value, unit, range, flag = '') {
  const level = ['HH', 'LL', 'AA', 'C'].includes(flag) ? 'critical' : flag ? 'abnormal' : null;
  return { name, value, unit, range, flag, level };
}

/** Sample investigations, one of each review state, timed around now. */
function labTemplates(doctorName) {
  return {
    fbc_overdue: {
      test_name: 'Full Blood Count', test_code: 'FBC', category: 'Haematology', specimen: 'Blood (EDTA)',
      priority: 'routine', status: 'resulted', ordered_ago: 60 * 30, resulted_ago: 60 * 26,
      review_state: 'overdue', review_label: `Review overdue since ${stamp(60 * 2)}`, flag: 'abnormal',
      results: [
        result('Haemoglobin', '9.8', 'g/dL', '12.0-15.0', 'L'),
        result('White cell count', '13.2', 'x10^9/L', '4.0-11.0', 'H'),
        result('Platelets', '245', 'x10^9/L', '150-400'),
      ],
    },
    fbc_due: {
      test_name: 'Full Blood Count', test_code: 'FBC', category: 'Haematology', specimen: 'Blood (EDTA)',
      priority: 'routine', status: 'resulted', ordered_ago: 60 * 3, resulted_ago: 60,
      review_state: 'due', review_label: `Review by ${stamp(-60 * 23)}`, flag: null,
      results: [
        result('Haemoglobin', '12.9', 'g/dL', '12.0-15.0'),
        result('White cell count', '8.1', 'x10^9/L', '4.0-11.0'),
        result('Platelets', '210', 'x10^9/L', '150-400'),
      ],
    },
    renal_critical: {
      test_name: 'Renal Profile (BUSE)', test_code: 'RP', category: 'Biochemistry', specimen: 'Blood (Plain)',
      priority: 'urgent', status: 'resulted', ordered_ago: 60 * 4, resulted_ago: 60 * 2,
      review_state: 'due', review_label: `Review by ${stamp(-60 * 2)}`, flag: 'critical',
      comment: 'Critical potassium phoned to ward by lab.',
      results: [
        result('Sodium', '134', 'mmol/L', '135-145', 'L'),
        result('Potassium', '6.2', 'mmol/L', '3.5-5.1', 'HH'),
        result('Urea', '12.4', 'mmol/L', '2.5-7.1', 'H'),
        result('Creatinine', '168', 'umol/L', '49-90', 'H'),
      ],
    },
    renal_reviewed: {
      test_name: 'Renal Profile (BUSE)', test_code: 'RP', category: 'Biochemistry', specimen: 'Blood (Plain)',
      priority: 'routine', status: 'resulted', ordered_ago: 60 * 20, resulted_ago: 60 * 17,
      review_state: 'reviewed', review_label: `Reviewed ${stamp(60 * 15)} by ${doctorName}`, flag: null,
      results: [
        result('Sodium', '139', 'mmol/L', '135-145'),
        result('Potassium', '4.2', 'mmol/L', '3.5-5.1'),
        result('Creatinine', '78', 'umol/L', '49-90'),
      ],
    },
    abg_due_soon: {
      test_name: 'Arterial Blood Gas', test_code: 'ABG', category: 'Blood Gas', specimen: 'Arterial blood',
      priority: 'stat', status: 'resulted', ordered_ago: 70, resulted_ago: 40,
      review_state: 'due_soon', review_label: `Review due soon, by ${stamp(-20)}`, flag: 'abnormal',
      results: [
        result('pH', '7.31', '', '7.35-7.45', 'L'),
        result('pCO2', '6.8', 'kPa', '4.7-6.0', 'H'),
        result('pO2', '9.2', 'kPa', '10.0-13.3', 'L'),
        result('Lactate', '2.6', 'mmol/L', '0.5-2.0', 'H'),
      ],
    },
    coag_reviewed: {
      test_name: 'Coagulation Profile (PT/INR/APTT)', test_code: 'COAG', category: 'Coagulation', specimen: 'Blood (Citrate)',
      priority: 'routine', status: 'resulted', ordered_ago: 60 * 30, resulted_ago: 60 * 25,
      review_state: 'reviewed', review_label: `Reviewed ${stamp(60 * 22)} by ${doctorName}`, flag: null,
      results: [result('PT', '12.8', 's', '11.0-13.5'), result('INR', '1.1', '', '0.8-1.2'), result('APTT', '31', 's', '25-37')],
    },
    crp_pending: {
      test_name: 'C-Reactive Protein', test_code: 'CRP', category: 'Biochemistry', specimen: 'Blood (Plain)',
      priority: 'stat', status: 'ordered', ordered_ago: 25, review_state: 'awaiting_result', review_label: 'Awaiting result',
    },
    culture: {
      test_name: 'Blood Culture & Sensitivity', test_code: 'BCS', category: 'Microbiology', specimen: 'Blood culture bottles x2',
      priority: 'urgent', status: 'in_progress', ordered_ago: 60 * 20, collected_ago: 60 * 19,
      review_state: 'awaiting_result', review_label: 'Awaiting result',
      comment: 'Preliminary: no growth at 18 hours. Final report at 5 days.',
    },
  };
}

// Which sample investigations each demo patient has, results to review first,
// in keeping with their story (see OXYGEN_DEMO and src/data/mockData.js)
const LABS_DEMO = {
  // Pneumonia: a raised white count overdue for review, cultures and CRP still in the lab
  5001: ['fbc_overdue', 'crp_pending', 'culture', 'coag_reviewed'],
  // Sepsis: a critical potassium with an acute kidney injury, and a lactic acidosis on the gas
  5003: ['renal_critical', 'abg_due_soon', 'culture', 'crp_pending'],
  // Post-op, recovering: a normal count to sign off
  5005: ['fbc_due'],
  6001: ['renal_reviewed'],
  // COPD: a hypercapnic blood gas
  7001: ['abg_due_soon', 'coag_reviewed'],
};

const STATUS_LABELS = { ordered: 'Ordered', collected: 'Specimen collected', in_progress: 'In progress', resulted: 'Resulted', cancelled: 'Cancelled' };
const PRIORITY_LABELS = { stat: 'STAT', urgent: 'Urgent', routine: 'Routine' };

function labCounts(items) {
  return {
    awaiting_review: items.filter((l) => l.can_review).length,
    overdue: items.filter((l) => l.review_state === 'overdue').length,
    critical: items.filter((l) => l.can_review && l.flag === 'critical').length,
    pending: items.filter((l) => IN_THE_LAB.includes(l.status)).length,
  };
}

function demoLabs(patientId, doctorName) {
  const templates = labTemplates(doctorName);
  const items = (LABS_DEMO[patientId] ?? []).map((key, i) => {
    const t = templates[key];
    return {
      id: `${patientId}-lab${i + 1}`,
      test_name: t.test_name,
      test_code: t.test_code,
      order_no: `LAB${String(patientId).slice(-3)}${pad(i + 1)}`,
      category: t.category,
      specimen: t.specimen,
      priority: t.priority,
      priority_label: PRIORITY_LABELS[t.priority],
      status: t.status,
      status_label: STATUS_LABELS[t.status],
      ordered_label: stamp(t.ordered_ago),
      ordered_by: doctorName,
      collected_label: t.collected_ago != null ? stamp(t.collected_ago) : null,
      resulted_label: t.resulted_ago != null ? stamp(t.resulted_ago) : null,
      results: t.results ?? [],
      flag: t.flag ?? null,
      comment: t.comment ?? null,
      review_state: t.review_state,
      review_label: t.review_label,
      can_review: t.status === 'resulted' && t.review_state !== 'reviewed',
      sample: false,
    };
  });
  return { enabled: true, items, counts: labCounts(items) };
}

/** Totals, the limit bar and the alerts, worked out again from the entries and the plan. */
function recalc(chart) {
  const io = chart.io;
  const counted = io.entries.filter((e) => !e.voided);
  const sum = (dir) => counted.filter((e) => e.direction === dir).reduce((t, e) => t + e.volume_ml, 0);
  const intake = sum('intake');
  const output = sum('output');
  const urine = counted.filter((e) => e.type === 'urine').reduce((t, e) => t + e.volume_ml, 0);
  io.totals = { intake, output, balance: intake - output, urine };

  const byType = {};
  counted.forEach((e) => {
    const key = `${e.direction}:${e.type}`;
    byType[key] = byType[key] ?? { direction: e.direction, type: e.type, label: e.type_label, volume: 0 };
    byType[key].volume += e.volume_ml;
  });
  io.by_type = Object.values(byType);

  const alerts = [];
  const cap = io.plan?.intake_limit_ml ?? null;
  if (cap) {
    const state = intake > cap ? 'over' : intake * 100 >= cap * 80 ? 'near' : 'ok';
    io.limit = {
      limit: cap,
      taken: intake,
      percent: Math.floor((intake * 100) / cap),
      remaining: Math.max(0, cap - intake),
      over_by: Math.max(0, intake - cap),
      state,
    };
    if (state === 'over') {
      alerts.push({ level: 'critical', title: 'Over the intake limit', detail: `${formatMl(intake)} taken in against a limit of ${formatMl(cap)}, ${formatMl(intake - cap)} over.` });
    } else if (state === 'near') {
      alerts.push({ level: 'warning', title: 'Near the intake limit', detail: `${formatMl(intake)} of ${formatMl(cap)} taken in. ${formatMl(cap - intake)} left until 07:00.` });
    }
  } else {
    io.limit = null;
  }

  const urineMin = io.plan?.urine_min_ml_per_hour ?? null;
  if (urineMin) {
    const hours = io.observed_hours;
    const average = hours >= 1 ? Math.round(urine / hours) : null;
    const state = hours < 4 ? 'pending' : average < urineMin ? 'low' : 'ok';
    io.urine = { min: urineMin, average, hours: Math.floor(hours), state };
    if (state === 'low') {
      alerts.push({ level: 'warning', title: 'Urine output below target', detail: `Averaging ${average} mL/h (${formatMl(urine)} since 07:00), against at least ${urineMin} mL/h.` });
    }
  } else {
    io.urine = null;
  }

  (io.extra_alerts ?? []).forEach((a) => alerts.push(a));
  alerts.sort((a, b) => (a.level === 'critical' ? 0 : 1) - (b.level === 'critical' ? 0 : 1));
  io.alerts = alerts;
  io.days[0] = { ...io.days[0], intake, output, balance: intake - output, limit: cap, over: cap != null && intake > cap };

  chart.orders.open.forEach((o) => { o.can_cancel = o.is_mine; });
  chart.labs.counts = labCounts(chart.labs.items);
  chart.badges = {
    io_level: alerts[0]?.level ?? null,
    meds_overdue: chart.medications.counts.overdue,
    orders_open: chart.orders.open.length,
    oxygen_short: chart.oxygen.current?.on_oxygen ? chart.oxygen.current.short : null,
    oxygen_level: chart.oxygen.alert,
    labs_review: chart.labs.counts.awaiting_review,
    labs_overdue: chart.labs.counts.overdue,
  };
  chart.generated_label = timeNow();
  return chart;
}

function seedChart(bed, doctorName) {
  const id = bed.patient_id ?? bed.id;
  // The chart holds what the bed card shows: I/O only where something is charted
  // today, and a fluid restriction where the bed has a limit (of the demo patients, 5001)
  const charted = bed.io != null;
  const restricted = bed.io?.limit != null;
  const hoursIntoDay = Math.max(1, (new Date().getHours() - 7 + 24) % 24 || 1);
  let n = 1;

  const entries = !charted ? [] : [
    entry(n++, 20, 'output', 'urine', 180, 'Catheter'),
    entry(n++, 55, 'intake', 'iv_med', 100, 'Ceftriaxone 2 g IV in 100 mL', { auto: 'medication dose' }),
    entry(n++, 70, 'intake', 'iv', 125, 'Pump: 0.9% NaCl (LVP-07)', { auto: 'infusion pump', by: null }),
    entry(n++, 95, 'intake', 'oral', 200, 'Water'),
    entry(n++, 130, 'output', 'urine', 220, 'Catheter'),
    entry(n++, 150, 'intake', 'oral', 150, 'Tea / coffee', { voided: true, void_reason: 'Entered twice' }),
    entry(n++, 150, 'intake', 'oral', 150, 'Tea / coffee'),
    entry(n++, 190, 'intake', 'iv', 125, 'Pump: 0.9% NaCl (LVP-07)', { auto: 'infusion pump', by: null }),
    entry(n++, 230, 'output', 'urine', 150, 'Catheter'),
  ].concat(restricted ? [entry(n++, 260, 'intake', 'tube_feed', 250, 'NG feed')] : []);

  const chart = {
    patient: {
      id,
      name: bed.patient_name ?? 'Demo patient',
      mrn: bed.mrn ?? null,
      gender: bed.gender ?? null,
      age: bed.age ?? null,
      bed: bed.number ?? null,
      ward: bed.ward_name ?? null,
      consultant: bed.consultant ?? doctorName,
      admitted_label: stamp(60 * 50),
      vip: bed.vip_status ?? null,
      allergies: (bed.allergy_list ?? []).filter((a) => !a.resolved).map((a) => a.label),
      allergy_list: bed.allergy_list ?? [],
    },
    io: {
      day: { key: 'today', label: 'Today, 07:00 to 07:00', is_current: true, previous: null, next: null },
      observed_hours: hoursIntoDay,
      plan: restricted
        ? {
            intake_limit_ml: 1500,
            urine_min_ml_per_hour: 30,
            summary: planSummary(1500, 30),
            notes: `Consultant order by ${doctorName}: Fluid restrict to 1.5 L a day. Daily weights.`,
            set_label: `Set ${stamp(60 * 20)} by SN Aisyah`,
            from_order: true,
          }
        : null,
      entries,
      overload: restricted
        ? { time_label: stamp(180), edema: 'Edema 2+ (feet / ankles)', signs: ['Crackles in the lungs'], urgent: false, weight_kg: 71.8, by: 'SN Aisyah' }
        : null,
      weight: restricted ? { kg: 71.8, change: 0.6, at_label: stamp(180).slice(0, 6), gain: null } : null,
      extra_alerts: restricted
        ? [{ level: 'warning', title: 'Signs of fluid overload', detail: `Edema 2+ (feet / ankles), Crackles in the lungs. Recorded ${stamp(180)} by SN Aisyah.` }]
        : [],
      days: [
        { key: 'today', label: 'Today', intake: 0, output: 0, balance: 0, limit: null, over: false },
        { key: 'd1', label: 'Yesterday', intake: 1890, output: 1420, balance: 470, limit: restricted ? 1500 : null, over: restricted },
        { key: 'd2', label: '2 days ago', intake: 1610, output: 1550, balance: 60, limit: null, over: false },
      ],
      stay_balance: 980,
    },
    medications: {
      active: [
        {
          id: `${id}-m1`, name: 'Ceftriaxone', summary: '2 g IV OD', dose: '2 g', route: 'Intravenous (IV)',
          frequency: 'OD (every 24 h)', state: 'scheduled', due_label: 'Due in 23h 5m', high_alert: false,
          instructions: 'Give over 30 minutes', io_volume_ml: 100, last_given_label: stamp(55),
          doses: [
            { status: 'given', status_label: 'Given', time_label: stamp(55), by: 'SN Aisyah', notes: null },
            { status: 'given', status_label: 'Given', time_label: stamp(55 + 1440), by: 'SN Rahman', notes: null },
          ],
        },
        {
          id: `${id}-m2`, name: 'Paracetamol', summary: '1 g PO QID', dose: '1 g', route: 'Oral (PO)',
          frequency: 'QID (every 6 h)', state: 'overdue', due_label: 'Overdue 25m', high_alert: false,
          instructions: null, io_volume_ml: null, last_given_label: stamp(385),
          doses: [
            { status: 'given', status_label: 'Given', time_label: stamp(385), by: 'SN Rahman', notes: null },
            { status: 'held', status_label: 'Held', time_label: stamp(745), by: 'SN Rahman', notes: 'Temp 36.8, not needed' },
          ],
        },
        {
          id: `${id}-m3`, name: 'Enoxaparin', summary: '40 mg SC OD', dose: '40 mg', route: 'Subcutaneous (SC)',
          frequency: 'OD (every 24 h)', state: 'due_soon', due_label: 'Due in 20m', high_alert: true,
          instructions: null, io_volume_ml: null, last_given_label: stamp(1420),
          doses: [{ status: 'given', status_label: 'Given', time_label: stamp(1420), by: 'SN Mei Ling', notes: null }],
        },
      ],
      inactive: [
        {
          id: `${id}-m4`, name: 'Amoxicillin-clavulanate', summary: '1.2 g IV TDS', status: 'stopped',
          status_label: 'Stopped', closed_label: stamp(60 * 30), stop_reason: 'Changed to ceftriaxone',
          doses: [{ status: 'given', status_label: 'Given', time_label: stamp(60 * 31), by: 'SN Rahman', notes: null }],
        },
      ],
      counts: { active: 3, overdue: 1, due_soon: 1 },
    },
    orders: {
      open: [
        {
          id: `${id}-o1`, instruction: 'Repeat FBC and renal profile at 06:00.', urgency: 'routine', urgency_label: 'Routine',
          ordered_label: stamp(240), consultant: doctorName, is_mine: true, assigned_nurse: 'SN Aisyah',
          fluid_restriction: null, status: 'open', status_label: 'Open', closed_label: null, closed_by: null,
          outcome_note: null, can_cancel: true,
        },
        {
          id: `${id}-o2`, instruction: 'Chest X-ray today, portable.', urgency: 'urgent', urgency_label: 'Urgent',
          ordered_label: stamp(90), consultant: 'Dr. Wong Mei Ling', is_mine: false, assigned_nurse: 'SN Aisyah',
          fluid_restriction: null, status: 'open', status_label: 'Open', closed_label: null, closed_by: null,
          outcome_note: null, can_cancel: false,
        },
      ].slice(0, bed.pending_orders ?? 2), // as many open as the bed card shows
      closed: [
        {
          id: `${id}-o3`, instruction: 'ECG now.', urgency: 'stat', urgency_label: 'STAT', ordered_label: stamp(600),
          consultant: doctorName, is_mine: true, assigned_nurse: 'SN Rahman', fluid_restriction: null, status: 'done',
          status_label: 'Done', closed_label: stamp(585), closed_by: 'SN Rahman', outcome_note: 'Sinus rhythm, rate 88.',
          can_cancel: false,
        },
      ],
    },
    oxygen: demoOxygen(bed),
    labs: demoLabs(id, doctorName),
  };

  return recalc(chart);
}

const charts = new Map();
const planBefore = new Map();
let orderCounter = 1;

function chartFor(bed, doctorName) {
  const key = bed.patient_id ?? bed.id;
  if (!charts.has(key)) charts.set(key, seedChart(bed, doctorName));
  return charts.get(key);
}

const copy = (value) => JSON.parse(JSON.stringify(value));

/** Forget every demo chart and the demo's clock (on logout), so the next demo starts fresh. */
export function resetDemoCharts() {
  charts.clear();
  planBefore.clear();
  resetDemoClock();
}

/**
 * A demo bed with its figures worked out from its chart, as the server works
 * the dashboard's out from the same records: the open orders, today's I/O
 * against the fluid plan, the oxygen now and the lab results waiting for
 * review. An order written or a result reviewed in the demo shows on the bed
 * card at the next dashboard refresh, as it does live.
 */
export function demoBedFromChart(bed, doctorName) {
  const chart = chartFor(bed, doctorName);
  const { io, labs, oxygen } = chart;
  const current = oxygen.current;

  return {
    ...bed,
    pending_orders: chart.orders.open.length,
    io: io.entries.length || io.plan ? {
      intake: io.totals.intake,
      output: io.totals.output,
      balance: io.totals.balance,
      limit: io.limit,
      level: io.alerts[0]?.level ?? null,
      alerts: io.alerts.map((a) => a.title),
    } : null,
    oxygen: current ? {
      on_oxygen: current.on_oxygen,
      short: current.short,
      label: current.label,
      settings: current.settings,
      since_label: current.since_label,
      target_label: oxygen.target?.label ?? null,
      spo2_state: oxygen.latest_spo2?.state ?? null,
    } : null,
    labs: labs.counts.awaiting_review ? {
      awaiting_review: labs.counts.awaiting_review,
      overdue: labs.counts.overdue,
      critical: labs.counts.critical,
    } : null,
    pending_review: labs.counts.awaiting_review > 0,
  };
}

/**
 * An in-memory client with the same methods and answers as the live one.
 */
export function createDemoChartClient(bed, doctorName = 'Consultant') {
  return {
    load: async () => ({ success: true, chart: copy(chartFor(bed, doctorName)) }),

    createOrder: async ({ instruction, urgency, fluid_limit_ml: limit, urine_min_ml_per_hour: urineMin }) => {
      const text = (instruction ?? '').trim();
      if (!text) {
        const err = new Error('Write the order.');
        err.status = 422;
        throw err;
      }
      const chart = chartFor(bed, doctorName);
      const restriction = restrictionSummary(limit ?? null, urineMin ?? null);
      const orderId = `demo-${orderCounter++}`;
      chart.orders.open.unshift({
        id: orderId, instruction: text, urgency, urgency_label: URGENCY_LABELS[urgency] ?? urgency,
        ordered_label: stampNow(), consultant: doctorName, is_mine: true, assigned_nurse: 'SN Aisyah',
        fluid_restriction: restriction, status: 'open', status_label: 'Open', closed_label: null, closed_by: null,
        outcome_note: null, can_cancel: true,
      });

      if (restriction) {
        const current = chart.io.plan;
        const nextLimit = limit ?? current?.intake_limit_ml ?? null;
        const nextUrine = urineMin ?? current?.urine_min_ml_per_hour ?? null;
        // Remembered so cancelling the order can go back to it, as the server does
        planBefore.set(orderId, current ? { ...current } : null);
        chart.io.plan = {
          intake_limit_ml: nextLimit,
          urine_min_ml_per_hour: nextUrine,
          summary: planSummary(nextLimit, nextUrine),
          notes: `Consultant order by ${doctorName}: ${text}`,
          set_label: `Set ${stampNow()}`,
          from_order: true,
          order_id: orderId,
        };
      }

      recalc(chart);
      return {
        success: true,
        message: `Order sent to SN Aisyah.${restriction ? ' The fluid plan on the I/O chart now follows it.' : ''}`,
        chart: copy(chart),
      };
    },

    cancelOrder: async (orderId, reason) => {
      const text = (reason ?? '').trim();
      if (!text) {
        const err = new Error('Give a reason for cancelling the order.');
        err.status = 422;
        throw err;
      }
      const chart = chartFor(bed, doctorName);
      const order = chart.orders.open.find((o) => o.id === orderId);
      if (!order || !order.is_mine) {
        const err = new Error('Only the consultant who wrote an order can cancel it from the app.');
        err.status = 403;
        throw err;
      }
      chart.orders.open = chart.orders.open.filter((o) => o.id !== orderId);
      chart.orders.closed.unshift({
        ...order, status: 'cancelled', status_label: 'Cancelled', closed_label: stampNow(), can_cancel: false,
        outcome_note: `Cancelled by ${doctorName} in the doctor app: ${text}`,
      });

      // The order's restriction is lifted while it is still the plan in force
      if (chart.io.plan?.order_id === orderId) {
        const previous = planBefore.get(orderId);
        const prevLimit = previous?.intake_limit_ml ?? null;
        const prevUrine = previous?.urine_min_ml_per_hour ?? null;
        chart.io.plan = {
          intake_limit_ml: prevLimit,
          urine_min_ml_per_hour: prevUrine,
          summary: planSummary(prevLimit, prevUrine),
          notes: `Fluid restriction lifted: the consultant order was cancelled (${text})`,
          set_label: `Set ${stampNow()}`,
          from_order: false,
        };
      }
      recalc(chart);
      return { success: true, message: 'Order cancelled.', chart: copy(chart) };
    },

    reviewLab: async (labId) => {
      const chart = chartFor(bed, doctorName);
      const lab = chart.labs.items.find((l) => l.id === labId);
      if (!lab?.can_review) {
        const err = new Error(`${lab?.test_name ?? 'That investigation'} has no result waiting for review.`);
        err.status = 422;
        throw err;
      }
      Object.assign(lab, { can_review: false, review_state: 'reviewed', review_label: `Reviewed ${stampNow()} by ${doctorName}` });
      recalc(chart);
      return { success: true, message: `${lab.test_name} marked as reviewed.`, chart: copy(chart) };
    },
  };
}
