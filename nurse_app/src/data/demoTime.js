// Time, volume and shift helpers shared by the demo chart (mockPatient.js)
// and the demo nursing plan (mockNursingPlan.js), formatted the way the
// server formats them.

export const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
export const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
export const pad = (n) => String(n).padStart(2, '0');
export const hm = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;
export const dm = (d) => `${pad(d.getDate())} ${MONTHS[d.getMonth()]}`;
export const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const ago = (minutes) => new Date(Date.now() - minutes * 60000);
export const ahead = (minutes) => new Date(Date.now() + minutes * 60000);
export const sameDay = (a, b) => a.toDateString() === b.toDateString();

/** "14:05" today, "Yest 14:05", or "21 Sep 14:05". */
export function when(d) {
  const now = new Date();
  if (sameDay(d, now)) return hm(d);
  const y = new Date(now);
  y.setDate(now.getDate() - 1);
  return `${sameDay(d, y) ? 'Yest' : dm(d)} ${hm(d)}`;
}

export const thousands = (n) => String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
export const ml = (n) => `${thousands(n)} mL`;
export const balance = (n) => (n > 0 ? `+${ml(n)}` : n < 0 ? `-${ml(-n)}` : '0 mL');

// The demo ward's shifts: the server's defaults (ShiftSetting::getDefaults),
// the night running past midnight
export const SHIFTS = [
  { code: 'AM', name: 'Morning', from: 7, to: 14 },
  { code: 'PM', name: 'Afternoon', from: 14, to: 23 },
  { code: 'ON', name: 'Night', from: 23, to: 31 },
];

/** The shift on now: its code, name, start and end, and the roster date it belongs to. */
export function currentShift(now = new Date()) {
  const hour = now.getHours();
  const shift = hour >= 7 && hour < 14 ? SHIFTS[0] : hour >= 14 && hour < 23 ? SHIFTS[1] : SHIFTS[2];
  const start = new Date(now);
  start.setHours(shift.from, 0, 0, 0);
  if (shift.code === 'ON' && hour < 7) start.setDate(start.getDate() - 1); // began last night
  const end = new Date(start.getTime() + (shift.to - shift.from) * 3600000);
  return { ...shift, start, end, date: ymd(start), time: `${pad(shift.from)}:00 - ${pad(shift.to % 24)}:00` };
}

/**
 * "PM", "AM yesterday", "ON 21 Sep": a shift code with its date, as ShiftHandover::label.
 * The shift on now counts from the day it started, so after midnight the night on duty reads "ON".
 */
export function shiftLabel(code, date) {
  const on = currentShift();
  const from = new Date(code === on.code ? on.start : Date.now());
  from.setHours(0, 0, 0, 0);
  const [y, m, d] = date.split('-').map(Number);
  const day = new Date(y, m - 1, d);
  const offset = Math.round((day - from) / 86400000);
  if (offset === 0) return code;
  if (offset === 1) return `${code} tomorrow`;
  if (offset === -1) return `${code} yesterday`;
  return `${code} ${day.getDate()} ${MONTHS[day.getMonth()]}`;
}
