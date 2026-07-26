// Consultant notes store, backed by the Laravel API and keyed by patient id.
//
//   GET  {BASE_URL}/api/doctor/patients/{patientId}/notes
//   POST {BASE_URL}/api/doctor/patients/{patientId}/notes  body: { text }
//
// A small in-memory cache keeps the UI snappy; loadNotes() refreshes it from
// the server and notifies subscribers.

import { fetchNotes, postNote } from '../api/endpoints';

const listeners = new Set();
const notesByPatient = new Map();

// Demo mode ("Demo Login"): notes live only in memory, seeded with samples,
// and nothing touches the server.
let demoMode = false;

export function setDemoMode(on) {
  demoMode = !!on;
  notesByPatient.clear();
  if (demoMode) {
    // Keyed by the mock patients' ids (see src/data/mockData.js)
    notesByPatient.set(5001, [
      { id: 'n-5001-1', text: 'Continue IV antibiotics. Repeat CXR tomorrow. Watch SpO2 trend overnight.', author: 'Dr. Rajan Krishnan', created_at: isoMinusHours(8) },
      { id: 'n-5001-2', text: 'Discussed plan with family. They are aware of escalation plan.', author: 'Dr. Rajan Krishnan', created_at: isoMinusHours(2) },
    ]);
    notesByPatient.set(5003, [
      { id: 'n-5003-1', text: 'For early MET review if EWS rises. Repeat lactate in 2 hours.', author: 'Dr. Rajan Krishnan', created_at: isoMinusHours(3) },
    ]);
  }
  emit();
}

export function getNotes(patientId) {
  return notesByPatient.get(patientId) ?? [];
}

/**
 * Refresh notes for a patient from the server. Emits on success.
 * No-op in demo mode.
 */
export async function loadNotes(patientId) {
  if (patientId == null || demoMode) return;
  try {
    const notes = await fetchNotes(patientId);
    notesByPatient.set(patientId, notes);
    emit();
  } catch (e) {
    // Keep whatever is cached; the section simply shows stale/empty data.
  }
}

/**
 * Persist a new note. Server-backed normally; in-memory only in demo mode.
 * Resolves true on success, throws on error.
 */
export async function addNote(patientId, text, author) {
  const cleaned = (text ?? '').trim();
  if (!cleaned || patientId == null) return null;

  if (demoMode) {
    const note = {
      id: `n-${patientId}-${Date.now()}`,
      text: cleaned,
      author: author ?? 'Consultant',
      created_at: new Date().toISOString(),
    };
    notesByPatient.set(patientId, [note, ...getNotes(patientId)]);
    emit();
    return true;
  }

  const notes = await postNote(patientId, cleaned);
  notesByPatient.set(patientId, notes);
  emit();
  return true;
}

export function subscribe(fn) {
  listeners.add(fn);
  return () => listeners.delete(fn);
}

function emit() {
  listeners.forEach((fn) => {
    try { fn(); } catch (e) {}
  });
}

export function formatRelative(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const pad = (n) => String(n).padStart(2, '0');
  return `${days[d.getDay()]}, ${pad(d.getDate())} ${months[d.getMonth()]} · ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
