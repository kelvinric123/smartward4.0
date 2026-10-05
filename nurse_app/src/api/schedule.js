// Roster API: the nurse's own roster from the AI Nurse Schedule, leave and
// shift-swap requests, and the ward team on shift (NurseAppRosterController).
//
// Every roster action answers { success, message, roster }, where `roster` is
// the whole refreshed roster, so the screen simply redraws from it.
//
//   GET  /api/nurse/roster?week=Y-m-d&weeks=2       -> { roster }
//   POST /api/nurse/roster/acknowledge             { week }
//   GET  /api/nurse/roster/colleagues?date=Y-m-d    -> { date, shift, colleagues: [{ id, name, shift, on_leave }] }
//   POST /api/nurse/requests                       { type: 'leave', leave_type, start_date, end_date, note? }
//                                                  { type: 'swap', shift_date, colleague_id, colleague_shift?, note? }
//   POST /api/nurse/requests/{r}/cancel
//   POST /api/nurse/requests/{r}/respond           { accept, note? }
//   GET  /api/nurse/team                            -> { team: { ward, current, next } }
//
// A leave request goes to the nurse manager. A swap goes to the colleague
// first; once they accept, to the nurse manager, who approves it on the ward
// dashboard's AI Nurse Schedule.

import { apiFetch } from './endpoints';
import { createDemoScheduleClient } from '../data/mockSchedule';

function post(path, body) {
  return apiFetch(path, { method: 'POST', body: body ?? {} });
}

function createLiveScheduleClient() {
  // The week shown travels with each action, so the answer redraws the same weeks
  let shown = null;
  const withWeek = (body) => ({ ...(body ?? {}), week_shown: shown });

  return {
    loadRoster: ({ week } = {}) => {
      shown = week ?? null;
      return apiFetch(`/api/nurse/roster${week ? `?week=${encodeURIComponent(week)}` : ''}`);
    },
    acknowledge: (week) => post('/api/nurse/roster/acknowledge', withWeek({ week })),
    colleagues: (date) => apiFetch(`/api/nurse/roster/colleagues?date=${encodeURIComponent(date)}`),
    requestLeave: (body) => post('/api/nurse/requests', withWeek({ type: 'leave', ...body })),
    requestSwap: (body) => post('/api/nurse/requests', withWeek({ type: 'swap', ...body })),
    cancelRequest: (id) => post(`/api/nurse/requests/${id}/cancel`, withWeek()),
    respondRequest: (id, body) => post(`/api/nurse/requests/${id}/respond`, withWeek(body)),
    loadTeam: () => apiFetch('/api/nurse/team'),
  };
}

/** The server for a real session, or the demo roster for a demo login. */
export function createScheduleClient(session) {
  return session?.demo ? createDemoScheduleClient() : createLiveScheduleClient();
}
