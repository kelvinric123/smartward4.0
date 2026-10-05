// Times in the demo data. The sample patients were written as if it were
// 16 Jun, 10:00; each demo session moves that moment to "now", so the vital
// signs, the oxygen, the I/O, the orders and the labs always look current and
// line up with each other, the way the server's records do.

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const pad = (n) => String(n).padStart(2, '0');

// When the demo data was written (the year does not matter, only the gaps)
const WRITTEN_AT = Date.UTC(2026, 5, 16, 10, 0);

let startedAt = null;

/** "Now" for this demo session, fixed when first asked so every time agrees. */
export function demoNow() {
  if (startedAt === null) startedAt = Date.now();
  return startedAt;
}

/** Forget the session's "now" (on logout), so the next demo starts current again. */
export function resetDemoClock() {
  startedAt = null;
}

/** The moment a time written in the demo data stands for: "16 Jun 05:00", or "09:42" on 16 Jun. */
export function demoTime(label) {
  const m = String(label ?? '').trim().match(/^(?:(\d{1,2}) ([A-Za-z]{3}) )?(\d{1,2}):(\d{2})$/);
  if (!m) return new Date(demoNow());
  const month = m[2] ? MONTHS.indexOf(m[2][0].toUpperCase() + m[2].slice(1).toLowerCase()) : 5;
  const written = Date.UTC(2026, month, m[1] ? Number(m[1]) : 16, Number(m[3]), Number(m[4]));
  return new Date(demoNow() - (WRITTEN_AT - written));
}

/** "05 Oct 23:42", as the server labels times. */
export function stampOf(date) {
  return `${pad(date.getDate())} ${MONTHS[date.getMonth()]} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

/** "23:42" */
export function clockOf(date) {
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
}
