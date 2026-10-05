// Demo-login version of the patient chart: sample orders, I/O chart, doses,
// infusions, blood units, alerts and the nursing plan (mockNursingPlan.js)
// for the demo beds in mockData.js, answering exactly like the server (same
// shapes as NurseAppPatientBundle) and updating in memory when the nurse
// acts. Nothing is saved.

import { nurse as demoNurse } from './mockData';
import { DAYS, SHIFTS, ago, ahead, balance, dm, hm, ml, pad, when } from './demoTime';
import { carePlanActions, demoCarePlan, nursingPlanBundle } from './mockNursingPlan';
import { assessmentsBundle, clinicalActions, demoClinical, demoPatientExtras, labsBundle, oxygenBundle } from './mockClinical';

const INTAKE_TYPES = { oral: 'Oral', iv: 'IV fluid', tube_feed: 'Tube feed', blood: 'Blood product', iv_med: 'IV medication', other: 'Other intake' };
const OUTPUT_TYPES = { urine: 'Urine', drain: 'Drain', vomit: 'Vomit', ng_aspirate: 'NG aspirate', stool: 'Stool', other: 'Other output' };
const SUGGESTIONS = {
  intake: {
    oral: ['Water', 'Milk', 'Tea / coffee', 'Juice', 'Soup', 'Porridge'],
    iv: ['0.9% NaCl', 'Dextrose 5%', 'Dextrose saline', "Hartmann's"],
    tube_feed: ['NG feed', 'PEG feed', 'Water flush'],
    blood: ['Packed red cells', 'Platelets', 'Fresh frozen plasma'],
    iv_med: ['IV antibiotic', 'IV flush'],
    other: [],
  },
  output: {
    urine: ['Voided', 'Catheter', 'Bedpan / urinal'],
    drain: ['Chest drain', 'Wound drain', 'Redivac'],
    vomit: [],
    ng_aspirate: ['Free drainage', 'Aspirated'],
    stool: ['Loose stool', 'Stoma'],
    other: [],
  },
};
const EDEMA_GRADES = [
  { value: 0, short: 'None', label: 'No edema' },
  { value: 1, short: '1+', label: '2 mm pit, rebounds at once' },
  { value: 2, short: '2+', label: '4 mm pit, gone in 10 to 15 s' },
  { value: 3, short: '3+', label: '6 mm pit, lasts over 1 min' },
  { value: 4, short: '4+', label: '8 mm pit, lasts over 2 min' },
];
const EDEMA_SITES = { feet_ankles: 'Feet / ankles', legs: 'Lower legs', sacral: 'Sacrum', hands_arms: 'Hands / arms', face: 'Face / eyelids', generalised: 'Generalised' };
const SIGNS = { breathless: 'Shortness of breath', orthopnea: 'Breathless lying flat', crackles: 'Crackles on the chest', raised_jvp: 'Raised JVP / neck veins', ascites: 'Abdominal swelling', frothy_sputum: 'Pink frothy sputum' };
const DOSE_LABELS = { given: 'Given', held: 'Held', refused: 'Refused' };
const URGENCIES = { stat: 'STAT', urgent: 'Urgent', routine: 'Routine' };
const CONSULTANTS = [
  { id: 1, name: 'Dr. Rajan Krishnan' },
  { id: 2, name: 'Dr. Chen Hui' },
  { id: 3, name: 'Dr. Aminah Yusof' },
  { id: 4, name: 'Dr. Lim Kok Seng' },
];

// ------------------------------------------------------------ scenarios

// One per demo bed (mockData.assignedBeds), so the demo shows a spread:
// a sick patient, a settled one, a deteriorating one, one with nothing due.
const SCENARIOS = {
  5001: {
    allergies: [{ name: 'Penicillin', resolved: false }],
    diet: 'Diabetic, Low salt',
    isolation: 'Contact precautions',
    fallRisk: 'High',
    orders: [
      { instruction: 'Repeat blood cultures x2 and lactate', urgency: 'stat', minutesAgo: 25 },
      { instruction: 'Chest X-ray (portable) today', urgency: 'routine', minutesAgo: 140 },
    ],
    closedOrders: [{ instruction: 'Start IV Noradrenaline, titrate to MAP > 65', urgency: 'urgent', minutesAgo: 300, outcome: 'Started 06:10' }],
    meds: [
      { name: 'Ceftriaxone', dose: '2 g', route: 'IV', freq: 'OD (every 24 h)', dueIn: -40 },
      { name: 'Paracetamol', dose: '1 g', route: 'PO', freq: 'QID (every 6 h)', dueIn: 20 },
      { name: 'Morphine', dose: '2.5 mg', route: 'SC', freq: 'PRN (4 h minimum gap)', prn: true, highAlert: true },
    ],
    plan: { limit: 1500, urine: 30 },
    io: [
      ['intake', 'iv', "Hartmann's", 500, 170],
      ['output', 'urine', 'Catheter', 120, 150],
      ['intake', 'oral', 'Water', 200, 95],
      ['intake', 'iv_med', 'IV antibiotic', 100, 60],
      ['output', 'urine', 'Catheter', 90, 40],
    ],
    alerts: [{ type: 'ews', severity: 'urgent', message: 'EWS 5 on the last set of vitals', minutesAgo: 12, ews: 5 }],
    transfusion: null,
  },
  5002: {
    allergies: [],
    diet: 'Regular diet',
    orders: [{ instruction: 'Remove urinary catheter this evening', urgency: 'routine', minutesAgo: 90 }],
    closedOrders: [],
    meds: [
      { name: 'Omeprazole', dose: '20 mg', route: 'PO', freq: 'OD (every 24 h)', dueIn: 190 },
      { name: 'Enoxaparin', dose: '40 mg', route: 'SC', freq: 'OD (every 24 h)', dueIn: 300, highAlert: true },
    ],
    plan: null,
    io: [
      ['intake', 'oral', 'Tea / coffee', 250, 150],
      ['output', 'urine', 'Voided', 300, 110],
      ['intake', 'oral', 'Water', 300, 45],
    ],
    alerts: [],
    transfusion: null,
  },
  5003: {
    allergies: [{ name: 'Sulfonamides', resolved: false }, { name: 'Latex', resolved: true }],
    diet: 'Renal',
    nbm: true,
    fallRisk: 'Moderate',
    orders: [
      { instruction: 'ABG now, call if pH < 7.30', urgency: 'stat', minutesAgo: 8 },
      { instruction: 'Strict I/O, fluid limit 1 L/day', urgency: 'urgent', minutesAgo: 200 },
    ],
    closedOrders: [{ instruction: 'Crossmatch 2 units packed cells', urgency: 'urgent', minutesAgo: 400, outcome: 'Sent to blood bank' }],
    meds: [
      { name: 'Insulin Soluble (Actrapid)', dose: '6 units', route: 'SC', freq: 'TDS (every 8 h)', dueIn: -15, highAlert: true },
      { name: 'Furosemide', dose: '40 mg', route: 'IV', freq: 'BD (every 12 h)', dueIn: 75 },
    ],
    plan: { limit: 1000, urine: 30 },
    io: [
      ['intake', 'iv', '0.9% NaCl', 500, 200],
      ['intake', 'blood', 'Packed red cells', 280, 130],
      ['output', 'urine', 'Catheter', 60, 120],
      ['intake', 'iv_med', 'IV flush', 50, 70],
      ['intake', 'iv', 'Dextrose 5%', 250, 30],
      ['output', 'urine', 'Catheter', 40, 20],
    ],
    assessment: { edema: 'Edema 2+ (feet / ankles)', signs: ['Crackles on the chest'], concern: true, minutesAgo: 100, weight: 78.4 },
    alerts: [{ type: 'patient_request', severity: 'normal', message: 'Patient asks for help to sit up', minutesAgo: 4 }],
    // One unit running, the second waiting at check 2, one finished earlier
    transfusions: [
      { unit: 'PRC-24-11873', product: 'Packed Red Cells', unitGroup: 'O+', patientGroup: 'O+', crossmatch: 'XM-24-1187', expiresInDays: 20, volume: 280, minutes: 180, status: 'in_progress', checks: 4, startedMinutes: 55 },
      { unit: 'PRC-24-11874', product: 'Packed Red Cells', unitGroup: 'O-', patientGroup: 'O+', crossmatch: 'XM-24-1187', expiresInDays: 20, volume: 290, minutes: 150, status: 'pending', checks: 1 },
      { unit: 'FFP-24-0412', product: 'Fresh Frozen Plasma', unitGroup: 'O+', patientGroup: 'O+', crossmatch: 'XM-24-1150', expiresInDays: 5, volume: 250, minutes: 30, status: 'completed', checks: 4, startedMinutes: 1500, tookMinutes: 35 },
    ],
  },
  5004: {
    allergies: [],
    diet: 'Soft diet',
    orders: [],
    closedOrders: [{ instruction: 'Physiotherapy review before discharge', urgency: 'routine', minutesAgo: 600, outcome: 'Seen, cleared' }],
    meds: [{ name: 'Amlodipine', dose: '5 mg', route: 'PO', freq: 'OD (every 24 h)', dueIn: 600 }],
    plan: null,
    io: [['intake', 'oral', 'Porridge', 200, 120]],
    alerts: [],
    transfusion: null,
  },
};

// ---------------------------------------------------------------- state

function buildState(bed) {
  const s = SCENARIOS[bed.patient_id] ?? SCENARIOS[5004];
  let id = 1;
  const next = () => id++;
  const by = demoNurse.name;

  return {
    bed,
    next,
    s,
    orders: [
      ...s.orders.map((o) => ({ id: next(), ...o, at: ago(o.minutesAgo), status: 'open', assigned: by, mine: true, handovers: 0 })),
      ...s.closedOrders.map((o) => ({
        id: next(), ...o, at: ago(o.minutesAgo + 60), status: 'done', closedAt: ago(o.minutesAgo), closedBy: 'Sr. Farah', outcome: o.outcome,
      })),
    ],
    io: s.io.map(([direction, category, description, volume, minutesAgo]) => ({
      id: next(), direction, category, description, volume, at: ago(minutesAgo), by, voided: false,
    })),
    plan: s.plan ? { ...s.plan, notes: null, at: ago(600), by: 'Dr. on call' } : null,
    assessment: s.assessment ? { ...s.assessment, at: ago(s.assessment.minutesAgo), by } : null,
    meds: s.meds.map((m) => ({
      id: next(),
      ...m,
      status: 'active',
      nextDue: m.prn ? null : ahead(m.dueIn),
      lastGiven: m.prn ? ago(300) : null,
      doses: m.prn ? [{ status: 'given', at: ago(300), by: 'Sr. Farah', notes: 'Pain 6/10' }] : [],
    })),
    alerts: s.alerts.map((a) => ({ id: next(), ...a, at: ago(a.minutesAgo), status: 'pending' })),
    answered: [],
    units: tfUnitsFromScenario(s, next),
    carePlan: demoCarePlan(bed, next),
    clinical: demoClinical(bed, next),
  };
}

// --------------------------------------------------------------- bundle

function chartDayStart() {
  const d = new Date();
  const start = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 7, 0, 0);
  if (d < start) start.setDate(start.getDate() - 1);
  return start;
}

function ioBundle(state) {
  const start = chartDayStart();
  const end = new Date(start.getTime() + 24 * 3600000);
  const today = state.io.filter((e) => e.at >= start && e.at < end).sort((a, b) => a.at - b.at);
  const counted = today.filter((e) => !e.voided);
  const sum = (list, dir) => list.filter((e) => e.direction === dir).reduce((n, e) => n + e.volume, 0);
  const intake = sum(counted, 'intake');
  const output = sum(counted, 'output');
  const urine = counted.filter((e) => e.category === 'urine').reduce((n, e) => n + e.volume, 0);

  const byType = [];
  counted.forEach((e) => {
    const row = byType.find((r) => r.direction === e.direction && r.category === e.category);
    if (row) row.ml += e.volume;
    else byType.push({ direction: e.direction, category: e.category, label: (e.direction === 'intake' ? INTAKE_TYPES : OUTPUT_TYPES)[e.category], ml: e.volume });
  });
  byType.forEach((r) => (r.ml_label = ml(r.ml)));

  const now = new Date();
  const hoursObserved = Math.max(0, (now - start) / 3600000);
  const alerts = [];
  let limit = null;
  if (state.plan?.limit) {
    const cap = state.plan.limit;
    const st = intake > cap ? 'over' : intake * 100 >= cap * 80 ? 'near' : 'ok';
    limit = { limit: cap, taken: intake, percent: Math.floor((intake * 100) / cap), remaining: Math.max(0, cap - intake), over_by: Math.max(0, intake - cap), state: st };
    if (st === 'over') alerts.push({ level: 'critical', title: 'Over the intake limit', detail: `${ml(intake)} taken in against a limit of ${ml(cap)}, ${ml(intake - cap)} over.` });
    if (st === 'near') alerts.push({ level: 'warning', title: 'Near the intake limit', detail: `${ml(intake)} of ${ml(cap)} taken in. ${ml(cap - intake)} left until 07:00.` });
  }
  let urineStatus = null;
  if (state.plan?.urine) {
    const average = hoursObserved >= 1 ? Math.round(urine / hoursObserved) : null;
    urineStatus = { min: state.plan.urine, average, hours: Math.floor(hoursObserved), state: average != null && average < state.plan.urine ? 'low' : 'ok' };
    if (urineStatus.state === 'low') {
      alerts.push({ level: 'warning', title: 'Urine output below target', detail: `Averaging ${average} mL/h (${ml(urine)} in ${Math.floor(hoursObserved)} h) against ${state.plan.urine} mL/h.` });
    }
  }
  if (state.assessment?.concern) {
    alerts.push({ level: 'warning', title: 'Signs of fluid overload', detail: `${state.assessment.edema}, ${state.assessment.signs.join(', ')}.` });
  }
  alerts.sort((a, b) => (a.level === 'critical' ? -1 : 1) - (b.level === 'critical' ? -1 : 1));

  let running = 0;
  const shifts = SHIFTS.map((sh) => {
    const from = new Date(start);
    from.setHours(sh.from, 0, 0, 0);
    const to = new Date(start);
    to.setHours(sh.to % 24, 0, 0, 0);
    if (sh.to >= 24) to.setDate(to.getDate() + 1);
    const rows = today.filter((e) => e.at >= from && e.at < to);
    const inS = sum(rows.filter((e) => !e.voided), 'intake');
    const outS = sum(rows.filter((e) => !e.voided), 'output');
    return {
      code: sh.code,
      name: sh.name,
      time: `${pad(sh.from)}:00 - ${pad(sh.to % 24)}:00`,
      current: now >= from && now < to,
      intake_label: ml(inS),
      output_label: ml(outS),
      balance_label: balance(inS - outS),
      entries: rows.map((e) => {
        if (!e.voided) running += e.direction === 'intake' ? e.volume : -e.volume;
        const label = `${(e.direction === 'intake' ? INTAKE_TYPES : OUTPUT_TYPES)[e.category]}${e.description ? ` - ${e.description}` : ''}`;
        return {
          id: e.id,
          time_label: hm(e.at),
          direction: e.direction,
          category: e.category,
          type_label: label,
          description: e.description,
          label,
          volume: e.volume,
          volume_label: ml(e.volume),
          running_label: e.voided ? null : balance(running),
          by: e.by,
          auto_label: e.auto ?? null,
          voided: e.voided,
          void_reason: e.voidReason ?? null,
          voided_by: e.voided ? demoNurse.name : null,
        };
      }),
    };
  });

  const dayLabel = (d) => `${DAYS[d.getDay()]} ${dm(d)}`;
  const yesterday = new Date(start.getTime() - 24 * 3600000);
  const dayBefore = new Date(start.getTime() - 48 * 3600000);

  return {
    day: { key: start.toISOString().slice(0, 10), label: dayLabel(start), range: `${dm(start)} 07:00 - ${dm(end)} 07:00`, is_current: true, previous: null, next: null },
    totals: { intake, output, balance: intake - output, urine, intake_label: ml(intake), output_label: ml(output), balance_label: balance(intake - output), urine_label: ml(urine), by_type: byType },
    status: { alerts, limit, urine: urineStatus },
    plan: state.plan
      ? {
          summary: [state.plan.limit ? `Intake up to ${ml(state.plan.limit)} per day` : null, state.plan.urine ? `urine at least ${state.plan.urine} mL/h` : null].filter(Boolean).join(', ') || 'No limits',
          intake_limit_ml: state.plan.limit ?? null,
          urine_min_ml_per_hour: state.plan.urine ?? null,
          notes: state.plan.notes,
          set_label: `${when(state.plan.at)} by ${state.plan.by}`,
        }
      : null,
    shifts: shifts.filter((sh) => sh.entries.length || sh.current),
    latest_assessment: state.assessment
      ? { time_label: when(state.assessment.at), edema: state.assessment.edema, signs: state.assessment.signs, weight_kg: state.assessment.weight ?? null, urgent: !!state.assessment.urgent, concern: !!state.assessment.concern, notes: null, by: state.assessment.by }
      : null,
    weight: state.assessment?.weight ? { kg: state.assessment.weight, time_label: when(state.assessment.at), change: 0.6, gain: null } : null,
    days: [
      { key: 'today', label: dayLabel(start), is_current: true, entries: today.length, intake_label: ml(intake), output_label: ml(output), balance: intake - output, balance_label: balance(intake - output), over: !!(limit && limit.state === 'over') },
      { key: 'y1', label: dayLabel(yesterday), is_current: false, entries: 11, intake_label: ml(1850), output_label: ml(1420), balance: 430, balance_label: balance(430), over: false },
      { key: 'y2', label: dayLabel(dayBefore), is_current: false, entries: 9, intake_label: ml(1600), output_label: ml(1550), balance: 50, balance_label: balance(50), over: false },
    ],
    stay_balance_label: balance(480 + intake - output),
    options: {
      intake_types: INTAKE_TYPES,
      output_types: OUTPUT_TYPES,
      suggestions: SUGGESTIONS,
      quick_volumes: [50, 100, 150, 200, 250, 300, 500, 1000],
      volume_max: 5000,
      limit_presets: [800, 1000, 1200, 1500, 2000],
      urine_presets: [20, 30, 40, 50],
      limit_min: 100,
      limit_max: 10000,
      urine_min: 5,
      urine_max: 500,
      edema_grades: EDEMA_GRADES,
      edema_sites: EDEMA_SITES,
      signs: SIGNS,
    },
  };
}

function medsBundle(state) {
  const now = new Date();
  const map = (m) => {
    let dueState = m.prn ? 'prn' : 'scheduled';
    let dueLabel = m.prn ? 'PRN - give when needed' : `Next dose ${m.nextDue ? when(m.nextDue) : '-'}`;
    if (!m.prn && m.nextDue) {
      const mins = Math.round((m.nextDue - now) / 60000);
      if (mins < 0) {
        dueState = 'overdue';
        dueLabel = `Overdue by ${Math.abs(mins) >= 60 ? `${Math.floor(Math.abs(mins) / 60)} h ${Math.abs(mins) % 60} min` : `${Math.abs(mins)} min`}`;
      } else if (mins <= 30) {
        dueState = 'due_soon';
        dueLabel = `Due in ${mins} min`;
      } else {
        dueLabel = `Next dose ${when(m.nextDue)}`;
      }
    }
    if (m.status !== 'active') {
      dueState = m.status;
      dueLabel = m.status === 'stopped' ? 'Stopped' : 'Completed';
    }
    return {
      id: m.id,
      name: m.name,
      summary: `${m.dose} ${m.route}`,
      dose_label: m.dose,
      route_label: m.route,
      frequency_label: m.freq,
      is_high_alert: !!m.highAlert,
      instructions: m.prn ? 'For moderate to severe pain' : null,
      status: m.status,
      due_state: dueState,
      due_label: dueLabel,
      next_due_label: m.nextDue ? when(m.nextDue) : null,
      next_allowed_label: m.prn && m.lastGiven && now - m.lastGiven < 4 * 3600000 ? when(new Date(m.lastGiven.getTime() + 4 * 3600000)) : null,
      last_given_label: m.lastGiven ? when(m.lastGiven) : null,
      stopped_label: null,
      stop_reason: null,
      recent_doses: [...m.doses].sort((a, b) => b.at - a.at).slice(0, 4).map((d) => ({ status: d.status, status_label: DOSE_LABELS[d.status], time_label: when(d.at), by: d.by, notes: d.notes })),
    };
  };
  const active = state.meds.filter((m) => m.status === 'active').map(map);
  const rank = { overdue: 0, due_soon: 1, scheduled: 2, prn: 3 };
  active.sort((a, b) => (rank[a.due_state] ?? 9) - (rank[b.due_state] ?? 9));
  return {
    active,
    closed: state.meds.filter((m) => m.status !== 'active').map(map),
    counts: { active: active.length, overdue: active.filter((m) => m.due_state === 'overdue').length, due_soon: active.filter((m) => m.due_state === 'due_soon').length },
    statuses: DOSE_LABELS,
  };
}

function infusionsBundle(state) {
  const statusLabel = { alarming: 'Alarm', running: 'Running', paused: 'Paused', stopped: 'Stopped', pending: 'Pending', completed: 'Completed' };
  const active = (state.bed.infusions ?? []).map((inf, i) => {
    const total = inf.remaining_volume != null ? Math.round(inf.remaining_volume * (i % 2 ? 2.2 : 1.6)) : null;
    const infused = total != null ? Math.round((total - inf.remaining_volume) * 10) / 10 : null;
    return {
      key: `demo-${inf.id}`,
      medication: inf.medication_name,
      concentration: null,
      pump: inf.device_id,
      status: inf.status,
      status_label: statusLabel[inf.status] ?? inf.status,
      is_warning: !!inf.is_warning,
      alarm_message: inf.status === 'alarming' ? 'Occlusion downstream' : null,
      alarm_priority: inf.status === 'alarming' ? 'High' : null,
      flow_rate: inf.flow_rate,
      dose_rate: null,
      infused_volume: infused,
      total_volume: total,
      remaining_volume: inf.remaining_volume,
      progress_percent: total ? Math.round((infused / total) * 100) : null,
      remaining_time: inf.formatted_remaining_time,
      ends_label: inf.status === 'running' ? hm(ahead(45 + i * 30)) : null,
      started_label: when(ago(120 + i * 40)),
      updated_label: inf.last_updated_label,
      completed_label: null,
      live: true,
    };
  });
  return {
    source: 'engine',
    error: null,
    active,
    completed: [],
  };
}

// ---------------------------------------------------- blood transfusion
// A port of BloodTransfusion's rules, so the demo refuses what the server
// refuses: checks in order, undo only the last, no start while anything
// critical is open.

const PRODUCT_TYPES = ['Packed Red Cells', 'Whole Blood', 'Platelets', 'Fresh Frozen Plasma', 'Cryoprecipitate'];
const PRODUCT_PRESETS = {
  'Packed Red Cells': { volume_ml: 300, minutes: 120, min_minutes: 60, max_minutes: 240, note: 'Usually given over 1.5 to 3 hours, and always within 4.' },
  'Whole Blood': { volume_ml: 450, minutes: 180, min_minutes: 90, max_minutes: 240, note: 'Larger volume, usually 2 to 4 hours.' },
  Platelets: { volume_ml: 250, minutes: 30, min_minutes: 15, max_minutes: 60, note: 'Given quickly, usually about 30 minutes.' },
  'Fresh Frozen Plasma': { volume_ml: 250, minutes: 30, min_minutes: 15, max_minutes: 60, note: 'Given quickly once thawed, usually about 30 minutes.' },
  Cryoprecipitate: { volume_ml: 200, minutes: 30, min_minutes: 15, max_minutes: 60, note: 'Given quickly, usually about 30 minutes.' },
};
const BLOOD_GROUPS = ['O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'];
const RED_CELLS = ['Packed Red Cells', 'Whole Blood'];
const COMPATIBLE = {
  'O-': ['O-'], 'O+': ['O-', 'O+'], 'A-': ['O-', 'A-'], 'A+': ['O-', 'O+', 'A-', 'A+'],
  'B-': ['O-', 'B-'], 'B+': ['O-', 'O+', 'B-', 'B+'], 'AB-': ['O-', 'A-', 'B-', 'AB-'], 'AB+': BLOOD_GROUPS,
};
const CHECK_KEYS = ['check_crossmatch', 'check_product', 'check_expiry', 'check_identity'];
const MAX_RUNNING_MINUTES = 240;
const T_STATUS_LABELS = { pending: 'Registered', in_progress: 'Running', completed: 'Completed', stopped: 'Stopped' };
const fullDate = (d) => `${dm(d)} ${d.getFullYear()} ${hm(d)}`;

function tfUnitsFromScenario(s, next) {
  return (s.transfusions ?? []).map((t) => {
    const checks = Object.fromEntries(CHECK_KEYS.map((k, i) => [k, i < t.checks]));
    const started = t.startedMinutes != null ? ago(t.startedMinutes) : null;
    const expires = new Date();
    expires.setDate(expires.getDate() + (t.expiresInDays ?? 10));
    expires.setHours(23, 59, 0, 0);
    return {
      id: next(),
      unit_number: t.unit,
      product: t.product,
      unit_group: t.unitGroup ?? null,
      patient_group: t.patientGroup ?? null,
      crossmatch: t.crossmatch ?? null,
      expires,
      volume: t.volume,
      minutes: t.minutes,
      notes: null,
      status: t.status,
      checks,
      checked_at: t.checks ? ago((t.startedMinutes ?? 5) + 3) : null,
      checked_by: t.checks ? demoNurse.name : null,
      started_at: started,
      completed_at: t.status === 'completed' || t.status === 'stopped' ? new Date(started.getTime() + (t.tookMinutes ?? 30) * 60000) : null,
      stop_reason: null,
      created_at: ago((t.startedMinutes ?? 10) + 15),
      created_by: 'Sr. Farah Idris',
    };
  });
}

const tfFinished = (u) => u.status === 'completed' || u.status === 'stopped';

function tfCompatibility(u) {
  if (!u.unit_group || !u.patient_group || !RED_CELLS.includes(u.product)) return 'manual';
  return (COMPATIBLE[u.patient_group] ?? []).includes(u.unit_group) ? 'compatible' : 'incompatible';
}

function tfGroups(u) {
  return u.unit_group && u.patient_group ? `Unit ${u.unit_group} to patient ${u.patient_group}` : 'Groups not both recorded';
}

function tfChecklist(u) {
  const expired = u.expires && u.expires < new Date();
  return [
    { key: 'check_crossmatch', label: 'Crossmatch confirmed', detail: u.crossmatch ? `Reference ${u.crossmatch}` : 'No crossmatch reference recorded', problem: u.crossmatch ? null : 'No crossmatch reference on this unit.' },
    { key: 'check_product', label: 'Product type verified', detail: u.product, problem: null },
    { key: 'check_expiry', label: 'Expiry checked', detail: u.expires ? `Expires ${fullDate(u.expires)}` : 'No expiry recorded', problem: expired ? 'This unit has passed its expiry.' : null },
    { key: 'check_identity', label: 'Patient and unit match', detail: tfGroups(u), problem: tfCompatibility(u) === 'incompatible' ? 'Unit group is not compatible with the patient group.' : null },
  ].map((c) => ({ ...c, done: !!u.checks[c.key] }));
}

function tfSteps(u) {
  const list = tfChecklist(u);
  const lastDone = list.reduce((last, c, i) => (c.done ? i : last), -1);
  return list.map((c, i) => ({
    ...c,
    number: i + 1,
    unlocked: list.slice(0, i).every((p) => p.done),
    is_next: list.slice(0, i).every((p) => p.done) && !c.done,
    can_undo: c.done && i === lastDone,
  }));
}

function tfExceptions(u) {
  const now = new Date();
  const out = [];
  const checks = tfChecklist(u);
  const done = checks.filter((c) => c.done).length;
  if (tfCompatibility(u) === 'incompatible') out.push({ level: 'critical', title: 'Blood group mismatch', detail: `${tfGroups(u)} is not a compatible red cell pairing. Do not proceed.` });
  if (u.expires && u.expires < now) out.push({ level: 'critical', title: 'Unit past expiry', detail: `Expired ${fullDate(u.expires)}.` });
  if (u.status === 'in_progress' && u.started_at && (now - u.started_at) / 60000 > MAX_RUNNING_MINUTES) {
    out.push({ level: 'critical', title: 'Running beyond four hours', detail: `Started ${dm(u.started_at)} ${hm(u.started_at)}. A unit should be completed within four hours of starting.` });
  }
  if (!tfFinished(u) && done < 4) {
    out.push({
      level: u.status === 'in_progress' ? 'critical' : 'warning',
      title: u.status === 'in_progress' ? 'Running with incomplete checks' : 'Checks incomplete',
      detail: `Outstanding: ${checks.filter((c) => !c.done).map((c) => c.label).join(', ')}.`,
    });
  }
  const end = u.started_at && u.minutes ? new Date(u.started_at.getTime() + u.minutes * 60000) : null;
  if (u.status === 'in_progress' && end && now > end) {
    out.push({ level: 'warning', title: 'Past predicted end time', detail: `Expected to finish ${dm(end)} ${hm(end)}, ${Math.round((now - end) / 60000)} min ago.` });
  }
  const preset = PRODUCT_PRESETS[u.product];
  if (!tfFinished(u) && preset && u.minutes && (u.minutes < preset.min_minutes || u.minutes > preset.max_minutes)) {
    out.push({ level: 'warning', title: 'Duration outside the usual window', detail: `${u.minutes} min planned for ${u.product}, which is normally given over ${preset.min_minutes} to ${preset.max_minutes} min.` });
  }
  if (tfCompatibility(u) === 'manual' && !tfFinished(u)) {
    out.push({
      level: 'info',
      title: 'Group match needs manual confirmation',
      detail: RED_CELLS.includes(u.product)
        ? 'Both blood groups are not recorded, so the app cannot check the pairing.'
        : `${u.product} does not follow the red cell rule. Confirm compatibility at the bedside.`,
    });
  }
  return out;
}

const tfCanStart = (u) => u.status === 'pending' && CHECK_KEYS.every((k) => u.checks[k]) && !tfExceptions(u).some((e) => e.level === 'critical');

function tfView(u) {
  const steps = tfSteps(u);
  const done = steps.filter((s) => s.done).length;
  const end = u.started_at && u.minutes ? new Date(u.started_at.getTime() + u.minutes * 60000) : null;
  const limit = u.started_at ? new Date(u.started_at.getTime() + MAX_RUNNING_MINUTES * 60000) : null;
  const canStart = tfCanStart(u);
  return {
    id: u.id,
    unit_number: u.unit_number,
    product: u.product,
    unit_group: u.unit_group,
    patient_group: u.patient_group,
    groups: tfGroups(u),
    compatibility: tfCompatibility(u),
    crossmatch_reference: u.crossmatch,
    expires_label: u.expires ? fullDate(u.expires) : null,
    expired: !!(u.expires && u.expires < new Date()),
    volume_ml: u.volume,
    prescribed_minutes: u.minutes,
    rate: u.volume && u.minutes ? Math.round((u.volume / (u.minutes / 60)) * 10) / 10 : null,
    notes: u.notes,
    status: u.status,
    status_label: T_STATUS_LABELS[u.status],
    registered_label: when(u.created_at),
    registered_by: u.created_by,
    steps: steps.map(({ key, number, label, detail, done: isDone, problem, is_next, can_undo }) => ({ key, number, label, detail, done: isDone, problem, is_next, can_undo })),
    steps_done: done,
    checks_complete: done === 4,
    can_start: canStart,
    start_note: u.status === 'pending'
      ? canStart ? 'All four steps confirmed. Ready to start.' : done < 4 ? `${4 - done} ${4 - done === 1 ? 'step' : 'steps'} left before this unit can start.` : 'Resolve the flagged problems before starting.'
      : null,
    last_action_label: u.checked_at ? `${hm(u.checked_at)}${u.checked_by ? ` by ${u.checked_by}` : ''}` : null,
    started_at: u.started_at ? u.started_at.toISOString() : null,
    started_label: u.started_at ? when(u.started_at) : null,
    end_at: end ? end.toISOString() : null,
    end_label: end ? `${dm(end)} ${hm(end)}` : null,
    limit_at: limit ? limit.toISOString() : null,
    limit_label: limit ? hm(limit) : null,
    completed_label: u.completed_at ? when(u.completed_at) : null,
    took_minutes: tfFinished(u) && u.started_at ? Math.round((u.completed_at - u.started_at) / 60000) : null,
    stop_reason: u.stop_reason,
  };
}

function transfusionsBundle(state) {
  const order = { critical: 0, warning: 1, info: 2 };
  const units = [...state.units].sort((a, b) => b.id - a.id);
  return {
    running: units.filter((u) => u.status === 'in_progress').map(tfView),
    pending: units.filter((u) => u.status === 'pending').map(tfView),
    finished: units.filter(tfFinished).sort((a, b) => b.completed_at - a.completed_at).map(tfView),
    exceptions: units
      .filter((u) => !tfFinished(u))
      .flatMap((u) => tfExceptions(u).map((e) => ({ ...e, unit: u.unit_number, unit_id: u.id })))
      .sort((a, b) => (order[a.level] ?? 9) - (order[b.level] ?? 9)),
    options: {
      product_types: PRODUCT_TYPES,
      presets: PRODUCT_PRESETS,
      blood_groups: BLOOD_GROUPS,
      volume_step: 25,
      volume_min: 50,
      volume_max: 2000,
      minutes_step: 15,
      minutes_min: 15,
      minutes_max: MAX_RUNNING_MINUTES,
      patient_blood_group: units.map((u) => u.patient_group).find(Boolean) ?? null,
    },
  };
}

function toBundle(state) {
  const { bed, s } = state;
  const open = state.orders.filter((o) => o.status === 'open').sort((a, b) => a.at - b.at);
  const closed = state.orders.filter((o) => o.status !== 'open').sort((a, b) => b.closedAt - a.closedAt);
  const io = ioBundle(state);
  const medications = medsBundle(state);
  const infusions = infusionsBundle(state);
  const transfusions = transfusionsBundle(state);
  const v = bed.vitals ?? {};
  const alertMap = (a) => ({
    id: a.id,
    type: a.type,
    type_label: { patient_request: 'Patient call', ews: 'EWS alert', infusion: 'Infusion alert' }[a.type] ?? 'Alert',
    category: null,
    severity: a.severity,
    severity_label: a.severity === 'urgent' ? 'Urgent' : a.severity === 'warning' ? 'Needs Attention' : 'Normal',
    message: a.message,
    ews_score: a.ews ?? null,
    time_label: when(a.at),
    minutes_ago: Math.max(0, Math.round((Date.now() - a.at) / 60000)),
    responded_label: a.respondedAt ? when(a.respondedAt) : null,
    responded_by: a.respondedAt ? demoNurse.name : null,
  });

  const bundle = {
    patient: {
      id: bed.patient_id,
      name: bed.patient_name,
      alias_name: null,
      mrn: bed.mrn,
      gender: bed.gender,
      age: bed.age,
      bed: bed.number,
      ward_id: 1,
      ward: 'Ward 3A - Medical',
      status: bed.is_pending_discharge ? 'pending_discharge' : 'admitted',
      status_label: bed.is_pending_discharge ? 'Pending Discharge' : 'Admitted',
      admitted_label: `${dm(ago((bed.days ?? 1) * 1440 + (bed.hours ?? 0) * 60))} 09:15`,
      stay_label: `${bed.days ?? 0}d ${bed.hours ?? 0}h`,
      consultant: bed.consultant,
      anaesthetist: null,
      primary_nurse: demoNurse.name,
      // rn, vip, payor, coe, expected discharge, allergies with severity
      ...demoPatientExtras(bed, s.allergies ?? []),
      nbm: !!s.nbm,
      diet: s.diet ?? null,
      diet_orders: null,
      feeding: null,
      fall_risk: s.fallRisk ?? null,
      isolation: s.isolation ?? null,
      nursing_level: 'Level 2',
      hgt: 'TDS (Three Times Daily)',
    },
    vitals: {
      latest: bed.vitals
        ? {
            id: 1,
            time_label: v.recorded_at_label,
            bp: v.systolic_bp ? `${v.systolic_bp}/${v.diastolic_bp}` : null,
            pulse: v.pulse_rate,
            temperature: v.temperature != null ? Number(v.temperature).toFixed(1) : null,
            spo2: v.spo2,
            respiratory_rate: v.respiratory_rate,
            oxygen: bed.ews >= 5 ? 'NP 2L' : null,
            ews: bed.ews_has_vitals ? bed.ews : null,
            by: demoNurse.name,
          }
        : null,
      recent: [],
      hgt: bed.last_hgt ? { value: bed.last_hgt.value, time_label: '08:00', tone: 'default' } : null,
    },
    orders: {
      open: open.map((o) => ({
        id: o.id,
        instruction: o.instruction,
        urgency: o.urgency,
        urgency_label: URGENCIES[o.urgency],
        ordered_label: when(o.at),
        ordered_at: o.at.toISOString(),
        consultant: o.consultant ?? bed.consultant,
        assigned_nurse: o.assigned,
        is_mine: o.mine,
        slot_label: o.mine ? 'AM' : 'PM',
        entered_by: o.enteredBy ?? null,
        handovers: o.handovers,
        last_handover_label: o.handedOverAt ? `${when(o.handedOverAt)} by ${demoNurse.name}` : null,
      })),
      closed: closed.map((o) => ({
        id: o.id,
        instruction: o.instruction,
        urgency: o.urgency,
        urgency_label: URGENCIES[o.urgency],
        status: o.status,
        status_label: o.status === 'done' ? 'Done' : 'Cancelled',
        closed_label: when(o.closedAt),
        closed_by: o.closedBy,
        outcome_note: o.outcome ?? null,
        consultant: bed.consultant,
      })),
      slots: {
        current: { label: 'AM', name: 'Morning', time: '07:00 - 14:00', nurse: demoNurse.name },
        next: { label: 'PM', name: 'Afternoon', time: '14:00 - 23:00', nurse: 'Sr. Farah Idris' },
      },
      consultants: CONSULTANTS.map((c) => ({ ...c, is_patients: c.name === bed.consultant })).sort((a, b) => (b.is_patients ? 1 : 0) - (a.is_patients ? 1 : 0)),
      default_consultant_id: CONSULTANTS.find((c) => c.name === bed.consultant)?.id ?? null,
      urgencies: URGENCIES,
    },
    io,
    medications,
    infusions,
    transfusions,
    alerts: {
      pending: state.alerts.filter((a) => a.status === 'pending').map(alertMap),
      recent: state.answered.slice(0, 5).map(alertMap),
    },
    labs: labsBundle(state),
    oxygen: oxygenBundle(state),
    assessments: assessmentsBundle(state),
    generated_at: new Date().toISOString(),
    generated_label: hm(new Date()),
  };

  bundle.nursing_plan = nursingPlanBundle(state, {
    orders: bundle.orders,
    io,
    medications,
    infusions,
    transfusions,
    vitals: bundle.vitals,
  });

  bundle.badges = {
    orders_open: bundle.orders.open.length,
    orders_stat: bundle.orders.open.filter((o) => o.urgency === 'stat').length,
    orders_mine: bundle.orders.open.filter((o) => o.is_mine).length,
    meds_overdue: medications.counts.overdue,
    meds_due_soon: medications.counts.due_soon,
    io_level: io.status.alerts[0]?.level ?? null,
    infusion_alarms: infusions.active.filter((i) => i.status === 'alarming' || i.is_warning).length,
    alerts_pending: bundle.alerts.pending.length,
    transfusions_running: transfusions.running.length,
    transfusions_pending: transfusions.pending.length,
    transfusion_critical: transfusions.exceptions.filter((e) => e.level === 'critical').length,
    care_plan_due: bundle.nursing_plan.care_plan.due_evaluations,
    shift_overdue: bundle.nursing_plan.shift.counts.overdue,
    labs_review: bundle.labs.counts.awaiting_review,
    labs_overdue: bundle.labs.counts.overdue,
    labs_critical: bundle.labs.counts.critical,
    oxygen_level: bundle.oxygen.alert,
    assess_overdue: bundle.assessments.counts.overdue,
    assess_due: bundle.assessments.counts.due,
  };

  return bundle;
}

// --------------------------------------------------------------- client

// One in-memory chart per demo patient, kept for the whole demo session so
// what the nurse records is still there when the chart is reopened.
const stores = new Map();

function demoState(bed) {
  if (!stores.has(bed.patient_id)) stores.set(bed.patient_id, buildState(bed));
  return stores.get(bed.patient_id);
}

export function createDemoPatientClient(bed) {
  const state = demoState(bed);
  const by = demoNurse.name;

  const wait = (ms = 250) => new Promise((resolve) => setTimeout(resolve, ms));
  const ok = async (message) => {
    await wait();
    return { success: true, message, patient: toBundle(state) };
  };
  const fail = async (message) => {
    await wait(150);
    throw new Error(message);
  };
  const find = (list, id) => list.find((x) => x.id === id);

  return {
    load: async () => {
      await wait(300);
      return { success: true, patient: toBundle(state) };
    },

    ...carePlanActions(state, { ok, fail, by }),
    ...clinicalActions(state, { ok, fail, by }),

    addOrder: ({ instruction, urgency, consultant_id }) => {
      if (!instruction) return fail('Write down what the consultant ordered.');
      const consultant = CONSULTANTS.find((c) => c.id === consultant_id)?.name;
      state.orders.push({ id: state.next(), instruction, urgency, at: new Date(), status: 'open', assigned: by, mine: true, handovers: 0, consultant, enteredBy: by });
      return ok(`Order added for ${by}.`);
    },
    completeOrder: (id, { outcome_note }) => {
      const o = find(state.orders, id);
      if (!o || o.status !== 'open') return fail('That order is already closed.');
      Object.assign(o, { status: 'done', closedAt: new Date(), closedBy: by, outcome: outcome_note });
      return ok('Order marked done.');
    },
    cancelOrder: (id, { outcome_note }) => {
      if (!outcome_note) return fail('Give a reason for cancelling the order.');
      const o = find(state.orders, id);
      if (!o || o.status !== 'open') return fail('That order is already closed.');
      Object.assign(o, { status: 'cancelled', closedAt: new Date(), closedBy: by, outcome: outcome_note });
      return ok('Order cancelled.');
    },
    handoverOrders: ({ to }) => {
      const open = state.orders.filter((o) => o.status === 'open' && (to === 'next' ? o.mine : !o.mine));
      if (!open.length) return fail(`Those orders are already with the ${to === 'next' ? 'PM' : 'AM'} shift.`);
      open.forEach((o) => Object.assign(o, { mine: to !== 'next', assigned: to === 'next' ? 'Sr. Farah Idris' : by, handovers: o.handovers + 1, handedOverAt: new Date() }));
      return ok(`${open.length} ${open.length === 1 ? 'order' : 'orders'} passed to ${to === 'next' ? 'Sr. Farah Idris (PM)' : `${by} (AM)`}.`);
    },

    addIoEntry: ({ direction, category, volume_ml, description, minutes_ago }) => {
      if (!volume_ml) return fail('Enter the volume in mL.');
      const at = ago(minutes_ago || 0);
      state.io.push({ id: state.next(), direction, category, description, volume: volume_ml, at, by, voided: false });
      const label = `${(direction === 'intake' ? INTAKE_TYPES : OUTPUT_TYPES)[category]}${description ? ` - ${description}` : ''}`;
      return ok(`${direction === 'intake' ? 'Intake' : 'Output'} recorded: ${label}, ${ml(volume_ml)} at ${hm(at)}.`);
    },
    voidIoEntry: (id, { void_reason }) => {
      const e = find(state.io, id);
      if (!void_reason) return fail('Give a reason for striking out this entry.');
      if (!e || e.voided) return fail('That entry is already struck out.');
      Object.assign(e, { voided: true, voidReason: void_reason });
      return ok(`Struck out: ${ml(e.volume)} at ${hm(e.at)}.`);
    },
    saveIoPlan: ({ intake_limit_ml, urine_min_ml_per_hour, notes }) => {
      state.plan = intake_limit_ml || urine_min_ml_per_hour ? { limit: intake_limit_ml, urine: urine_min_ml_per_hour, notes, at: new Date(), by } : null;
      return ok(state.plan ? 'Fluid plan set.' : 'Fluid limits lifted.');
    },
    addIoAssessment: ({ edema_grade, edema_sites, signs, weight_kg }) => {
      const grade = EDEMA_GRADES.find((g) => g.value === edema_grade);
      const sites = (edema_sites ?? []).map((k) => EDEMA_SITES[k].toLowerCase());
      state.assessment = {
        edema: edema_grade > 0 ? `Edema ${grade.short}${sites.length ? ` (${sites.join(', ')})` : ''}` : 'No edema',
        signs: (signs ?? []).map((k) => SIGNS[k]),
        concern: edema_grade > 0 || (signs ?? []).length > 0,
        urgent: (signs ?? []).includes('frothy_sputum'),
        weight: weight_kg,
        at: new Date(),
        by,
      };
      return ok('Assessment recorded.');
    },

    recordDose: (id, { status, notes, minutes_ago }) => {
      const m = find(state.meds, id);
      if (status !== 'given' && !notes) return fail('Give a reason when a dose is held or refused.');
      if (!m || m.status !== 'active') return fail('That medication is no longer active.');
      const at = ago(minutes_ago || 0);
      m.doses.push({ status, at, by, notes });
      if (status === 'given') m.lastGiven = at;
      if (!m.prn) {
        const hours = /every (\d+) h/.exec(m.freq)?.[1];
        m.nextDue = new Date(at.getTime() + (Number(hours) || 24) * 3600000);
      }
      return ok(`${m.name}: dose ${status} at ${hm(at)}.`);
    },

    addTransfusion: (body) => {
      if (!body.unit_number) return fail('Enter the unit number from the bag.');
      if (!PRODUCT_TYPES.includes(body.product_type)) return fail('Choose the product.');
      let expires = null;
      if (body.unit_expires_at) {
        const m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})$/.exec(body.unit_expires_at);
        if (!m) return fail('Enter the expiry as a date and time, e.g. 2026-09-30 23:59.');
        expires = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]));
      }
      state.units.push({
        id: state.next(),
        unit_number: body.unit_number,
        product: body.product_type,
        unit_group: body.unit_blood_group,
        patient_group: body.patient_blood_group,
        crossmatch: body.crossmatch_reference,
        expires,
        volume: body.volume_ml,
        minutes: body.prescribed_minutes,
        notes: body.notes,
        status: 'pending',
        checks: Object.fromEntries(CHECK_KEYS.map((k) => [k, false])),
        checked_at: null,
        checked_by: null,
        started_at: null,
        completed_at: null,
        stop_reason: null,
        created_at: new Date(),
        created_by: by,
      });
      return ok(`Unit ${body.unit_number} registered. Next: the bedside checks.`);
    },
    transfusionCheck: (id, { step, action }) => {
      const u = find(state.units, id);
      if (!u || tfFinished(u)) return fail('That unit is already finished.');
      if (u.status === 'in_progress') return fail('The checks are locked once the unit has started.');
      const s = tfSteps(u).find((x) => x.key === step);
      if (action === 'confirm') {
        if (!s.unlocked) return fail('Confirm the earlier checks first.');
        u.checks[step] = true;
      } else {
        if (!s.can_undo) return fail('Only the last confirmed check can be undone.');
        u.checks[step] = false;
      }
      u.checked_at = new Date();
      u.checked_by = by;
      const done = CHECK_KEYS.filter((k) => u.checks[k]).length;
      return ok(action === 'confirm' ? `Check ${s.number} of 4 done: ${s.label}.` : `Check ${s.number} undone: ${s.label}. ${done} of 4 done.`);
    },
    startTransfusion: (id) => {
      const u = find(state.units, id);
      if (!u) return fail('That unit could not be found.');
      if (!tfCanStart(u)) {
        return fail(u.status !== 'pending'
          ? 'That unit has already been started.'
          : CHECK_KEYS.every((k) => u.checks[k]) ? 'Resolve the flagged problems before starting this unit.' : 'Complete every pre-start check before starting this unit.');
      }
      u.status = 'in_progress';
      u.started_at = new Date();
      return ok(`Unit ${u.unit_number} started.`);
    },
    finishTransfusion: (id, { outcome, stop_reason }) => {
      const u = find(state.units, id);
      if (!u || u.status !== 'in_progress') return fail('That unit is not running.');
      if (outcome === 'stopped' && !stop_reason) return fail('Give a reason when stopping a unit early.');
      u.status = outcome === 'completed' ? 'completed' : 'stopped';
      u.completed_at = new Date();
      u.stop_reason = stop_reason || null;

      // Like the server (FluidBalanceLinks): the unit goes on the I/O chart as
      // blood intake, all of it, or an estimate when stopped early
      let volume = u.volume || 0;
      let description = `${u.product}, unit ${u.unit_number}`;
      if (u.status === 'stopped') {
        const ran = Math.round((u.completed_at - u.started_at) / 60000);
        volume = u.minutes ? Math.round(Math.min(volume, (volume * ran) / u.minutes)) : 0;
        description += ` (stopped early: about ${volume} mL, estimated from ${ran} of ${u.minutes} min)`;
      }
      if (volume >= 1) {
        state.io.push({ id: state.next(), direction: 'intake', category: 'blood', description, volume, at: u.completed_at, by: u.checked_by ?? by, voided: false, auto: 'blood unit' });
      }
      return ok(`Unit ${u.unit_number} ${outcome}.`);
    },

    respondAlert: (id) => {
      const a = find(state.alerts, id);
      if (!a || a.status !== 'pending') return fail('That alert has already been answered.');
      a.status = 'responded';
      a.respondedAt = new Date();
      state.alerts = state.alerts.filter((x) => x.id !== id);
      state.answered.unshift(a);
      return ok('Marked as answered.');
    },
  };
}

/** Badge counts for the demo dashboard's bed cards. */
export function demoBadgesFor(bed) {
  return bed?.patient_id ? toBundle(demoState(bed)).badges : null;
}
