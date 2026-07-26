// API client for the QMed Smart Ward Laravel backend.
//
// The patient app is a bedside companion: no user login. Staff pick a ward
// and a bed on the connect screen, then the app polls that bed's snapshot
// for the live patient profile, care team and vitals. It reuses the public
// terminal API also used by the bedside Patient Information Terminal.
//
// Laravel endpoints (routes/web.php, TerminalApiController):
//
//   GET  {BASE_URL}/api/terminal/ping
//   GET  {BASE_URL}/api/terminal/wards
//        resp: { success, wards: [{ id, ward_name, ward_code }] }
//   GET  {BASE_URL}/api/terminal/beds?ward_id={id}
//        resp: { success, beds: [{ id, bed_number, display_name, status, patient_name }] }
//   GET  {BASE_URL}/api/terminal/beds/{bed}/snapshot
//        resp: { success, occupied, bed, patient, care_team, vitals }
//
//   GET  {BASE_URL}/api/terminal/beds/{bed}/requests
//   POST {BASE_URL}/api/terminal/beds/{bed}/requests
//        body: { category, label, urgent }
//        Smart calls surface in the ward dashboard's Ward Notifications
//        panel; when a nurse taps Respond there, the request comes back
//        with responded=true plus who responded and when.

import { getBaseUrl, ensureConfigLoaded } from '../config';

export const API_TIMEOUT_MS = 15000;

async function apiFetch(path, { method = 'GET', body } = {}) {
  await ensureConfigLoaded();
  const url = `${getBaseUrl()}${path}`;
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), API_TIMEOUT_MS);

  let res;
  try {
    res = await fetch(url, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      body: body != null ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (e) {
    const aborted = e?.name === 'AbortError';
    const err = new Error(
      aborted
        ? 'Server did not respond. Check the API path in Settings.'
        : 'Cannot reach the server. Check your network and the API path in Settings.'
    );
    err.cause = e;
    throw err;
  } finally {
    clearTimeout(timer);
  }

  let data = null;
  try {
    data = await res.json();
  } catch (e) {
    // Non-JSON response (e.g. HTML error page)
  }

  if (!res.ok) {
    const err = new Error(
      data?.message ?? `Request failed (HTTP ${res.status}).`
    );
    err.status = res.status;
    err.data = data;
    throw err;
  }

  return data;
}

export async function pingServer() {
  return apiFetch('/api/terminal/ping');
}

export async function fetchWards() {
  const data = await apiFetch('/api/terminal/wards');
  return data?.wards ?? [];
}

export async function fetchBeds(wardId) {
  const data = await apiFetch(`/api/terminal/beds?ward_id=${encodeURIComponent(wardId)}`);
  return data?.beds ?? [];
}

export async function fetchBedSnapshot(bedId) {
  return apiFetch(`/api/terminal/beds/${encodeURIComponent(bedId)}/snapshot`);
}

export async function fetchPatientRequests(bedId) {
  const data = await apiFetch(`/api/terminal/beds/${encodeURIComponent(bedId)}/requests`);
  return data?.requests ?? [];
}

export async function submitPatientRequest(bedId, { category, label, urgent = false }) {
  const data = await apiFetch(`/api/terminal/beds/${encodeURIComponent(bedId)}/requests`, {
    method: 'POST',
    body: { category, label, urgent },
  });
  return data?.request ?? null;
}
