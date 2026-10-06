// Shift handover store, backed by the Laravel API.
//
//   GET  {BASE_URL}/api/nurse/handovers
//   POST {BASE_URL}/api/nurse/handovers          body: { to_nurse_id?, items: [...] }
//   POST {BASE_URL}/api/nurse/handovers/receive  body: { ids: [...] }
//
// Every shift, following the ward roster, the outgoing nurse passes each
// patient's condition and nursing plan to the nurse rostered on the next
// shift; the counterpart receives (acknowledges) it. The server scopes the
// state to the current shift change. The latest state is cached in memory and
// subscribers are notified on every change.

import { fetchHandovers, postHandovers, postReceiveHandovers } from '../api/endpoints';
import * as mock from './mockData';

export const CONDITION_STATUSES = [
  { key: 'stable', label: 'Stable' },
  { key: 'improving', label: 'Improving' },
  { key: 'deteriorating', label: 'Deteriorating' },
  { key: 'critical', label: 'Critical' },
];

const EMPTY_STATE = {
  from_shift: null,
  to_shift: null,
  patients: [],
  nurses: [],
  suggested_receivers: [],
  outgoing: [],
  incoming: [],
};

const listeners = new Set();
let state = EMPTY_STATE;

// Demo mode ("Demo Login"): handovers live only in memory, seeded with
// samples, and nothing touches the server.
let demoMode = false;
let demoNurse = null;

export function setDemoMode(on, nurse) {
  demoMode = !!on;
  demoNurse = nurse ?? null;
  state = demoMode ? demoSeed() : EMPTY_STATE;
  emit();
}

export function getHandoverState() {
  return state;
}

/**
 * Refresh the handover state from the server. Emits on success.
 * No-op in demo mode. Throws on error so callers can surface it.
 */
export async function loadHandovers() {
  if (demoMode) return state;
  const data = await fetchHandovers();
  setState(data);
  return state;
}

/**
 * Hand over patients to the next shift.
 * items: [{ patient_id, condition_status, patient_condition, nursing_plan }]
 * toNurseId: null = the nurse scheduled on each bed next shift (auto).
 */
export async function submitHandovers({ toNurseId, items }) {
  if (!items?.length) return state;

  if (demoMode) {
    const now = new Date().toISOString();
    const receiverFor = (patientId) => {
      if (toNurseId != null) return state.nurses.find((n) => n.id === toNurseId) ?? null;
      const s = state.suggested_receivers.find((r) => r.patient_id === patientId);
      return s?.nurse ?? null;
    };
    let outgoing = [...state.outgoing];
    items.forEach((item) => {
      const fields = {
        to_nurse: receiverFor(item.patient_id),
        condition_status: item.condition_status ?? null,
        patient_condition: item.patient_condition?.trim() || null,
        nursing_plan: item.nursing_plan?.trim() || null,
        ews: item.ews ?? null,
        updated_at: now,
      };
      const idx = outgoing.findIndex((h) => h.patient_id === item.patient_id && h.status === 'pending');
      if (idx >= 0) {
        outgoing[idx] = { ...outgoing[idx], ...fields };
      } else {
        outgoing = [
          {
            id: `demo-out-${item.patient_id}-${Date.now()}`,
            patient_id: item.patient_id,
            patient_name: item.patient_name ?? null,
            mrn: item.mrn ?? null,
            bed_number: item.bed_number ?? null,
            ward_name: item.ward_name ?? null,
            from_nurse: demoNurse ? { id: demoNurse.id, name: demoNurse.name } : null,
            from_shift: state.from_shift?.shift_code ?? null,
            to_shift: state.to_shift?.shift_code ?? null,
            status: 'pending',
            received_by: null,
            received_at: null,
            created_at: now,
            ...fields,
          },
          ...outgoing,
        ];
      }
    });
    state = { ...state, outgoing };
    emit();
    return state;
  }

  const data = await postHandovers({
    toNurseId,
    items: items.map((i) => ({
      patient_id: i.patient_id,
      condition_status: i.condition_status ?? null,
      patient_condition: i.patient_condition ?? null,
      nursing_plan: i.nursing_plan ?? null,
    })),
  });
  setState(data);
  return state;
}

/**
 * Mark incoming handovers as received by this nurse.
 */
export async function receiveHandovers(ids) {
  if (!ids?.length) return state;

  if (demoMode) {
    const now = new Date().toISOString();
    const me = demoNurse ? { id: demoNurse.id, name: demoNurse.name } : null;
    state = {
      ...state,
      incoming: state.incoming.map((h) =>
        ids.includes(h.id) && h.status === 'pending'
          ? { ...h, status: 'received', received_by: me, received_at: now }
          : h
      ),
    };
    emit();
    return state;
  }

  const data = await postReceiveHandovers(ids);
  setState(data);
  return state;
}

export function subscribe(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

// Latest outgoing handover per patient id.
export function latestOutgoingByPatient(outgoing) {
  const map = new Map();
  (outgoing ?? []).forEach((h) => {
    const prev = map.get(h.patient_id);
    if (!prev || (h.created_at ?? '') > (prev.created_at ?? '')) map.set(h.patient_id, h);
  });
  return map;
}

export function pendingIncomingCount(s = state) {
  return (s.incoming ?? []).filter((h) => h.status === 'pending').length;
}

// "Morning → Afternoon" and when the receiving shift starts (or started,
// when handing over after the end of the shift).
export function shiftChangeLabels(fromShift, toShift) {
  const name = (s) => s?.shift_name ?? s?.shift_code;
  const title = fromShift && toShift
    ? `${name(fromShift)} → ${name(toShift)}`
    : toShift
      ? `To ${name(toShift)} shift`
      : 'Pass over to the next shift';
  const when = toShift?.starts_at_label
    ? toShift.started
      ? `${name(toShift)} shift started ${toShift.starts_at_label}`
      : `Next shift starts ${toShift.starts_at_label}`
    : null;
  return { title, when };
}

export function formatTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const pad = (n) => String(n).padStart(2, '0');
  return `${pad(d.getDate())} ${months[d.getMonth()]} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function setState(data) {
  state = {
    from_shift: data?.from_shift ?? null,
    to_shift: data?.to_shift ?? null,
    patients: data?.patients ?? [],
    nurses: data?.nurses ?? [],
    suggested_receivers: data?.suggested_receivers ?? [],
    outgoing: data?.outgoing ?? [],
    incoming: data?.incoming ?? [],
  };
  emit();
}

function emit() {
  listeners.forEach((fn) => {
    try { fn(); } catch (e) {}
  });
}

function isoMinusMinutes(m) {
  return new Date(Date.now() - m * 60000).toISOString();
}

function nextShiftLabel() {
  const d = new Date();
  if (d.getHours() >= 14) d.setDate(d.getDate() + 1);
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return `${String(d.getDate()).padStart(2, '0')} ${months[d.getMonth()]} 14:00`;
}

// Keyed by the mock patients' ids (see src/data/mockData.js)
function demoSeed() {
  const nightNurse = { id: 12, name: 'Sr. Aisyah Rahman' };
  const me = demoNurse ? { id: demoNurse.id, name: demoNurse.name } : null;
  return {
    from_shift: { shift_code: 'AM', shift_name: 'Morning' },
    to_shift: { shift_code: 'PM', shift_name: 'Afternoon', starts_at: null, starts_at_label: nextShiftLabel(), started: false },
    patients: mock.assignedBeds.filter((b) => b.patient_id != null),
    nurses: [
      { id: 12, name: 'Sr. Aisyah Rahman', designation: 'STAFF NURSE I', on_next_shift: false },
      { id: 13, name: 'Sr. Daniel Wong', designation: 'STAFF NURSE II', on_next_shift: false },
      { id: 10, name: 'Sr. Hannah Tan', designation: 'SENIOR STAFF NURSE II', on_next_shift: true },
      { id: 11, name: 'Sr. Priya Devi', designation: 'STAFF NURSE I', on_next_shift: true },
    ],
    suggested_receivers: [
      { patient_id: 5001, nurse: { id: 10, name: 'Sr. Hannah Tan' } },
      { patient_id: 5002, nurse: { id: 10, name: 'Sr. Hannah Tan' } },
      { patient_id: 5003, nurse: { id: 11, name: 'Sr. Priya Devi' } },
      { patient_id: 5004, nurse: { id: 11, name: 'Sr. Priya Devi' } },
    ],
    outgoing: [],
    incoming: [
      {
        id: 'demo-in-5003',
        patient_id: 5003,
        patient_name: 'Rajesh Kumar',
        mrn: 'MRN-205044',
        bed_number: '13A',
        ward_name: 'Ward 3A - Medical',
        from_nurse: nightNurse,
        to_nurse: me,
        from_shift: 'ON',
        to_shift: 'AM',
        condition_status: 'critical',
        patient_condition:
          'Septic, febrile 39.1°C overnight. Tachycardic 120s, BP trending down to 92/58. SpO2 89-90% on 4L NC. EWS 7, MET informed 05:30.',
        nursing_plan:
          'Hourly vitals + strict I/O. Repeat lactate at 10:00. Vancomycin running, insulin infusion paused (check HGT before restarting). Back from CT ~10:00.',
        ews: 7,
        status: 'pending',
        received_by: null,
        received_at: null,
        created_at: isoMinusMinutes(150),
      },
      {
        id: 'demo-in-5001',
        patient_id: 5001,
        patient_name: 'Tan Wei Ming',
        mrn: 'MRN-204815',
        bed_number: '12A',
        ward_name: 'Ward 3A - Medical',
        from_nurse: nightNurse,
        to_nurse: me,
        from_shift: 'ON',
        to_shift: 'AM',
        condition_status: 'deteriorating',
        patient_condition:
          'CAP day 3. Spiking temp 38.4°C, PR 110s. On noradrenaline 6.5 mL/hr, pump alarming at 06:40 — line flushed.',
        nursing_plan:
          'Contact precautions. Wean noradrenaline per MAP target. Fall risk high — bed rails up, assist to toilet. Diabetic diet, HGT QID.',
        ews: 5,
        status: 'pending',
        received_by: null,
        received_at: null,
        created_at: isoMinusMinutes(155),
      },
    ],
  };
}
