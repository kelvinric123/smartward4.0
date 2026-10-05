// Demo login: the chart's lab investigations, oxygen therapy and assessment
// scales, and the patient's VIP, payor, COE and expected discharge -
// answering like the server (PatientChartSections, NurseAppPatientBundle),
// so those tabs work without a server.

import { ago, ahead, dm, hm, when } from './demoTime';

// ------------------------------------------------------------ the demo ward

const DEVICES = [
  { key: 'room_air', label: 'Room Air', room_air: true, flow: [], fio2: [], hint: null },
  { key: 'nasal_cannula', label: 'Nasal Cannula / Prongs', flow: [1, 2, 3, 4], fio2: [], hint: 'Usually 1 to 4 L/min, at most 6.' },
  { key: 'simple_mask', label: 'Simple Face Mask', flow: [5, 6, 8, 10], fio2: [], hint: '5 to 10 L/min. Below 5 L/min the patient can rebreathe CO₂.' },
  { key: 'venturi_mask', label: 'Venturi Mask', flow: [], fio2: [24, 28, 31, 35, 40, 60], hint: 'Record the FiO₂ of the valve fitted: 24, 28, 31, 35, 40 or 60%.' },
  { key: 'non_rebreather', label: 'Non-Rebreather Mask', flow: [10, 12, 15], fio2: [], hint: '10 to 15 L/min, enough to keep the reservoir bag inflated.' },
  { key: 'hfnc', label: 'High Flow Nasal Cannula (HFNC)', flow: [30, 40, 50, 60], fio2: [30, 40, 50, 60], hint: 'Record both the flow rate and the FiO₂ set.' },
  { key: 'cpap', label: 'CPAP', flow: [], fio2: [30, 40, 50, 60], hint: 'Record the FiO₂ set on the device.' },
  { key: 'other', label: 'Other', flow: [], fio2: [], hint: 'Record the flow rate or FiO₂, and name the device in the notes.' },
].map((d) => ({ room_air: false, ...d }));

const ABBR = { room_air: 'RA', nasal_cannula: 'NC', simple_mask: 'SFM', venturi_mask: 'VM', non_rebreather: 'NRB', hfnc: 'HFNC', cpap: 'CPAP', other: 'O₂' };
const TARGETS = [
  { min: 94, max: 98, label: 'Most patients' },
  { min: 88, max: 92, label: 'Risk of hypercapnia, e.g. COPD' },
];

const MORSE = {
  id: 901,
  code: 'MORSE',
  name: 'Morse Fall Scale',
  category: 'Fall risk',
  purpose: 'Identifies adults at risk of falling in hospital so that fall precautions can be put in place.',
  kind: 'scored',
  items: [
    { name: 'History of falling', abbr: null, options: [{ label: 'No', value: 0 }, { label: 'Yes', value: 25 }] },
    { name: 'Secondary diagnosis', abbr: null, options: [{ label: 'No', value: 0 }, { label: 'Yes', value: 15 }] },
    { name: 'Ambulatory aid', abbr: null, options: [{ label: 'None, bed rest or nurse assist', value: 0 }, { label: 'Crutches, cane or walker', value: 15 }, { label: 'Furniture', value: 30 }] },
    { name: 'Intravenous therapy or heparin lock', abbr: null, options: [{ label: 'No', value: 0 }, { label: 'Yes', value: 20 }] },
    { name: 'Gait and transferring', abbr: null, options: [{ label: 'Normal, bed rest or wheelchair', value: 0 }, { label: 'Weak', value: 10 }, { label: 'Impaired', value: 20 }] },
    { name: 'Mental status', abbr: null, options: [{ label: 'Oriented to own ability', value: 0 }, { label: 'Overestimates or forgets limitations', value: 15 }] },
  ],
  score_min: 0,
  score_max: 125,
  bands: [
    { label: 'No risk', min: 0, max: 24, tone: 'low' },
    { label: 'Low risk', min: 25, max: 44, tone: 'moderate' },
    { label: 'High risk', min: 45, max: null, tone: 'high' },
  ],
  note: 'Band cut-offs are commonly adjusted locally. Confirm these against your own falls policy before use.',
  every: 480, // due every 8 h, overdue after 12 h
  overdueAfter: 720,
};

const PAIN = {
  id: 902,
  code: 'PAIN',
  name: 'Pain Score',
  category: 'Pain',
  purpose: 'Pain intensity recorded with the observations. Whichever tool is used gives a score out of 10, so the numbers stay comparable.',
  kind: 'score',
  items: [],
  score_min: 0,
  score_max: 10,
  bands: [
    { label: 'No pain', min: 0, max: 0, tone: 'low' },
    { label: 'Mild', min: 1, max: 3, tone: 'low' },
    { label: 'Moderate', min: 4, max: 6, tone: 'moderate' },
    { label: 'Severe', min: 7, max: null, tone: 'high' },
  ],
  note: null,
  every: 240, // with the observations: due every 4 h, overdue after 6 h
  overdueAfter: 360,
};

const CSSRS = {
  id: 903,
  code: 'CSSRS',
  name: 'Mental State Assessment (C-SSRS)',
  category: 'Mental health',
  purpose: 'Screens for suicidal thoughts and behaviour with the six questions of the C-SSRS screener.',
  kind: 'screen',
  items: [],
  score_min: null,
  score_max: null,
  bands: [],
  note: null,
  every: null,
};

const SCALES = [MORSE, PAIN, CSSRS];

// ------------------------------------------------------- per demo patient

const LAB_SETS = {
  // A sick patient: an overdue FBC, a critical potassium, a STAT gas due soon, a culture growing
  sick: [
    { order: 1, code: 'FBC', name: 'Full Blood Count', category: 'Haematology', specimen: 'Blood (EDTA)', priority: 'routine', orderedAgo: 1800, resultedAgo: 1560, results: [['Haemoglobin', '9.8', 'g/dL', '12.0-15.0', 'L'], ['White cell count', '13.2', 'x10^9/L', '4.0-11.0', 'H'], ['Platelets', '245', 'x10^9/L', '150-400', '']] },
    { order: 2, code: 'RP', name: 'Renal Profile (BUSE)', category: 'Biochemistry', specimen: 'Blood (Plain)', priority: 'urgent', orderedAgo: 240, resultedAgo: 120, results: [['Sodium', '134', 'mmol/L', '135-145', 'L'], ['Potassium', '6.2', 'mmol/L', '3.5-5.1', 'HH'], ['Creatinine', '168', 'umol/L', '49-90', 'H']], comment: 'Critical potassium phoned to ward by lab.' },
    { order: 3, code: 'ABG', name: 'Arterial Blood Gas', category: 'Blood Gas', specimen: 'Arterial blood', priority: 'stat', orderedAgo: 70, resultedAgo: 40, results: [['pH', '7.31', '', '7.35-7.45', 'L'], ['pCO2', '6.8', 'kPa', '4.7-6.0', 'H'], ['Lactate', '2.6', 'mmol/L', '0.5-2.0', 'H']] },
    { order: 4, code: 'BCS', name: 'Blood Culture & Sensitivity', category: 'Microbiology', specimen: 'Blood culture bottles x2', priority: 'urgent', orderedAgo: 1200, status: 'in_progress', reviewDueIn: 5 * 1440 - 1200, comment: 'Preliminary: no growth at 18 hours. Final report at 5 days.' },
  ],
  // Settled: one reviewed, one on its way
  settled: [
    { order: 1, code: 'COAG', name: 'Coagulation Profile (PT/INR/APTT)', category: 'Coagulation', specimen: 'Blood (Citrate)', priority: 'routine', orderedAgo: 1700, resultedAgo: 1500, reviewedAgo: 1300, results: [['PT', '12.8', 's', '11.0-13.5', ''], ['INR', '1.1', '', '0.8-1.2', '']] },
    { order: 2, code: 'HBA1C', name: 'HbA1c', category: 'Biochemistry', specimen: 'Blood (EDTA)', priority: 'routine', orderedAgo: 120, status: 'collected' },
  ],
  // Renal: a STAT CRP still in the lab and an abnormal result due later today
  renal: [
    { order: 1, code: 'CRP', name: 'C-Reactive Protein', category: 'Biochemistry', specimen: 'Blood (Plain)', priority: 'stat', orderedAgo: 25, status: 'ordered' },
    { order: 2, code: 'RP', name: 'Renal Profile (BUSE)', category: 'Biochemistry', specimen: 'Blood (Plain)', priority: 'routine', orderedAgo: 300, resultedAgo: 180, results: [['Urea', '12.4', 'mmol/L', '2.5-7.1', 'H'], ['Creatinine', '210', 'umol/L', '49-90', 'H'], ['Potassium', '4.9', 'mmol/L', '3.5-5.1', '']] },
  ],
  none: [],
};

const EXTRAS = {
  5001: {
    severities: { Penicillin: 'Severe' },
    vip: null,
    payor: { status: 'approved', status_label: 'GL Approved', detail: 'Great Eastern Life' },
    coe: ['Cardiac Care'],
    discharge: { inDays: 3, projected: true },
    labs: 'sick',
    oxygen: [{ device: 'nasal_cannula', flow: 2, target: [94, 98], minutesAgo: 300 }],
    spo2: { value: 91, minutesAgo: 20 },
    scores: { MORSE: { minutesAgo: 800, items: [25, 15, 0, 20, 10, 0] }, PAIN: { minutesAgo: 150, score: 5 } },
  },
  5002: {
    severities: {},
    vip: 'VIP',
    payor: { status: 'gl_requested', status_label: 'GL Requested', detail: 'Corporate / Panel' },
    coe: [],
    discharge: { inDays: 0, projected: false },
    labs: 'settled',
    oxygen: [],
    spo2: null,
    scores: { MORSE: { minutesAgo: 200, items: [0, 15, 0, 0, 0, 0] }, PAIN: { minutesAgo: 90, score: 2 } },
  },
  5003: {
    severities: { Sulfonamides: 'Moderate', Latex: 'Mild' },
    vip: null,
    payor: { status: 'pending', status_label: 'Pending Verification', detail: 'Self Pay' },
    coe: ['Renal Care'],
    discharge: { inDays: -1, projected: false },
    labs: 'renal',
    oxygen: [{ device: 'venturi_mask', fio2: 28, target: [88, 92], minutesAgo: 90 }],
    spo2: { value: 96, minutesAgo: 30 },
    scores: { PAIN: { minutesAgo: 420, score: 3 }, CSSRS: { minutesAgo: 1500, band: 'No risk identified', tone: 'low', breakdown: 'No to every question asked' } },
  },
  5004: {
    severities: {},
    vip: 'VVIP',
    payor: null,
    coe: [],
    discharge: null,
    labs: 'none',
    oxygen: [{ device: 'room_air', minutesAgo: 1300 }],
    spo2: { value: 98, minutesAgo: 60 },
    scores: { MORSE: { minutesAgo: 300, items: [0, 0, 0, 0, 0, 0] }, PAIN: { minutesAgo: 100, score: 0 } },
  },
};

function extrasFor(bed) {
  return EXTRAS[bed.patient_id] ?? EXTRAS[5004];
}

/** The demo patient's lab, oxygen and assessment records, kept for the demo session. */
export function demoClinical(bed, next) {
  const x = extrasFor(bed);
  const doctor = bed.consultant ?? 'Dr. On-call';

  return {
    labs: LAB_SETS[x.labs].map((l) => ({
      id: next(),
      ...l,
      order_no: `LAB${String(bed.patient_id).slice(-2)}${String(l.order).padStart(4, '0')}`,
      status: l.status ?? 'resulted',
      ordered: ago(l.orderedAgo),
      resulted: l.resultedAgo != null ? ago(l.resultedAgo) : null,
      reviewDue: l.reviewDueIn != null ? ahead(l.reviewDueIn) : null,
      reviewed: l.reviewedAgo != null ? ago(l.reviewedAgo) : null,
      reviewedBy: l.reviewedAgo != null ? doctor : null,
      orderedBy: doctor,
    })),
    oxygen: x.oxygen.map((o) => ({ id: next(), ...o, at: ago(o.minutesAgo), by: 'Sr. Farah Idris', voided: false })),
    spo2: x.spo2 ? { value: x.spo2.value, at: ago(x.spo2.minutesAgo) } : null,
    scores: Object.entries(x.scores).map(([code, s]) => ({ id: next(), code, ...s, at: ago(s.minutesAgo), by: 'Sr. Farah Idris' })),
  };
}

// ------------------------------------------------------------ the patient

/** VIP, payor, COE and expected discharge, and the allergies with severity. */
export function demoPatientExtras(bed, allergies) {
  const x = extrasFor(bed);
  let discharge = null;
  if (x.discharge) {
    const d = ahead(x.discharge.inDays * 1440);
    const relative = x.discharge.inDays === 0 ? 'Today' : x.discharge.inDays === 1 ? 'Tomorrow' : x.discharge.inDays > 1 ? `In ${x.discharge.inDays} days` : `Overdue by ${-x.discharge.inDays} day${x.discharge.inDays === -1 ? '' : 's'}`;
    const label = `${dm(d)} ${d.getFullYear()}${x.discharge.projected ? '' : ', 11:00'}`;
    discharge = {
      label,
      projected: x.discharge.projected,
      relative,
      tone: x.discharge.inDays < 0 ? 'critical' : x.discharge.inDays <= 1 ? 'warning' : 'info',
    };
  }

  return {
    rn: `RN${String(bed.patient_id).padStart(7, '0')}`,
    vip: x.vip,
    payor: x.payor,
    coe: x.coe,
    expected_discharge: discharge,
    expected_discharge_label: discharge?.label ?? null,
    allergies: allergies.map((a) => ({ ...a, severity: a.severity ?? x.severities[a.name] ?? null })),
  };
}

// ------------------------------------------------------------------ labs

const CRITICAL_FLAGS = ['HH', 'LL', 'AA', 'C'];
const PRIORITY = { stat: ['STAT', 60], urgent: ['Urgent', 240], routine: ['Routine', 1440] };
const STATUS = { ordered: 'Ordered', collected: 'Specimen collected', in_progress: 'In progress', resulted: 'Resulted', cancelled: 'Cancelled' };
const stamp = (d) => (d ? `${dm(d)} ${hm(d)}` : null);

function reviewDue(lab) {
  if (lab.status === 'cancelled') return null;
  if (lab.reviewDue) return lab.reviewDue;
  return new Date((lab.resulted ?? lab.ordered).getTime() + PRIORITY[lab.priority][1] * 60000);
}

function reviewState(lab, now = new Date()) {
  const due = reviewDue(lab);
  if (lab.reviewed) return 'reviewed';
  if (!due) return 'none';
  if (lab.status !== 'resulted') return due < now ? 'result_late' : 'awaiting_result';
  if (due < now) return 'overdue';
  return due - now <= 3600000 ? 'due_soon' : 'due';
}

function labFlag(lab) {
  const flags = (lab.results ?? []).map(([, , , , f]) => f).filter((f) => f && f !== 'N');
  if (!flags.length) return null;
  return flags.some((f) => CRITICAL_FLAGS.includes(f)) ? 'critical' : 'abnormal';
}

export function labsBundle(state) {
  const now = new Date();
  const items = state.clinical.labs
    .map((lab) => {
      const st = reviewState(lab, now);
      const due = stamp(reviewDue(lab));
      return {
        id: lab.id,
        test_name: lab.name,
        test_code: lab.code,
        order_no: lab.order_no,
        category: lab.category,
        specimen: lab.specimen,
        priority: lab.priority,
        priority_label: PRIORITY[lab.priority][0],
        status: lab.status,
        status_label: STATUS[lab.status],
        ordered_label: stamp(lab.ordered),
        ordered_by: lab.orderedBy,
        collected_label: lab.status !== 'ordered' ? stamp(new Date(lab.ordered.getTime() + 15 * 60000)) : null,
        resulted_label: stamp(lab.resulted),
        results: (lab.results ?? []).map(([name, value, unit, range, flag]) => ({
          name, value, unit, range, flag,
          level: CRITICAL_FLAGS.includes(flag) ? 'critical' : flag ? 'abnormal' : null,
        })),
        flag: labFlag(lab),
        comment: lab.comment ?? null,
        review_state: st,
        review_label: {
          overdue: `Review overdue since ${due}`,
          due_soon: `Review due soon, by ${due}`,
          due: `Review by ${due}`,
          awaiting_result: 'Awaiting result',
          result_late: `Result late, expected by ${due}`,
          reviewed: `Reviewed ${stamp(lab.reviewed)}${lab.reviewedBy ? ` by ${lab.reviewedBy}` : ''}`,
        }[st] ?? null,
        can_review: lab.status === 'resulted' && !lab.reviewed,
        sample: false,
      };
    })
    // Unreviewed results first (overdue at the top), then the newest orders
    .sort((a, b) => rank(a) - rank(b));

  return {
    enabled: true,
    sample: false,
    items,
    counts: {
      awaiting_review: items.filter((i) => i.can_review).length,
      overdue: items.filter((i) => i.review_state === 'overdue').length,
      critical: items.filter((i) => i.can_review && i.flag === 'critical').length,
      pending: items.filter((i) => ['ordered', 'collected', 'in_progress'].includes(i.status)).length,
    },
  };
}

function rank(item) {
  if (item.review_state === 'overdue') return 0;
  return item.can_review ? 1 : 2;
}

// ---------------------------------------------------------------- oxygen

function deviceLabel(key) {
  return DEVICES.find((d) => d.key === key)?.label ?? key;
}

function settingsLabel(o) {
  return [o.flow ? `${o.flow} L/min` : null, o.fio2 ? `FiO₂ ${o.fio2}%` : null].filter(Boolean).join(', ') || null;
}

function describe(o) {
  return o.device === 'room_air' ? 'Room Air' : `${deviceLabel(o.device)}${settingsLabel(o) ? ` ${settingsLabel(o)}` : ''}`;
}

function durationLabel(minutes) {
  const m = Math.max(0, Math.round(minutes));
  if (m < 60) return `${m} min`;
  const h = Math.floor(m / 60);
  if (h < 24) return `${h} h${m % 60 ? ` ${m % 60} min` : ''}`;
  return `${Math.floor(h / 24)} d ${h % 24} h`;
}

const targetLabel = (t) => (t ? `${t[0]}–${t[1]}%` : null);

export function oxygenBundle(state) {
  const now = new Date();
  const live = state.clinical.oxygen.filter((o) => !o.voided).sort((a, b) => a.at - b.at);
  const current = live[live.length - 1] ?? null;
  const spo2 = state.clinical.spo2;
  const target = current?.target ?? null;
  const stale = !!(spo2 && current && spo2.at < current.at);
  const spo2State = spo2 && target && !stale
    ? spo2.value < target[0] ? 'below' : spo2.value > target[1] && current.device !== 'room_air' ? 'above' : 'in'
    : null;

  const history = [...state.clinical.oxygen]
    .sort((a, b) => b.at - a.at)
    .map((o) => {
      const idx = live.indexOf(o);
      const until = idx >= 0 && idx < live.length - 1 ? live[idx + 1].at : null;
      return {
        key: `change-${o.id}`,
        change_id: o.id,
        time_label: `${dm(o.at)} ${hm(o.at)}`,
        device: o.device,
        label: deviceLabel(o.device),
        short: o.device === 'room_air' ? 'RA' : `${ABBR[o.device]} ${o.flow ? `${o.flow}L` : `${o.fio2}%`}`,
        settings: o.device === 'room_air' ? null : settingsLabel(o),
        on_oxygen: o.device !== 'room_air',
        target_label: targetLabel(o.target),
        duration_label: o.voided ? null : `${durationLabel(((until ?? now) - o.at) / 60000)}${until ? '' : ' so far'}`,
        current: o === current,
        source: 'therapy',
        by: o.by,
        notes: o.notes ?? null,
        spo2: null,
        voided: o.voided,
        void_reason: o.voided ? o.voidReason : null,
      };
    });

  let since = null;
  for (let i = live.length - 1; i >= 0 && live[i].device !== 'room_air'; i--) since = live[i].at;

  return {
    current: current
      ? {
          device: current.device,
          label: deviceLabel(current.device),
          abbr: ABBR[current.device],
          short: history.find((h) => h.current)?.short,
          settings: current.device === 'room_air' ? null : settingsLabel(current),
          on_oxygen: current.device !== 'room_air',
          since_label: `${dm(current.at)} ${hm(current.at)}`,
          duration_label: durationLabel((now - current.at) / 60000),
          source: 'therapy',
          by: current.by,
          notes: current.notes ?? null,
        }
      : null,
    target: target
      ? { min: target[0], max: target[1], label: targetLabel(target), note: TARGETS.find((t) => t.min === target[0] && t.max === target[1])?.label ?? null }
      : null,
    latest_spo2: spo2 ? { value: spo2.value, time_label: `${dm(spo2.at)} ${hm(spo2.at)}`, on: stale ? null : history.find((h) => h.current)?.short ?? null, state: spo2State, stale } : null,
    alert: spo2State === 'below' ? 'critical' : spo2State === 'above' ? 'warning' : null,
    on_oxygen_since_label: since ? `${dm(since)} ${hm(since)}` : null,
    on_oxygen_duration_label: since ? durationLabel((now - since) / 60000) : null,
    history,
    options: {
      devices: DEVICES,
      targets: TARGETS,
      flow_min: 0.1,
      flow_max: 80,
      fio2_min: 21,
      fio2_max: 100,
      target_min: 70,
      target_max: 100,
    },
  };
}

// ------------------------------------------------------------ assessments

function bandFor(scale, score) {
  return scale.bands.find((b) => (b.min == null || score >= b.min) && (b.max == null || score <= b.max)) ?? null;
}

function scoreRow(scale, s) {
  const total = s.items ? s.items.reduce((n, v) => n + v, 0) : s.score;
  const band = s.band ? { label: s.band, tone: s.tone } : total != null ? bandFor(scale, total) : null;
  return {
    id: s.id,
    score: scale.kind === 'screen' ? null : total,
    band_label: band?.label ?? null,
    band_tone: band?.tone ?? null,
    breakdown: s.breakdown ?? null,
    notes: s.notes ?? null,
    time_label: when(s.at),
    by: s.by,
  };
}

export function assessmentsBundle(state) {
  const now = new Date();
  const scales = SCALES.map((scale) => {
    const history = state.clinical.scores.filter((s) => s.code === scale.code).sort((a, b) => b.at - a.at);
    let monitoring = null;
    if (scale.every) {
      const since = history[0]?.at ?? ago(2 * 1440);
      const elapsed = Math.max(0, Math.floor((now - since) / 60000));
      const st = elapsed >= scale.overdueAfter ? 'overdue' : elapsed >= scale.every ? 'due' : 'ok';
      const ago_ = `${durationLabel(elapsed)} ago`;
      const hist = history[0] ? `last scored ${ago_}` : `not scored since admission ${ago_}`;
      monitoring = {
        state: st,
        label: st === 'overdue' ? `Overdue · ${hist}` : st === 'due' ? `Due · ${hist}` : `Next due in ${durationLabel(scale.every - elapsed)}`,
        interval: durationLabel(scale.every),
        due_label: when(new Date(since.getTime() + scale.every * 60000)),
      };
    }
    return {
      id: scale.id,
      code: scale.code,
      name: scale.name,
      category: scale.category,
      purpose: scale.purpose,
      kind: scale.kind,
      can_score: scale.kind === 'scored' || scale.kind === 'score',
      items: scale.items,
      score_min: scale.score_min,
      score_max: scale.score_max,
      bands: scale.bands,
      note: scale.note,
      monitoring,
      latest: history[0] ? scoreRow(scale, history[0]) : null,
      history: history.slice(0, 5).map((s) => scoreRow(scale, s)),
    };
  });

  return {
    scales,
    counts: {
      overdue: scales.filter((s) => s.monitoring?.state === 'overdue').length,
      due: scales.filter((s) => s.monitoring?.state === 'due').length,
    },
  };
}

// ---------------------------------------------------------------- actions

export function clinicalActions(state, { ok, fail, by }) {
  return {
    reviewLab: (id) => {
      const lab = state.clinical.labs.find((l) => l.id === id);
      if (!lab || lab.status !== 'resulted' || lab.reviewed) return fail(`${lab?.name ?? 'That test'} has no result waiting for review.`);
      Object.assign(lab, { reviewed: new Date(), reviewedBy: by });
      return ok(`${lab.name} marked as reviewed.`);
    },

    changeOxygen: ({ oxygen_delivery, oxygen_flow_rate, fio2_percent, target_spo2_min, target_spo2_max, notes, minutes_ago }) => {
      if (!oxygen_delivery) return fail('Choose how the oxygen is given, or Room Air.');
      const roomAir = oxygen_delivery === 'room_air';
      if (!roomAir && !oxygen_flow_rate && !fio2_percent) return fail(`Enter the flow rate or the FiO₂ for ${deviceLabel(oxygen_delivery)}.`);
      if ((target_spo2_min == null) !== (target_spo2_max == null)) return fail('Enter both ends of the SpO₂ target, or neither.');
      if (target_spo2_min != null && target_spo2_max <= target_spo2_min) return fail('The top of the SpO₂ target must be above the bottom.');
      const change = {
        id: state.next(),
        device: oxygen_delivery,
        flow: roomAir ? null : oxygen_flow_rate ?? null,
        fio2: roomAir ? null : fio2_percent ?? null,
        target: target_spo2_min != null ? [target_spo2_min, target_spo2_max] : null,
        notes: notes || null,
        at: ago(minutes_ago || 0),
        by,
        voided: false,
      };
      state.clinical.oxygen.push(change);
      return ok(roomAir ? `Changed to room air at ${hm(change.at)}.` : `Oxygen changed to ${describe(change)} at ${hm(change.at)}.`);
    },

    voidOxygen: (id, { void_reason }) => {
      if (!void_reason) return fail('Give a reason for striking out this change.');
      const change = state.clinical.oxygen.find((o) => o.id === id);
      if (!change || change.voided) return fail('That change is already struck out.');
      Object.assign(change, { voided: true, voidReason: void_reason });
      return ok(`Struck out: ${describe(change)} from ${hm(change.at)}.`);
    },

    scoreAssessment: (scaleId, { item_scores, score, notes }) => {
      const scale = SCALES.find((s) => s.id === scaleId);
      if (!scale || !(scale.kind === 'scored' || scale.kind === 'score')) return fail(`${scale?.name ?? 'That scale'} is recorded on the ward dashboard.`);
      const row = { id: state.next(), code: scale.code, at: new Date(), by, notes: notes || null };
      if (scale.kind === 'scored') {
        if (!item_scores || item_scores.filter((v) => v != null).length !== scale.items.length) return fail('Score every item before saving.');
        row.items = item_scores;
      } else {
        if (score == null || score === '') return fail('Enter a score before saving.');
        if (score > scale.score_max) return fail(`${scale.name} scores at most ${scale.score_max}.`);
        row.score = Number(score);
      }
      state.clinical.scores.push(row);
      const total = row.items ? row.items.reduce((n, v) => n + v, 0) : row.score;
      const band = bandFor(scale, total);
      return ok(`${scale.name} scored ${total}${band ? ` - ${band.label}` : ''}.`);
    },
  };
}
