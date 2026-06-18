// In-memory consultant notes store. Notes are keyed by bed id.
//
// For showcase build only — notes are lost when the app restarts. When wiring
// the Laravel backend, replace the implementation with:
//
//   GET  {BASE_URL}/beds/{bedId}/consultant-notes
//   POST {BASE_URL}/beds/{bedId}/consultant-notes  body: { text }
//
// Or persist locally via @react-native-async-storage/async-storage.

const listeners = new Set();
const notesByBed = new Map();

// Seed a couple of demo notes so the showcase has something to render.
seed(101, [
  { id: 'n-101-1', text: 'Continue IV antibiotics. Repeat CXR tomorrow. Watch SpO2 trend overnight.', author: 'Dr. Rajan Krishnan', created_at: isoMinusHours(8) },
  { id: 'n-101-2', text: 'Discussed plan with family. They are aware of escalation plan.', author: 'Dr. Rajan Krishnan', created_at: isoMinusHours(2) },
]);
seed(103, [
  { id: 'n-103-1', text: 'For early MET review if EWS rises. Repeat lactate in 2 hours.', author: 'Dr. Rajan Krishnan', created_at: isoMinusHours(3) },
]);

export function getNotes(bedId) {
  return notesByBed.get(bedId) ?? [];
}

export function addNote(bedId, text, author) {
  const cleaned = (text ?? '').trim();
  if (!cleaned) return null;
  const note = {
    id: `n-${bedId}-${Date.now()}`,
    text: cleaned,
    author: author ?? 'Consultant',
    created_at: new Date().toISOString(),
  };
  const next = [note, ...getNotes(bedId)];
  notesByBed.set(bedId, next);
  emit();
  return note;
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

function seed(bedId, notes) {
  notesByBed.set(bedId, notes);
}

function isoMinusHours(h) {
  return new Date(Date.now() - h * 3600 * 1000).toISOString();
}

export function formatRelative(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const pad = (n) => String(n).padStart(2, '0');
  return `${days[d.getDay()]}, ${pad(d.getDate())} ${months[d.getMonth()]} · ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
