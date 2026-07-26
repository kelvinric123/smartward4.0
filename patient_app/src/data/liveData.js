// Maps a live bed snapshot from the terminal API into the data shapes the
// tabs consume (see mockData.js for the canonical shapes).
//
// Live sections: patient profile, care team, vitals, length of stay and
// expected discharge. Sections with no backend yet (medications, room
// controls, meals, requests, schedule, discharge checklist, visitors) keep
// the sample data so the whole app stays usable.

import * as mock from './mockData';

function initialsOf(name) {
  const clean = (name ?? '')
    .replace(/^(dr|mr|mrs|ms|sr|sn)\.?\s+/i, '')
    .trim();
  if (!clean) return '??';
  const parts = clean.split(/\s+/);
  const first = parts[0]?.[0] ?? '';
  const last = parts.length > 1 ? parts[parts.length - 1][0] : parts[0]?.[1] ?? '';
  return (first + last).toUpperCase();
}

function firstNameOf(name) {
  const clean = (name ?? '').trim();
  if (!clean) return 'there';
  return clean.split(/\s+/)[0];
}

// Simple traffic-light banding for the vital cards.
function band(value, normal, watch) {
  if (value == null) return 'normal';
  if (value >= normal[0] && value <= normal[1]) return 'normal';
  if (value >= watch[0] && value <= watch[1]) return 'mild';
  return 'high';
}

function show(value) {
  return value == null ? '–' : value;
}

function mapVitals(vitals) {
  const latest = vitals?.readings?.length
    ? vitals.readings[vitals.readings.length - 1]
    : null;

  const sys = latest?.systolic_bp ?? null;
  const dia = latest?.diastolic_bp ?? null;

  return {
    recorded_at_label: latest?.time_label
      ? `Latest reading · ${latest.time_label}`
      : 'No readings recorded yet',
    pulse_rate: {
      value: show(latest?.pulse_rate),
      unit: 'bpm',
      status: band(latest?.pulse_rate, [60, 100], [50, 110]),
      range: '60–100',
    },
    blood_pressure: {
      value: sys != null && dia != null ? `${sys}/${dia}` : '–',
      unit: 'mmHg',
      status: sys == null ? 'normal' : sys < 130 ? 'normal' : sys < 140 ? 'mild' : 'high',
      range: '<130/80',
    },
    spo2: {
      value: show(latest?.spo2),
      unit: '%',
      status: latest?.spo2 == null ? 'normal' : latest.spo2 >= 95 ? 'normal' : latest.spo2 >= 92 ? 'mild' : 'high',
      range: '≥95',
    },
    respiratory_rate: {
      value: show(latest?.respiratory_rate),
      unit: '/min',
      status: band(latest?.respiratory_rate, [12, 20], [10, 24]),
      range: '12–20',
    },
    temperature: {
      value: show(latest?.temperature),
      unit: '°C',
      status: band(latest?.temperature, [36.1, 37.2], [35.5, 37.8]),
      range: '36.1–37.2',
    },
    pain_score: {
      value: '–',
      unit: '/10',
      status: 'normal',
      range: '0–3 mild',
    },
  };
}

const TEAM_COLORS = {
  doctor: '#0e7490',
  nurse: '#0f766e',
  other: '#d97706',
};

function mapCareTeam(careTeam) {
  return (careTeam ?? []).map((member, i) => {
    // Roles arrive like "Consultant · Cardiology" — split into role/specialty
    // so the card's "{role} · {specialty}" line reads naturally.
    const [role, specialty] = (member.role ?? 'Care team').split(' · ');
    return {
      id: member.id ?? `member-${i}`,
      name: member.name,
      role,
      specialty:
        specialty ??
        (member.group === 'doctor'
          ? 'Doctor'
          : member.group === 'nurse'
          ? 'Nursing'
          : 'Care team'),
      photo_initials: initialsOf(member.name),
      color: TEAM_COLORS[member.group] ?? TEAM_COLORS.other,
      on_shift: true,
      next_visit: member.status ?? 'On rounds',
      bio: member.detail ?? 'Part of the team looking after you.',
    };
  });
}

/**
 * Ward movements -> the Schedule tab's timeline. The first still-upcoming
 * item is flagged so the Home tab can show it as "NEXT".
 */
function mapSchedule(schedule) {
  const rows = (schedule ?? []).map((s) => ({
    id: s.id,
    time: s.time,
    title: s.title,
    detail: s.detail,
    type: s.type,
    status: s.status === 'active' ? 'upcoming' : s.status,
  }));

  const nextIndex = rows.findIndex((r) => r.status === 'upcoming');
  if (nextIndex >= 0) rows[nextIndex].is_next = true;

  return rows;
}

/**
 * The ward's discharge plan. Steps are only meaningful once a discharge has
 * actually been scheduled, so an unscheduled patient gets an empty checklist
 * and the tab explains that no date is set yet.
 */
function mapDischarge(discharge) {
  const scheduled = !!discharge?.is_scheduled;

  return {
    is_scheduled: scheduled,
    expected_date_label: scheduled
      ? discharge.expected_date_label
      : 'Not scheduled yet',
    expected_time_label: scheduled
      ? `Expected around ${discharge.expected_time_label}`
      : 'Your team will confirm your date',
    steps: scheduled
      ? [
          {
            id: 'd1',
            label: 'Discharge date confirmed by your ward',
            done: true,
            done_at: discharge.expected_date_label,
            owner: 'Ward',
          },
          {
            id: 'd2',
            label: 'Final review by your doctor',
            done: false,
            owner: 'Medical team',
            eta: discharge.expected_date_label,
          },
          {
            id: 'd3',
            label: 'Discharge medications & instructions',
            done: false,
            owner: 'Pharmacy',
            eta: discharge.expected_date_label,
          },
          {
            id: 'd4',
            label: 'Paperwork and going home',
            done: false,
            owner: 'Front desk',
            eta: discharge.expected_time_label
              ? `${discharge.expected_date_label}, ${discharge.expected_time_label}`
              : discharge.expected_date_label,
          },
        ]
      : [],
  };
}

export function buildLiveData(snapshot) {
  const p = snapshot?.patient ?? {};
  const bed = snapshot?.bed ?? {};
  const los = p.length_of_stay_days ?? null;

  const patient = {
    id: bed.id,
    name: p.name ?? 'Patient',
    preferred_name: firstNameOf(p.name),
    mrn: p.mrn ?? '–',
    gender: p.gender ?? '–',
    age: p.age ?? '–',
    photo_initials: initialsOf(p.name),
    room: p.bed_number ?? bed.bed_number ?? '–',
    ward: p.ward_name ?? bed.ward_name ?? 'Ward',
    admitted_label: p.admitted_at_label ?? '–',
    los_days: los ?? '–',
    primary_diagnosis: 'Ask your care team',
    allergies: p.allergies ?? [],
    blood_type: '–',
    language: '–',
  };

  const dischargePlan = mapDischarge(snapshot?.discharge);
  const schedule = mapSchedule(snapshot?.schedule);

  const recoveryOverview = {
    day_of_stay: los != null ? los + 1 : '–',
    expected_discharge: dischargePlan.is_scheduled
      ? snapshot.discharge.expected_date_label
      : p.expected_discharge_label ?? 'To be confirmed',
    goals_today: [
      { id: 'g1', label: 'Rest and follow your care plan', done: false },
      { id: 'g2', label: 'Eat well at meal times', done: false },
      { id: 'g3', label: 'Call your nurse if you need anything', done: false },
    ],
    progress_note:
      'Your care team reviews your progress during rounds and will keep you updated.',
  };

  const notifications = [
    {
      id: 'n-live',
      title: 'Connected to your bed',
      body: `Showing live information for bed ${patient.room}, ${patient.ward}.`,
      time_label: 'now',
      type: 'success',
    },
  ];

  if (dischargePlan.is_scheduled) {
    notifications.unshift({
      id: 'n-discharge',
      title: 'Your discharge is scheduled',
      body: `Your ward has planned your discharge for ${snapshot.discharge.expected_date_label}.`,
      time_label: 'now',
      type: 'info',
    });
  }

  return {
    patient,
    careTeam: mapCareTeam(snapshot?.care_team),
    vitals: mapVitals(snapshot?.vitals),
    recoveryOverview,
    notifications,
    todaySchedule: schedule,
    dischargeChecklist: dischargePlan,
    // No backend for these yet — keep the sample content so the tabs work.
    medications: mock.medications,
    roomControls: mock.roomControls,
    dietProfile: mock.dietProfile,
    mealMenu: mock.mealMenu,
    requestCategories: mock.requestCategories,
    visitor: mock.visitor,
  };
}

export function buildDemoData() {
  return {
    patient: mock.patient,
    careTeam: mock.careTeam,
    vitals: mock.vitals,
    medications: mock.medications,
    recoveryOverview: mock.recoveryOverview,
    roomControls: mock.roomControls,
    dietProfile: mock.dietProfile,
    mealMenu: mock.mealMenu,
    requestCategories: mock.requestCategories,
    todaySchedule: mock.todaySchedule,
    dischargeChecklist: mock.dischargeChecklist,
    notifications: mock.notifications,
    visitor: mock.visitor,
  };
}
