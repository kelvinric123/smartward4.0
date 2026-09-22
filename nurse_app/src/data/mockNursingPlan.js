// Demo-login nursing plan: the care plan and this shift's tasks for the demo
// beds, answering in the server's shapes (NursingCarePlan::forPatient and
// ShiftTasks::forPatient) and following the same rules when the nurse adds,
// evaluates, edits or closes a diagnosis. Nothing is saved.

import { nurse as demoNurse } from './mockData';
import { ago, ahead, currentShift, dm, hm, ml, sameDay, shiftLabel, when } from './demoTime';

// A copy of NursingCarePlanLibrary
const CATEGORIES = {
  safety: 'Safety',
  comfort: 'Comfort',
  fluid: 'Fluid balance',
  skin: 'Skin',
  infection: 'Infection',
  respiratory: 'Breathing',
  nutrition: 'Nutrition',
  metabolic: 'Blood glucose',
  neuro: 'Cognition',
  treatment: 'Treatment',
  psychosocial: 'Psychosocial',
  other: 'Other',
};

const TEMPLATES = {
  falls: {
    category: 'safety',
    diagnosis: 'Risk for falls',
    related_to: 'Impaired mobility, medication or confusion',
    goal: 'No fall during this admission.',
    interventions: ['Bed at its lowest position with brakes on', 'Call bell and personal items within reach', 'Hourly rounding', 'Non-slip footwear when mobilising', 'Assist with toileting and walking', 'Review sedating medicines with the doctor'],
  },
  pain: {
    category: 'comfort',
    diagnosis: 'Acute pain',
    related_to: 'Illness, surgery or procedure',
    goal: 'Pain score 3/10 or less, and the patient reports being comfortable.',
    interventions: ['Assess the pain score every 4 hours and after analgesia', 'Give prescribed analgesia on time', 'Comfort measures: positioning, heat or cold', 'Reassess 30 to 60 minutes after analgesia', 'Escalate uncontrolled pain to the doctor'],
  },
  fluid_excess: {
    category: 'fluid',
    diagnosis: 'Excess fluid volume',
    related_to: 'Heart, kidney or liver failure, or IV fluids',
    goal: 'Intake within the fluid plan and no signs of fluid overload.',
    interventions: ['Strict intake and output chart', 'Keep to the fluid limit', 'Weigh daily at the same time', 'Check for edema and breathlessness each shift', 'Report urine output below target'],
  },
  fluid_deficit: {
    category: 'fluid',
    diagnosis: 'Risk for deficient fluid volume',
    related_to: 'Poor intake, losses or nil by mouth',
    goal: 'Urine output at target, moist mucous membranes, stable blood pressure.',
    interventions: ['Encourage or give prescribed fluids', 'Strict intake and output chart', 'Monitor urine output against the target', 'Watch for dizziness, low blood pressure and a fast pulse'],
  },
  skin: {
    category: 'skin',
    diagnosis: 'Risk for impaired skin integrity',
    related_to: 'Immobility, incontinence or poor nutrition',
    goal: 'Skin intact with no new pressure injury.',
    interventions: ['Reposition at least every 2 hours', 'Pressure-relieving mattress', 'Keep the skin clean and dry', 'Check pressure areas each shift', 'Support nutrition and hydration'],
  },
  infection: {
    category: 'infection',
    diagnosis: 'Risk for infection',
    related_to: 'Invasive lines, wounds or reduced immunity',
    goal: 'No signs of infection; temperature within normal range.',
    interventions: ['Hand hygiene before and after every contact', 'Aseptic care of lines, catheters and wounds', 'Monitor temperature and line or wound sites', 'Isolation precautions as required', 'Give antibiotics on time'],
  },
  breathing: {
    category: 'respiratory',
    diagnosis: 'Ineffective breathing pattern',
    related_to: 'Infection, fluid or reduced lung expansion',
    goal: 'SpO2 at target and respiratory rate 12 to 20.',
    interventions: ['Sit upright or position for comfort', 'Oxygen as prescribed; check the delivery device', 'Monitor SpO2 and respiratory rate each round', 'Encourage deep breathing and coughing', 'Escalate if the EWS rises'],
  },
  nutrition: {
    category: 'nutrition',
    diagnosis: 'Imbalanced nutrition: less than body requirements',
    related_to: 'Poor appetite, nausea or nil by mouth',
    goal: 'Eats at least half of each meal; weight stable.',
    interventions: ['Food chart', 'Assist with meals', 'Refer to the dietitian', 'Weigh weekly'],
  },
  glucose: {
    category: 'metabolic',
    diagnosis: 'Risk for unstable blood glucose',
    related_to: 'Diabetes, steroids or poor intake',
    goal: 'HGT between 4 and 10 mmol/L.',
    interventions: ['HGT as ordered', 'Give insulin or diabetic medicines as prescribed', 'Treat HGT below 4 by the hypoglycaemia protocol', 'Diabetic diet'],
  },
  confusion: {
    category: 'neuro',
    diagnosis: 'Risk for acute confusion',
    related_to: 'Illness, medication, age or sleep loss',
    goal: 'Stays oriented and safe; no new delirium.',
    interventions: ['Reorientate regularly', 'Glasses and hearing aids on', 'Keep a sleep routine; low light at night', 'Frequent safety checks', 'Report new confusion to the doctor'],
  },
  transfusion: {
    category: 'treatment',
    diagnosis: 'Risk for transfusion reaction',
    related_to: 'Blood product transfusion',
    goal: 'Unit completed with no reaction.',
    interventions: ['Baseline observations before starting', 'Observations 15 minutes after the start, then per protocol', 'Stay with the patient for the first 15 minutes', 'Stop and escalate at any sign of a reaction'],
  },
  anxiety: {
    category: 'psychosocial',
    diagnosis: 'Anxiety',
    related_to: 'Illness, hospital stay or uncertainty',
    goal: 'Patient says they feel calmer and understands the plan.',
    interventions: ['Explain each procedure beforehand', 'Encourage questions and involve the family', 'Offer a quiet environment', 'Refer for counselling if needed'],
  },
};

const OUTCOMES = { met: 'Met', partly_met: 'Partly met', not_met: 'Not met' };
const STATUS_LABELS = { active: 'Active', resolved: 'Resolved', discontinued: 'Discontinued' };
const TONE_ORDER = { critical: 0, warning: 1, default: 2, good: 3, muted: 4 };
const HISTORY = 5;
const MAX_TASKS = 40;

// Each demo bed's plan as the nurses left it. shiftsAgo 0 is the shift on
// now, 1 the one before, ...
const PLANS = {
  5001: {
    items: [
      {
        template: 'falls',
        startedMinutes: 3 * 1440,
        evaluations: [
          { outcome: 'partly_met', note: 'Tried to get up alone once overnight; reminded to call.', shiftsAgo: 1, by: 'Sr. Farah Idris' },
          { outcome: 'met', note: null, shiftsAgo: 2, by: 'Sr. Farah Idris' },
        ],
      },
      { template: 'infection', startedMinutes: 2 * 1440, evaluations: [{ outcome: 'not_met', note: 'Temp 38.4; cultures repeated, doctor informed.', shiftsAgo: 1, by: 'Sr. Farah Idris' }] },
    ],
    pain: 'Pain score 6 - Moderate pain',
    assessments: [{ code: 'MORSE', name: 'Morse Fall Scale', dueIn: 95, every: '1 day', history: 'last 55 (High) yesterday' }],
    movements: [{ location: 'Radiology', type: 'Imaging', inMinutes: 150, notes: 'Portable not available: chest X-ray in the department, porter booked' }],
  },
  5002: {
    items: [],
    movements: [{ location: 'Physiotherapy gym', type: null, inMinutes: 50, notes: 'Walking practice with the physio' }],
  },
  5003: {
    items: [
      {
        template: 'fluid_excess',
        related_to: 'Chronic kidney disease, on a 1 L fluid limit',
        startedMinutes: 5 * 1440,
        evaluations: [
          { outcome: 'met', note: 'Within the limit so far this shift.', shiftsAgo: 0, by: demoNurse.name },
          { outcome: 'not_met', note: 'Over by 200 mL after IV antibiotics and flushes.', shiftsAgo: 1, by: 'Sr. Farah Idris' },
        ],
      },
      { template: 'transfusion', startedMinutes: 70, evaluations: [] },
    ],
  },
  5004: {
    items: [],
    closed: [{ template: 'pain', status: 'resolved', note: 'Pain 1/10 on oral analgesia.', startedMinutes: 2 * 1440, closedMinutes: 600, by: 'Sr. Farah Idris' }],
  },
};

// ---------------------------------------------------------------- state

/** The shift `back` shifts before the one on now. */
function shiftBefore(back) {
  let slot = currentShift();
  for (let i = 0; i < back; i++) slot = currentShift(new Date(slot.start.getTime() - 60000));
  return slot;
}

/** When an evaluation `back` shifts ago happened: well into that shift, never in the future. */
function evaluationTime(back) {
  const slot = shiftBefore(back);
  if (back === 0) return new Date(Math.max(slot.start.getTime() + 60000, Date.now() - 25 * 60000));
  return new Date(slot.start.getTime() + (slot.end - slot.start) * 0.6);
}

/** The plan items for a demo bed, with their evaluations. */
export function demoCarePlan(bed, next) {
  const p = PLANS[bed.patient_id] ?? {};
  const item = (t, extra) => ({
    id: next(),
    template_key: t.template,
    category: TEMPLATES[t.template].category,
    diagnosis: TEMPLATES[t.template].diagnosis,
    related_to: t.related_to ?? TEMPLATES[t.template].related_to,
    goal: TEMPLATES[t.template].goal,
    interventions: [...TEMPLATES[t.template].interventions],
    started_at: ago(t.startedMinutes),
    created_by: 'Sr. Farah Idris',
    evaluations: [],
    resolved_at: null,
    resolved_by: null,
    resolve_note: null,
    ...extra,
  });

  return [
    ...(p.closed ?? []).map((t) =>
      item(t, { status: t.status, resolved_at: ago(t.closedMinutes), resolved_by: t.by, resolve_note: t.note })
    ),
    ...(p.items ?? []).map((t) =>
      item(t, {
        status: 'active',
        evaluations: (t.evaluations ?? []).map((e) => {
          const slot = shiftBefore(e.shiftsAgo);
          return { id: next(), outcome: e.outcome, note: e.note, at: evaluationTime(e.shiftsAgo), by: e.by, shiftCode: slot.code, shiftDate: slot.date };
        }),
      })
    ),
  ];
}

// --------------------------------------------------------------- bundle

const evaluatedIn = (item, slot) => item.evaluations.some((e) => e.shiftCode === slot.code && e.shiftDate === slot.date);
const latestFirst = (list) => [...list].sort((a, b) => b.at - a.at);

function itemView(item, slot) {
  const evaluation = (e) => ({
    id: e.id,
    outcome: e.outcome,
    outcome_label: OUTCOMES[e.outcome],
    note: e.note,
    time_label: when(e.at),
    shift_label: e.shiftCode ? shiftLabel(e.shiftCode, e.shiftDate) : null,
    by: e.by,
  });
  const evaluations = latestFirst(item.evaluations);

  return {
    id: item.id,
    template_key: item.template_key,
    category: item.category,
    category_label: CATEGORIES[item.category] ?? CATEGORIES.other,
    diagnosis: item.diagnosis,
    related_to: item.related_to,
    goal: item.goal,
    interventions: item.interventions,
    status: item.status,
    status_label: STATUS_LABELS[item.status],
    started_label: when(item.started_at),
    created_by: item.created_by,
    evaluated_this_shift: evaluatedIn(item, slot),
    latest: evaluations[0] ? evaluation(evaluations[0]) : null,
    history: evaluations.slice(0, HISTORY).map(evaluation),
    resolved_label: item.resolved_at ? when(item.resolved_at) : null,
    resolved_by: item.resolved_by,
    resolve_note: item.resolve_note,
  };
}

/** Library entries the demo record points to and the plan does not cover (NursingCarePlan::suggestionsFor). */
function suggestionsFor(state, parts, active) {
  const s = state.s;
  const p = PLANS[state.bed.patient_id] ?? {};
  const latest = parts.vitals.latest;
  const openUnit = [...parts.transfusions.running, ...parts.transfusions.pending][0];
  const insulin = state.meds.some((m) => m.status === 'active' && /insulin/i.test(m.name));

  const reasons = {
    falls: s.fallRisk ? `Fall risk on record: ${s.fallRisk}` : null,
    pain: p.pain ?? null,
    fluid_excess: state.plan?.limit
      ? `Fluid limit ${ml(state.plan.limit)} per day`
      : state.assessment?.concern
        ? state.assessment.edema
        : null,
    fluid_deficit: state.plan?.urine && !state.plan?.limit ? `Urine target ${state.plan.urine} mL/h` : s.nbm ? 'Nil by mouth' : null,
    skin: null,
    infection: s.isolation ? `Isolation: ${s.isolation}` : latest?.temperature >= 38 ? `Temperature ${latest.temperature} °C` : null,
    breathing: latest?.oxygen
      ? `On oxygen (${latest.oxygen})`
      : latest?.spo2 != null && latest.spo2 < 94
        ? `SpO2 ${latest.spo2}%`
        : latest?.respiratory_rate > 20
          ? `Respiratory rate ${latest.respiratory_rate}`
          : null,
    nutrition: null,
    glucose: insulin ? 'Insulin charted' : /diabetic/i.test(s.diet ?? '') ? 'Diabetic diet' : null,
    confusion: null,
    transfusion: openUnit ? `Blood unit ${openUnit.unit_number} ${openUnit.status === 'in_progress' ? 'running' : 'registered'}` : null,
  };

  const inPlan = active.map((i) => i.template_key).filter(Boolean);
  return Object.entries(reasons)
    .filter(([key, reason]) => reason && !inPlan.includes(key))
    .map(([key, reason]) => ({
      key,
      diagnosis: TEMPLATES[key].diagnosis,
      category_label: CATEGORIES[TEMPLATES[key].category],
      reason,
    }));
}

function carePlanBundle(state, parts, slot) {
  const active = state.carePlan.filter((i) => i.status === 'active').sort((a, b) => a.id - b.id);
  const closed = state.carePlan.filter((i) => i.status !== 'active').sort((a, b) => b.resolved_at - a.resolved_at);

  return {
    active: active.map((i) => itemView(i, slot)),
    closed: closed.map((i) => itemView(i, slot)),
    suggestions: suggestionsFor(state, parts, active),
    templates: Object.entries(TEMPLATES).map(([key, t]) => ({
      key,
      diagnosis: t.diagnosis,
      category: t.category,
      category_label: CATEGORIES[t.category],
      related_to: t.related_to,
      goal: t.goal,
      interventions: t.interventions,
      in_plan: active.some((i) => i.template_key === key),
    })),
    outcomes: OUTCOMES,
    shift: { code: slot.code, label: slot.code, name: slot.name, date: slot.date },
    due_evaluations: active.filter((i) => !evaluatedIn(i, slot)).length,
  };
}

/** What is due before the shift ends (ShiftTasks::forPatient), from the demo chart. */
function shiftTasks(state, parts, slot) {
  const now = new Date();
  const end = slot.end;
  const p = PLANS[state.bed.patient_id] ?? {};
  const tasks = [];
  const task = (at, category, title, detail, tone, tab, badge = null) => {
    const overdue = !!at && at < now;
    tasks.push({
      at,
      time_label: at ? (sameDay(at, now) ? hm(at) : `${dm(at)} ${hm(at)}`) : null,
      overdue,
      category,
      title,
      detail: detail || null,
      tone: overdue && tone === 'default' ? 'warning' : tone,
      tab,
      badge: overdue ? 'Overdue' : badge,
    });
  };

  // Doses due before the shift ends, and further doses of regular orders
  state.meds
    .filter((m) => m.status === 'active' && !m.prn && m.nextDue)
    .forEach((m) => {
      const what = `${m.name} ${m.dose} ${m.route} ${m.freq.split(' (')[0]}`;
      const flag = m.highAlert ? 'High alert' : null;
      if (m.nextDue < end) task(m.nextDue, 'medication', what, null, m.nextDue < now ? 'critical' : 'default', 'meds', flag);
      const hours = Number(/every (\d+) h/.exec(m.freq)?.[1]);
      if (hours) {
        for (let t = new Date(m.nextDue.getTime() + hours * 3600000); t < end; t = new Date(t.getTime() + hours * 3600000)) {
          if (t > now) task(t, 'medication', what, null, 'default', 'meds', flag);
        }
      }
    });

  parts.orders.open.forEach((o) =>
    task(
      null,
      'order',
      o.instruction.length > 110 ? `${o.instruction.slice(0, 107)}...` : o.instruction,
      `${o.consultant ?? 'Consultant'}${o.assigned_nurse ? ` · with ${o.assigned_nurse}` : ''}`,
      o.urgency === 'stat' ? 'critical' : o.urgency === 'urgent' ? 'warning' : 'default',
      'orders',
      o.urgency_label
    )
  );

  (p.assessments ?? []).forEach((a) => {
    const due = ahead(a.dueIn);
    if (due < end) task(due, 'assessment', `${a.name} reassessment`, `${a.history[0].toUpperCase()}${a.history.slice(1)} · every ${a.every}`, a.dueIn < 0 ? 'critical' : a.dueIn <= 60 ? 'warning' : 'default', null, a.code);
  });

  // HGT three times a day, last reading at 08:00
  if (state.bed.last_hgt) {
    const last = new Date();
    if (last.getHours() < 8) last.setDate(last.getDate() - 1);
    last.setHours(8, 0, 0, 0);
    const due = new Date(last.getTime() + 480 * 60000);
    if (due < end) task(due, 'hgt', 'Blood glucose (HGT)', `TDS (Three Times Daily) · last ${state.bed.last_hgt.value} at ${when(last)}`, due < now ? 'warning' : 'default', null, 'HGT');
  }

  const status = parts.io.status;
  if (status.limit) {
    const l = status.limit;
    task(null, 'fluid', `Fluid limit ${ml(l.limit)} today`,
      `${ml(l.taken)} taken in, ${l.state === 'over' ? `${ml(l.over_by)} over` : `${ml(l.remaining)} left`}`,
      l.state === 'over' ? 'critical' : l.state === 'near' ? 'warning' : 'default', 'io');
  }
  const fluidAlerts = status.alerts.filter((a) => !a.title.includes('intake limit'));
  fluidAlerts.forEach((a) => task(null, 'fluid', a.title, a.detail, a.level === 'critical' ? 'critical' : 'warning', 'io'));
  if (!status.limit && fluidAlerts.length === 0) {
    const tt = parts.io.totals;
    task(null, 'fluid', 'Intake / output chart', `In ${tt.intake_label} · out ${tt.output_label} · ${tt.balance_label}`, 'default', 'io');
  }

  const tf = parts.transfusions;
  const holdOf = (u) => tf.exceptions.find((e) => e.unit_id === u.id && e.level === 'critical');
  tf.running.forEach((u) => {
    const finish = u.end_at ? new Date(u.end_at) : null;
    task(finish && finish < end ? finish : null, 'transfusion', `Blood unit ${u.unit_number} ${finish ? 'due to finish' : 'running'}`,
      `${u.product} · observations per protocol · 4 h limit ${u.limit_label ?? '-'}`, holdOf(u) ? 'critical' : 'warning', 'transfusion', 'Running');
  });
  tf.pending.forEach((u) => {
    const hold = holdOf(u);
    task(null, 'transfusion',
      u.can_start ? `Unit ${u.unit_number} ready to start` : hold ? `Unit ${u.unit_number} on hold: ${hold.title}` : `Bedside checks for unit ${u.unit_number}`,
      `${u.product} · ${u.steps_done} of 4 checks done`, hold ? 'critical' : 'default', 'transfusion', 'Pre-start');
  });

  parts.infusions.active.forEach((inf) => {
    if (inf.status === 'alarming') {
      task(null, 'infusion', `Pump alarm: ${inf.medication}`, inf.alarm_message, 'critical', 'infusion');
      return;
    }
    if (!inf.ends_label) return;
    const [h, m] = inf.ends_label.split(':').map(Number);
    const endsAt = new Date(now);
    endsAt.setHours(h, m, 0, 0);
    if (endsAt < new Date(now.getTime() - 5 * 60000)) endsAt.setDate(endsAt.getDate() + 1);
    if (endsAt < end) task(endsAt, 'infusion', `${inf.medication} ends`, 'Prepare the next bag or flush the line', inf.is_warning ? 'warning' : 'default', 'infusion');
  });

  (p.movements ?? []).forEach((mv) => {
    const at = ahead(mv.inMinutes);
    if (at < end) task(at, 'movement', `To ${mv.location}${mv.type ? ` (${mv.type})` : ''}`, mv.notes, 'default', null);
  });

  state.carePlan
    .filter((i) => i.status === 'active' && !evaluatedIn(i, slot))
    .sort((a, b) => a.id - b.id)
    .forEach((i) => task(null, 'careplan', `Evaluate: ${i.diagnosis}`, `Goal: ${i.goal.length > 90 ? `${i.goal.slice(0, 87)}...` : i.goal}`, 'default', 'plan'));

  const v = parts.vitals.latest;
  if (v) {
    task(null, 'vitals', 'Vital signs', `Last ${v.time_label}${v.ews != null ? ` · EWS ${v.ews}` : ''}`,
      v.ews != null && v.ews >= 5 ? 'critical' : v.ews != null && v.ews >= 3 ? 'warning' : 'default', 'overview', v.ews != null ? `EWS ${v.ews}` : null);
  } else {
    task(null, 'vitals', 'Vital signs', 'None recorded yet this stay', 'warning', 'overview');
  }

  const timed = tasks.filter((t) => t.at).sort((a, b) => a.at - b.at);
  const standing = tasks.filter((t) => !t.at).sort((a, b) => (TONE_ORDER[a.tone] ?? 5) - (TONE_ORDER[b.tone] ?? 5));
  const strip = ({ at, ...rest }) => rest;

  return {
    shift: { code: slot.code, name: slot.name, label: slot.code, time: `${hm(slot.start)} - ${hm(slot.end)}`, nurse: demoNurse.name },
    timed: timed.slice(0, MAX_TASKS).map(strip),
    standing: standing.slice(0, MAX_TASKS).map(strip),
    counts: { total: timed.length + standing.length, overdue: tasks.filter((t) => t.overdue).length },
  };
}

/**
 * The chart's nursing_plan, from the demo state and the parts of the chart
 * already built (orders, io, medications, infusions, transfusions, vitals).
 */
export function nursingPlanBundle(state, parts) {
  const slot = currentShift();
  return {
    care_plan: carePlanBundle(state, parts, slot),
    shift: shiftTasks(state, parts, slot),
  };
}

// -------------------------------------------------------------- actions

const lines = (list) =>
  [...new Set((Array.isArray(list) ? list : []).map((l) => (typeof l === 'string' ? l.trim() : '')).filter(Boolean))].slice(0, 20);
const cleanText = (text) => (typeof text === 'string' && text.trim() ? text.trim() : null);

/** The care plan actions of the demo client, with the server's rules and messages. */
export function carePlanActions(state, { ok, fail, by }) {
  const activeItem = (id) => state.carePlan.find((i) => i.id === id && i.status === 'active');

  return {
    addCarePlanItem: ({ template_key, diagnosis, related_to, goal, interventions }) => {
      const t = template_key ? TEMPLATES[template_key] : null;
      if (template_key && !t) return fail('The selected template key is invalid.');
      if (!t && !cleanText(diagnosis)) return fail('Name the nursing diagnosis, or pick one from the list.');
      if (!t && !cleanText(goal)) return fail('Write the goal for this diagnosis.');
      if (t && state.carePlan.some((i) => i.status === 'active' && i.template_key === template_key)) {
        return fail(`${t.diagnosis} is already in the plan.`);
      }
      const item = {
        id: state.next(),
        template_key: t ? template_key : null,
        category: t?.category ?? 'other',
        diagnosis: cleanText(diagnosis) ?? t.diagnosis,
        related_to: related_to === undefined ? t?.related_to ?? null : cleanText(related_to),
        goal: cleanText(goal) ?? t.goal,
        interventions: lines(interventions ?? t?.interventions),
        status: 'active',
        started_at: new Date(),
        created_by: by,
        evaluations: [],
        resolved_at: null,
        resolved_by: null,
        resolve_note: null,
      };
      state.carePlan.push(item);
      return ok(`"${item.diagnosis}" added to the care plan.`);
    },

    updateCarePlanItem: (id, { related_to, goal, interventions }) => {
      const item = activeItem(id);
      if (!item) return fail('That diagnosis is closed.');
      if (goal !== undefined && !cleanText(goal)) return fail('Write the goal for this diagnosis.');
      if (goal !== undefined) item.goal = cleanText(goal);
      if (related_to !== undefined) item.related_to = cleanText(related_to);
      if (interventions !== undefined) item.interventions = lines(interventions);
      return ok(`"${item.diagnosis}" updated.`);
    },

    evaluateCarePlanItem: (id, { outcome, note }) => {
      if (!OUTCOMES[outcome]) return fail('Choose whether the goal was met.');
      if (outcome === 'not_met' && !cleanText(note)) return fail('Say briefly why - the next shift works from it.');
      const item = activeItem(id);
      if (!item) return fail('That diagnosis is closed.');
      const slot = currentShift();
      const values = { outcome, note: cleanText(note), at: new Date(), by, shiftCode: slot.code, shiftDate: slot.date };
      const existing = item.evaluations.find((e) => e.shiftCode === slot.code && e.shiftDate === slot.date);
      if (existing) Object.assign(existing, values); // one evaluation per shift
      else item.evaluations.push({ id: state.next(), ...values });
      return ok(`"${item.diagnosis}" evaluated: ${OUTCOMES[outcome]}.`);
    },

    closeCarePlanItem: (id, { status, note }) => {
      if (!['resolved', 'discontinued'].includes(status)) return fail('Choose resolved or discontinued.');
      if (status === 'discontinued' && !cleanText(note)) return fail('Say briefly why - the next shift works from it.');
      const item = activeItem(id);
      if (!item) return fail('That diagnosis is already closed.');
      Object.assign(item, { status, resolved_at: new Date(), resolved_by: by, resolve_note: cleanText(note) });
      return ok(`"${item.diagnosis}" ${status}.`);
    },
  };
}
