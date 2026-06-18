// ============================================================
// Patient App — Mock API
// ============================================================
// Single source of truth for all data the screens consume.
// Swap each export for a fetch() call when the backend is ready.
// Keep the shape of the returned objects identical so the UI
// does not need to change.
//
// Example:
//   export async function getPatient() {
//     const r = await fetch(`${BASE}/patients/${id}.json`);
//     return r.json();
//   }
// ============================================================

export const patient = {
  id: 7821,
  name: 'Lim Wei Jie',
  preferred_name: 'Wei Jie',
  mrn: 'MRN-208104',
  gender: 'M',
  age: 42,
  photo_initials: 'WJ',
  room: '12A',
  ward: 'Ward 3A — Medical',
  admitted_label: '14 Jun, 08:20',
  los_days: 4,
  primary_diagnosis: 'Community-acquired pneumonia',
  allergies: ['Penicillin', 'Shellfish'],
  blood_type: 'O+',
  language: 'English',
};

export const careTeam = [
  {
    id: 1,
    name: 'Dr. Rajan Krishnan',
    role: 'Attending Physician',
    specialty: 'Internal Medicine',
    photo_initials: 'RK',
    color: '#0e7490',
    on_shift: true,
    next_visit: 'Today, 10:30',
    bio: 'Lead consultant for your care. Visits each morning during rounds.',
  },
  {
    id: 2,
    name: 'Dr. Chen Hui',
    role: 'Specialist',
    specialty: 'Pulmonology',
    photo_initials: 'CH',
    color: '#7c3aed',
    on_shift: true,
    next_visit: 'Today, 14:00',
    bio: 'Lung specialist reviewing your respiratory recovery.',
  },
  {
    id: 3,
    name: 'Sr. Maria Lim',
    role: 'Primary Nurse',
    specialty: 'Morning Shift',
    photo_initials: 'ML',
    color: '#0f766e',
    on_shift: true,
    next_visit: 'On call',
    bio: 'Your bedside nurse until 15:00. Press the call button anytime.',
  },
  {
    id: 4,
    name: 'Nurse Aaron Goh',
    role: 'Support Nurse',
    specialty: 'Morning Shift',
    photo_initials: 'AG',
    color: '#0891b2',
    on_shift: true,
    next_visit: 'On call',
    bio: 'Assists with mobility, medications and meal service.',
  },
  {
    id: 5,
    name: 'Sasha Tan',
    role: 'Physiotherapist',
    specialty: 'Respiratory therapy',
    photo_initials: 'ST',
    color: '#d97706',
    on_shift: true,
    next_visit: 'Today, 11:15',
    bio: 'Guides your breathing exercises and gentle mobilisation.',
  },
  {
    id: 6,
    name: 'Sr. Diana Wong',
    role: 'Night Nurse',
    specialty: 'Night Shift',
    photo_initials: 'DW',
    color: '#475569',
    on_shift: false,
    next_visit: 'Tonight, 19:00',
    bio: 'Takes over for the overnight shift starting at 19:00.',
  },
];

export const vitals = {
  recorded_at_label: 'Just now · 10:08',
  pulse_rate: { value: 78, unit: 'bpm', status: 'normal', range: '60–100' },
  blood_pressure: { value: '124/78', unit: 'mmHg', status: 'normal', range: '<130/80' },
  spo2: { value: 97, unit: '%', status: 'normal', range: '≥95' },
  respiratory_rate: { value: 16, unit: '/min', status: 'normal', range: '12–20' },
  temperature: { value: 37.1, unit: '°C', status: 'mild', range: '36.1–37.2' },
  pain_score: { value: 2, unit: '/10', status: 'mild', range: '0–3 mild' },
};

export const medications = [
  {
    id: 'm1',
    name: 'Amoxicillin-Clavulanate 625mg',
    plain_name: 'Antibiotic',
    purpose: 'Clears the lung infection.',
    schedule: '3 times a day · with food',
    next_dose: 'Next at 12:00',
    icon: 'pill',
  },
  {
    id: 'm2',
    name: 'Paracetamol 1g',
    plain_name: 'Fever & pain relief',
    purpose: 'Lowers fever and eases body aches.',
    schedule: 'Every 6 hours if needed',
    next_dose: 'Next at 13:00',
    icon: 'pill',
  },
  {
    id: 'm3',
    name: 'Salbutamol Inhaler',
    plain_name: 'Reliever puffer',
    purpose: 'Opens up the airways to ease breathing.',
    schedule: '2 puffs every 4 hours if short of breath',
    next_dose: 'As needed',
    icon: 'puffer',
  },
  {
    id: 'm4',
    name: 'Pantoprazole 40mg',
    plain_name: 'Stomach protector',
    purpose: 'Protects your stomach while on antibiotics.',
    schedule: 'Once daily, before breakfast',
    next_dose: 'Tomorrow 07:00',
    icon: 'pill',
  },
];

export const recoveryOverview = {
  day_of_stay: 4,
  expected_discharge: '20 Jun (Sat)',
  goals_today: [
    { id: 'g1', label: 'Walk 50m with physiotherapist', done: true },
    { id: 'g2', label: 'Complete antibiotic dose at 12:00', done: false },
    { id: 'g3', label: 'Practice deep breathing 3 sets', done: false },
    { id: 'g4', label: 'Eat at least half of meals', done: true },
  ],
  progress_note:
    'Fever is settling, oxygen levels stable on room air. Keep up the breathing exercises — one more day expected before discharge.',
};

export const roomControls = {
  lighting: {
    brightness: 60,
    scenes: [
      { id: 'reading', label: 'Reading', icon: '📖' },
      { id: 'rest', label: 'Rest', icon: '🌙' },
      { id: 'bright', label: 'Bright', icon: '☀️' },
      { id: 'off', label: 'Off', icon: '⏻' },
    ],
    active_scene: 'reading',
  },
  temperature: {
    target_c: 23,
    current_c: 22.8,
    min: 18,
    max: 26,
  },
  blinds: {
    position: 70, // 0 closed, 100 open
  },
  tv: {
    is_on: false,
    volume: 25,
  },
  do_not_disturb: false,
};

export const dietProfile = {
  diet_type: 'Soft diet · Low sodium',
  restrictions: ['No shellfish (allergy)', 'No raw fish', 'Limit caffeine'],
  fluid_target_ml: 1500,
  fluid_consumed_ml: 950,
};

export const mealMenu = {
  date_label: 'Today · 18 Jun',
  next_meal: 'Lunch',
  next_meal_time: '12:30',
  categories: [
    {
      id: 'mains',
      label: 'Main courses',
      items: [
        {
          id: 'meal1',
          name: 'Steamed chicken with ginger rice',
          kcal: 420,
          tags: ['Soft', 'Low sodium', 'High protein'],
          allowed: true,
          image_color: '#fde68a',
          emoji: '🍚',
          selected: true,
        },
        {
          id: 'meal2',
          name: 'Fish porridge with leafy greens',
          kcal: 310,
          tags: ['Soft', 'Low sodium'],
          allowed: true,
          image_color: '#bae6fd',
          emoji: '🥣',
          selected: false,
        },
        {
          id: 'meal3',
          name: 'Prawn noodles',
          kcal: 480,
          tags: ['Shellfish'],
          allowed: false,
          blocked_reason: 'Shellfish allergy on file',
          image_color: '#fecaca',
          emoji: '🍜',
          selected: false,
        },
        {
          id: 'meal4',
          name: 'Stewed beef with potatoes',
          kcal: 510,
          tags: ['High sodium'],
          allowed: false,
          blocked_reason: 'Exceeds sodium limit on your diet',
          image_color: '#fecaca',
          emoji: '🍲',
          selected: false,
        },
      ],
    },
    {
      id: 'sides',
      label: 'Sides & desserts',
      items: [
        {
          id: 'meal5',
          name: 'Fruit cup (papaya, banana)',
          kcal: 110,
          tags: ['Soft'],
          allowed: true,
          image_color: '#bbf7d0',
          emoji: '🍌',
          selected: true,
        },
        {
          id: 'meal6',
          name: 'Almond jelly',
          kcal: 90,
          tags: ['Soft'],
          allowed: true,
          image_color: '#e9d5ff',
          emoji: '🍮',
          selected: false,
        },
        {
          id: 'meal7',
          name: 'Black coffee',
          kcal: 5,
          tags: ['Caffeine'],
          allowed: false,
          blocked_reason: 'Limit caffeine on your plan',
          image_color: '#fecaca',
          emoji: '☕',
          selected: false,
        },
      ],
    },
    {
      id: 'drinks',
      label: 'Drinks',
      items: [
        {
          id: 'meal8',
          name: 'Warm barley water',
          kcal: 70,
          tags: ['Low sodium'],
          allowed: true,
          image_color: '#fef3c7',
          emoji: '🥤',
          selected: true,
        },
        {
          id: 'meal9',
          name: 'Fresh milk (warm)',
          kcal: 130,
          tags: [],
          allowed: true,
          image_color: '#e0f2fe',
          emoji: '🥛',
          selected: false,
        },
      ],
    },
  ],
};

export const requestCategories = [
  {
    id: 'pain',
    label: 'Pain relief',
    description: 'I need something for pain.',
    routes_to: 'Primary nurse',
    color: '#e11d48',
    emoji: '💊',
    estimated_response: '5 min',
  },
  {
    id: 'bathroom',
    label: 'Bathroom help',
    description: 'I need help getting to the toilet.',
    routes_to: 'Support nurse',
    color: '#0891b2',
    emoji: '🚻',
    estimated_response: '3 min',
  },
  {
    id: 'water',
    label: 'Water / drink',
    description: 'Please refill my water.',
    routes_to: 'Ward assistant',
    color: '#0ea5e9',
    emoji: '💧',
    estimated_response: '6 min',
  },
  {
    id: 'food',
    label: 'Hungry / meal',
    description: 'A meal question or snack request.',
    routes_to: 'Dietary aide',
    color: '#d97706',
    emoji: '🍽️',
    estimated_response: '10 min',
  },
  {
    id: 'breathing',
    label: 'Breathing trouble',
    description: 'I feel short of breath.',
    routes_to: 'Primary nurse (urgent)',
    color: '#b91c1c',
    emoji: '🫁',
    estimated_response: '1 min',
    urgent: true,
  },
  {
    id: 'comfort',
    label: 'Bed / pillow',
    description: 'Adjust my bed or pillows.',
    routes_to: 'Support nurse',
    color: '#7c3aed',
    emoji: '🛏️',
    estimated_response: '5 min',
  },
  {
    id: 'cleanup',
    label: 'Clean up / linen',
    description: 'I need a clean up or fresh sheets.',
    routes_to: 'Housekeeping',
    color: '#475569',
    emoji: '🧺',
    estimated_response: '12 min',
  },
  {
    id: 'talk',
    label: 'Speak to nurse',
    description: 'A non-urgent question for my nurse.',
    routes_to: 'Primary nurse',
    color: '#0f766e',
    emoji: '💬',
    estimated_response: '8 min',
  },
];

export const todaySchedule = [
  {
    id: 's1',
    time: '08:00',
    title: 'Breakfast',
    detail: 'Soft diet · congee with egg',
    type: 'meal',
    status: 'done',
  },
  {
    id: 's2',
    time: '09:00',
    title: 'Morning vitals',
    detail: 'Nurse Maria',
    type: 'check',
    status: 'done',
  },
  {
    id: 's3',
    time: '10:30',
    title: 'Doctor round',
    detail: 'Dr. Rajan Krishnan',
    type: 'doctor',
    status: 'upcoming',
    is_next: true,
  },
  {
    id: 's4',
    time: '11:15',
    title: 'Physiotherapy',
    detail: 'Breathing exercises · Sasha Tan',
    type: 'therapy',
    status: 'upcoming',
  },
  {
    id: 's5',
    time: '12:00',
    title: 'Antibiotic dose',
    detail: 'Amoxicillin-Clavulanate 625mg',
    type: 'med',
    status: 'upcoming',
  },
  {
    id: 's6',
    time: '12:30',
    title: 'Lunch',
    detail: 'Steamed chicken with ginger rice',
    type: 'meal',
    status: 'upcoming',
  },
  {
    id: 's7',
    time: '14:00',
    title: 'Specialist review',
    detail: 'Dr. Chen Hui · Pulmonology',
    type: 'doctor',
    status: 'upcoming',
  },
  {
    id: 's8',
    time: '15:00',
    title: 'Shift change',
    detail: 'Afternoon nurses take over',
    type: 'check',
    status: 'upcoming',
  },
  {
    id: 's9',
    time: '18:00',
    title: 'Dinner',
    detail: 'Fish porridge',
    type: 'meal',
    status: 'upcoming',
  },
];

export const dischargeChecklist = {
  expected_date_label: '20 Jun 2026 (Sat)',
  expected_time_label: 'After 11:00 round',
  steps: [
    {
      id: 'd1',
      label: 'Stable vitals for 24 hours',
      done: true,
      done_at: '17 Jun, 20:00',
      owner: 'Nursing',
    },
    {
      id: 'd2',
      label: 'Final chest X-ray reviewed',
      done: true,
      done_at: '18 Jun, 09:10',
      owner: 'Radiology',
    },
    {
      id: 'd3',
      label: 'Completion of IV antibiotic course',
      done: false,
      owner: 'Pharmacy',
      eta: '19 Jun, 22:00',
    },
    {
      id: 'd4',
      label: 'Discharge medications prepared',
      done: false,
      owner: 'Pharmacy',
      eta: '20 Jun, 09:00',
    },
    {
      id: 'd5',
      label: 'Physician discharge clearance',
      done: false,
      owner: 'Dr. Rajan Krishnan',
      eta: '20 Jun, 11:00',
    },
    {
      id: 'd6',
      label: 'Discharge education & follow-up booking',
      done: false,
      owner: 'Nursing',
      eta: '20 Jun, 11:30',
    },
    {
      id: 'd7',
      label: 'Payment & paperwork',
      done: false,
      owner: 'Front desk',
      eta: '20 Jun, 12:00',
    },
  ],
};

export const notifications = [
  {
    id: 'n1',
    title: 'Dr. Krishnan is on the way',
    body: 'Round starts in about 20 minutes.',
    time_label: '10:08',
    type: 'info',
  },
  {
    id: 'n2',
    title: 'Lab result available',
    body: 'Your blood test from this morning is normal.',
    time_label: '09:42',
    type: 'success',
  },
  {
    id: 'n3',
    title: 'Message from family',
    body: '"See you at 4pm today, hang in there!" — Mum',
    time_label: '08:15',
    type: 'message',
  },
];

export const visitor = {
  visiting_hours: '11:00 – 13:00 · 16:00 – 20:00',
  expected_visitors: [
    { id: 'v1', name: 'Mum (Mary)', eta: 'Today, 16:00' },
    { id: 'v2', name: 'Brother (Jun Hao)', eta: 'Today, 17:30' },
  ],
};

// ------------------------------------------------------------
// Simulated API helpers — wrap reads in a Promise so screens
// can be migrated to `await api.getPatient()` later without
// changing call sites.
// ------------------------------------------------------------
function ok(data, ms = 0) {
  return new Promise((resolve) => setTimeout(() => resolve(data), ms));
}

export const api = {
  getPatient: () => ok(patient),
  getCareTeam: () => ok(careTeam),
  getVitals: () => ok(vitals),
  getMedications: () => ok(medications),
  getRecoveryOverview: () => ok(recoveryOverview),
  getRoomControls: () => ok(roomControls),
  getDietProfile: () => ok(dietProfile),
  getMealMenu: () => ok(mealMenu),
  getRequestCategories: () => ok(requestCategories),
  getTodaySchedule: () => ok(todaySchedule),
  getDischargeChecklist: () => ok(dischargeChecklist),
  getNotifications: () => ok(notifications),
  getVisitor: () => ok(visitor),

  // Write actions — currently no-ops. Wire to POST endpoints later.
  submitRequest: (categoryId) =>
    ok({ ok: true, ticket_id: `REQ-${Date.now()}`, categoryId }, 300),
  setLightingScene: (sceneId) => ok({ ok: true, sceneId }, 150),
  setLightingBrightness: (value) => ok({ ok: true, value }, 50),
  setTemperature: (value_c) => ok({ ok: true, value_c }, 100),
  setBlinds: (position) => ok({ ok: true, position }, 50),
  toggleTv: (is_on) => ok({ ok: true, is_on }, 100),
  setTvVolume: (volume) => ok({ ok: true, volume }, 50),
  toggleDoNotDisturb: (value) => ok({ ok: true, value }, 100),
  selectMeal: (mealId) => ok({ ok: true, mealId }, 100),
};
