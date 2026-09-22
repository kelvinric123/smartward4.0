// Sample patient charts for the demo login, in the same shape the server
// sends (DoctorAppPatientChart). Orders written or cancelled in the demo are
// kept in memory, and a fluid restriction updates the demo fluid plan, so the
// whole flow can be tried without a server.

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const pad = (n) => String(n).padStart(2, '0');

function at(minutesAgo) {
  return new Date(Date.now() - minutesAgo * 60000);
}

function time(minutesAgo) {
  const d = at(minutesAgo);
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function stamp(minutesAgo) {
  const d = at(minutesAgo);
  return `${pad(d.getDate())} ${MONTHS[d.getMonth()]} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
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
  chart.badges = {
    io_level: alerts[0]?.level ?? null,
    meds_overdue: chart.medications.counts.overdue,
    orders_open: chart.orders.open.length,
  };
  chart.generated_label = time(0);
  return chart;
}

function seedChart(bed, doctorName) {
  const id = bed.patient_id ?? bed.id;
  const restricted = id % 3 === 0; // of the demo patients, 5001 is on a fluid restriction
  const hoursIntoDay = Math.max(1, (new Date().getHours() - 7 + 24) % 24 || 1);
  let n = 1;

  const entries = [
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
      allergies: bed.allergies ?? [],
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
      ],
      closed: [
        {
          id: `${id}-o3`, instruction: 'ECG now.', urgency: 'stat', urgency_label: 'STAT', ordered_label: stamp(600),
          consultant: doctorName, is_mine: true, assigned_nurse: 'SN Rahman', fluid_restriction: null, status: 'done',
          status_label: 'Done', closed_label: stamp(585), closed_by: 'SN Rahman', outcome_note: 'Sinus rhythm, rate 88.',
          can_cancel: false,
        },
      ],
    },
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

/** Forget every demo chart (on logout), so the next demo starts fresh. */
export function resetDemoCharts() {
  charts.clear();
  planBefore.clear();
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
        ordered_label: stamp(0), consultant: doctorName, is_mine: true, assigned_nurse: 'SN Aisyah',
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
          set_label: `Set ${stamp(0)}`,
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
        ...order, status: 'cancelled', status_label: 'Cancelled', closed_label: stamp(0), can_cancel: false,
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
          set_label: `Set ${stamp(0)}`,
          from_order: false,
        };
      }
      recalc(chart);
      return { success: true, message: 'Order cancelled.', chart: copy(chart) };
    },
  };
}
