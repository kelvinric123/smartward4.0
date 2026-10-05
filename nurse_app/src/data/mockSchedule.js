// Demo login: the nurse's roster, leave and swap requests and the ward team,
// answering like the server (NurseAppRosterController), so My roster and Team
// work without a server.

import { nurse as demoNurse, assignedBeds } from './mockData';
import { DAYS, MONTHS, SHIFTS, currentShift, ymd } from './demoTime';

const SHIFT_INFO = {
  AM: { name: 'Morning', time: '07:00 - 14:00', hours: 7 },
  PM: { name: 'Afternoon', time: '14:00 - 23:00', hours: 9 },
  ON: { name: 'Night', time: '23:00 - 07:00', hours: 8 },
};
const LEAVE_TYPES = [
  { key: 'annual', label: 'Annual leave', code: 'AL' },
  { key: 'medical', label: 'Medical leave', code: 'MC' },
  { key: 'emergency', label: 'Emergency leave', code: 'EL' },
  { key: 'public_holiday', label: 'Public holiday off', code: 'PH' },
  { key: 'training', label: 'Training / course', code: 'TR' },
  { key: 'off_request', label: 'Requested day off', code: 'RO' },
];
const WARD = { id: 1, name: 'Ward 3A - Medical' };

// Two weeks of shifts, Monday first, for the demo nurse and three colleagues
const PATTERNS = {
  me: ['AM', 'AM', 'PM', 'PM', 'OFF', 'OFF', 'ON', 'ON', 'ON', 'OFF', 'OFF', 'AM', 'AM', 'PM'],
  10: ['PM', 'PM', 'AM', 'AM', 'ON', 'ON', 'OFF', 'OFF', 'AM', 'AM', 'PM', 'PM', 'OFF', 'ON'],
  11: ['ON', 'OFF', 'OFF', 'AM', 'AM', 'PM', 'PM', 'AM', 'PM', 'ON', 'ON', 'OFF', 'OFF', 'AM'],
  12: ['AM', 'ON', 'ON', 'OFF', 'PM', 'AM', 'AM', 'PM', 'OFF', 'PM', 'AM', 'ON', 'ON', 'OFF'],
};
const COLLEAGUES = [
  { id: 10, name: 'Sr. Farah Idris', designation: 'SISTER' },
  { id: 11, name: 'Nurse Mei Ling', designation: 'STAFF NURSE I' },
  { id: 12, name: 'Nurse Kavitha', designation: 'STAFF NURSE II' },
];

function mondayOf(d) {
  const day = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  day.setDate(day.getDate() - ((day.getDay() + 6) % 7));
  return day;
}

const short = (d) => `${d.getDate()} ${MONTHS[d.getMonth()]}`;
const dayLabel = (d) => `${DAYS[d.getDay()]} ${d.getDate()} ${MONTHS[d.getMonth()]}`;
const parse = (s) => {
  const [y, m, d] = s.split('-').map(Number);
  return new Date(y, m - 1, d);
};

function state() {
  const start = mondayOf(new Date());
  const shiftOf = (who, date) => {
    const i = Math.round((parse(date) - start) / 86400000);
    return i >= 0 && i < 14 ? PATTERNS[who][i] : null;
  };

  return {
    start,
    shiftOf,
    changes: {}, // date -> shift the demo nurse now works, after an approved swap
    leave: [], // { from, to, code, label }
    acks: {}, // week start -> true
    requests: [
      {
        id: 1,
        type: 'leave',
        summary: `Annual leave, ${dayLabel(new Date(start.getTime() + 9 * 86400000))} to ${dayLabel(new Date(start.getTime() + 10 * 86400000))}`,
        status: 'pending',
        note: 'Family event',
        created: new Date(Date.now() - 26 * 3600000),
      },
    ],
    incoming: [
      {
        id: 2,
        type: 'swap',
        from: 'Sr. Farah Idris',
        date: ymd(new Date(start.getTime() + 3 * 86400000)),
        summary: `You work AM on ${dayLabel(new Date(start.getTime() + 3 * 86400000))}; Sr. Farah Idris works your PM`,
        status: 'awaiting_colleague',
        note: 'Child\'s school event in the morning',
        created: new Date(Date.now() - 3 * 3600000),
      },
    ],
    nextId: 3,
  };
}

let demo = null;
function store() {
  if (!demo) demo = state();
  return demo;
}

function myShift(s, date) {
  return s.changes[date] ?? s.shiftOf('me', date);
}

function day(s, d) {
  const date = ymd(d);
  const today = new Date();
  const shift = myShift(s, date);
  const working = shift && shift !== 'OFF';
  const info = working ? SHIFT_INFO[shift] : null;
  const leave = s.leave.find((l) => date >= l.from && date <= l.to) ?? null;
  const startAt = info ? new Date(d.getFullYear(), d.getMonth(), d.getDate(), Number(info.time.slice(0, 2))) : null;
  const isToday = date === ymd(today);

  return {
    date,
    label: dayLabel(d),
    weekday: DAYS[d.getDay()],
    day: d.getDate(),
    month: MONTHS[d.getMonth()],
    is_today: isToday,
    is_past: date < ymd(today),
    is_weekend: d.getDay() === 0 || d.getDay() === 6,
    shift: leave ? null : shift,
    shift_name: leave ? null : info?.name ?? (shift === 'OFF' ? 'Day off' : null),
    time: leave ? null : info?.time ?? null,
    hours: leave ? 0 : info?.hours ?? 0,
    ward_id: working && !leave ? WARD.id : null,
    ward: working && !leave ? WARD.name : null,
    other_ward: false,
    beds: working && !leave && isToday ? assignedBeds.map((b) => b.number) : [],
    leave: leave ? { code: leave.code, label: leave.label } : null,
    holiday: null,
    duties: working && !leave && isToday ? ['Team Leader'] : [],
    source: working ? 'auto' : null,
    swappable: !!(working && !leave && startAt > today),
  };
}

function week(s, start) {
  const days = Array.from({ length: 7 }, (_, i) => day(s, new Date(start.getFullYear(), start.getMonth(), start.getDate() + i)));
  const working = days.filter((d) => d.shift && d.shift !== 'OFF');
  const key = ymd(start);
  const end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
  return {
    start: key,
    label: `${short(start)} – ${short(end)}`,
    days,
    totals: {
      shifts: working.length,
      hours: working.reduce((n, d) => n + d.hours, 0),
      nights: working.filter((d) => d.shift === 'ON').length,
      leave_days: days.filter((d) => d.leave).length,
    },
    acknowledgement: s.acks[key] ? { state: s.acks[key].changed ? 'changed' : 'seen', at_label: s.acks[key].at } : { state: 'new', at_label: null },
  };
}

const STATUS_LABELS = {
  awaiting_colleague: 'Waiting for colleague',
  pending: 'Waiting for approval',
  approved: 'Approved',
  declined: 'Declined',
  cancelled: 'Cancelled',
};
const stamp = (d) => `${short(d)} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

function requestRow(r, incoming) {
  const open = r.status === 'pending' || r.status === 'awaiting_colleague';
  return {
    id: r.id,
    type: r.type,
    summary: r.summary,
    from: incoming ? r.from : null,
    status: r.status,
    status_label: STATUS_LABELS[r.status],
    note: r.note ?? null,
    created_label: stamp(r.created),
    decided_label: r.decided ? stamp(r.decided) : null,
    decided_by: r.decidedBy ?? null,
    decision_note: r.decisionNote ?? null,
    can_cancel: !incoming && open,
    can_respond: incoming && r.status === 'awaiting_colleague',
  };
}

function roster(s, weekStart) {
  const start = weekStart ? mondayOf(parse(weekStart)) : s.start;
  const next = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 7);
  const prev = new Date(start.getFullYear(), start.getMonth(), start.getDate() - 7);
  return {
    nurse: { id: demoNurse.id, name: demoNurse.name, ward: WARD.name },
    start: ymd(start),
    previous_week: ymd(prev),
    next_week: ymd(next),
    weeks: [week(s, start), week(s, next)],
    requests: {
      mine: s.requests.map((r) => requestRow(r, false)),
      incoming: s.incoming.filter((r) => r.status === 'awaiting_colleague').map((r) => requestRow(r, true)),
    },
    options: {
      leave_types: LEAVE_TYPES,
      shifts: SHIFTS.map((sh) => ({ code: sh.code, name: sh.name, time: SHIFT_INFO[sh.code].time })),
    },
  };
}

// ------------------------------------------------------------------- team

function slotTeam(s, code, date, label) {
  const loads = [3.5, 2.5, 3.0];
  const onShift = COLLEAGUES.filter((c) => s.shiftOf(c.id, date) === code);
  const meOn = myShift(s, date) === code;
  const nurses = [
    ...(meOn ? [{ id: demoNurse.id, name: demoNurse.name, designation: 'STAFF NURSE I', is_me: true, is_team_leader: true, duties: ['Team Leader'], beds: assignedBeds.map((b) => b.number), patients: 4, score: 6.5 }] : []),
    ...onShift.map((c, i) => ({
      ...c,
      is_me: false,
      is_team_leader: !meOn && i === 0,
      duties: !meOn && i === 0 ? ['Team Leader'] : i === 1 ? ['Medication fridge'] : [],
      beds: [['14A', '14B'], ['15A', '15B', '16A'], ['16B', '17A']][i],
      patients: [2, 3, 2][i],
      score: loads[i],
    })),
  ];
  const average = nurses.length ? Math.round((nurses.reduce((n, x) => n + x.score, 0) / nurses.length) * 10) / 10 : 0;
  nurses.forEach((n) => {
    n.level = n.score > average * 1.25 ? 'heavy' : n.score < average * 0.75 ? 'light' : 'even';
  });
  return { date, code, name: SHIFT_INFO[code].name, time: SHIFT_INFO[code].time, label, nurses, average, unassigned_beds: [] };
}

function team(s) {
  const now = currentShift();
  const order = ['AM', 'PM', 'ON'];
  const nextCode = order[(order.indexOf(now.code) + 1) % 3];
  const nextDate = now.code === 'ON' ? ymd(now.end) : now.date;
  return {
    ward: WARD,
    current: slotTeam(s, now.code, now.date, now.code),
    next: slotTeam(s, nextCode, nextDate, nextDate === ymd(new Date()) ? nextCode : `${nextCode} tomorrow`),
  };
}

/** The roster at a glance and my workload, for the demo dashboard. */
export function demoDashboardSchedule() {
  const s = store();
  const today = new Date();
  const tomorrow = new Date(today.getFullYear(), today.getMonth(), today.getDate() + 1);
  const weeks = [week(s, s.start), week(s, new Date(s.start.getFullYear(), s.start.getMonth(), s.start.getDate() + 7))];
  return {
    schedule: {
      today: day(s, today),
      tomorrow: day(s, tomorrow),
      swaps_to_answer: s.incoming.filter((r) => r.status === 'awaiting_colleague').length,
      requests_open: s.requests.filter((r) => r.status === 'pending' || r.status === 'awaiting_colleague').length,
      requests_decided: 0,
      to_acknowledge: weeks
        .filter((w) => w.totals.shifts > 0 && w.acknowledgement.state !== 'seen')
        .map((w) => ({ start: w.start, label: w.label, state: w.acknowledgement.state })),
    },
    my_load: { score: 6.5, beds: 4, patients: 4, level: 'heavy', team_average: 4.2, team_size: 3 },
  };
}

/** Workload per demo bed: score and what makes it up, as WorkloadCalculator scores it. */
export const DEMO_WORKLOAD = {
  101: { score: 2.5, factors: [{ label: 'Patient', points: 1 }, { label: 'EWS 5', points: 1 }, { label: 'Infusion running', points: 0.5 }] },
  102: { score: 1.0, factors: [{ label: 'Patient', points: 1 }] },
  103: { score: 2.0, factors: [{ label: 'Patient', points: 1 }, { label: 'Isolation', points: 0.5 }, { label: 'STAT order', points: 0.5 }] },
  104: { score: 1.0, factors: [{ label: 'Patient', points: 1 }] },
};

// ----------------------------------------------------------------- client

export function createDemoScheduleClient() {
  const s = store();
  let shown = null;
  const wait = (ms = 250) => new Promise((resolve) => setTimeout(resolve, ms));
  const ok = async (message) => {
    await wait();
    return { success: true, message, roster: roster(s, shown) };
  };
  const fail = async (message) => {
    await wait(150);
    throw new Error(message);
  };

  return {
    loadRoster: async ({ week: w } = {}) => {
      shown = w ?? null;
      await wait(300);
      return { success: true, roster: roster(s, shown) };
    },

    acknowledge: (w) => {
      const key = ymd(mondayOf(parse(w)));
      s.acks[key] = { at: stamp(new Date()), changed: false };
      const wk = week(s, mondayOf(parse(w)));
      return ok(`Roster for ${wk.label} acknowledged.`);
    },

    colleagues: async (date) => {
      await wait(200);
      const mine = myShift(s, date);
      if (!mine || mine === 'OFF') throw new Error('You are not rostered to work that day.');
      return {
        success: true,
        date,
        shift: mine,
        colleagues: COLLEAGUES.map((c) => ({ ...c, shift: s.shiftOf(c.id, date), on_leave: null })),
      };
    },

    requestLeave: ({ leave_type, start_date, end_date, note }) => {
      if (!start_date || !end_date) return fail('Choose the first and last day of leave.');
      if (end_date < start_date) return fail('The last day of leave cannot be before the first.');
      if (start_date < ymd(new Date())) return fail('Leave cannot start in the past. Ask the nurse manager to record it.');
      const type = LEAVE_TYPES.find((t) => t.key === leave_type);
      const from = parse(start_date);
      const to = parse(end_date);
      s.requests.unshift({
        id: s.nextId++,
        type: 'leave',
        summary: `${type?.label ?? 'Leave'}, ${dayLabel(from)}${start_date === end_date ? '' : ` to ${dayLabel(to)}`}`,
        status: 'pending',
        note: note ?? null,
        created: new Date(),
      });
      return ok('Leave request sent to the nurse manager.');
    },

    requestSwap: ({ shift_date, colleague_id, colleague_shift, note }) => {
      const colleague = COLLEAGUES.find((c) => c.id === colleague_id);
      const mine = myShift(s, shift_date);
      if (!colleague) return fail('Choose the colleague to swap with.');
      if (colleague_shift && colleague_shift === mine) return fail('Swap for a different shift, or give the shift away.');
      s.requests.unshift({
        id: s.nextId++,
        type: 'swap',
        summary: `${colleague_shift ? 'Swap' : 'Give away'} ${mine} on ${dayLabel(parse(shift_date))} with ${colleague.name}${colleague_shift ? ` (for their ${colleague_shift})` : ''}`,
        status: 'awaiting_colleague',
        note: note ?? null,
        created: new Date(),
      });
      return ok(`Swap request sent to ${colleague.name}. Once they accept, it goes to the nurse manager.`);
    },

    cancelRequest: (id) => {
      const r = s.requests.find((x) => x.id === id);
      if (!r || !['pending', 'awaiting_colleague'].includes(r.status)) return fail('That request can no longer be cancelled.');
      Object.assign(r, { status: 'cancelled', decided: new Date(), decidedBy: demoNurse.name });
      return ok('Request cancelled.');
    },

    respondRequest: (id, { accept, note }) => {
      const r = s.incoming.find((x) => x.id === id);
      if (!r || r.status !== 'awaiting_colleague') return fail('That request is no longer waiting for your answer.');
      Object.assign(r, accept ? { status: 'pending' } : { status: 'declined', decisionNote: note ?? null });
      return ok(accept ? 'Accepted. The swap now goes to the nurse manager for approval.' : `Swap declined. ${r.from} will see your answer.`);
    },

    loadTeam: async () => {
      await wait(250);
      return { success: true, team: team(s) };
    },
  };
}
