// API client for the QMed Smart Ward Laravel backend.
//
// Auth model: consultant logs in -> receives a token -> all subsequent calls
// include `Authorization: Bearer <token>`. The backend scopes the response to
// the patients (and wards) under that consultant's care.
//
// Laravel endpoints (routes/web.php, DoctorAppApiController):
//
//   POST  {BASE_URL}/api/doctor/login
//         body:  { username, password }
//         resp:  { success, token, doctor: { id, name, title, specialty, mmc }, wards: [...] }
//
//   GET   {BASE_URL}/api/doctor/dashboard
//         resp:  { doctor, summary, wards: [...], beds: [...] }
//
//   GET   {BASE_URL}/api/doctor/patients/{patientId}/notes
//   POST  {BASE_URL}/api/doctor/patients/{patientId}/notes   body: { text }
//
//   POST  {BASE_URL}/api/doctor/logout
//   GET   {BASE_URL}/api/doctor/ping

import { getBaseUrl, ensureConfigLoaded } from '../config';

export const API_TIMEOUT_MS = 15000;

// Current session token, set on login so other modules (notes store) can
// call the API without threading the token everywhere.
let sessionToken = null;

export function setSessionToken(token) {
  sessionToken = token;
}

export function getSessionToken() {
  return sessionToken;
}

async function apiFetch(path, { method = 'GET', body, token } = {}) {
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
        ...(token || sessionToken
          ? { Authorization: `Bearer ${token ?? sessionToken}` }
          : {}),
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
  return apiFetch('/api/doctor/ping', { method: 'POST' });
}

export async function loginConsultant({ username, password }) {
  const data = await apiFetch('/api/doctor/login', {
    method: 'POST',
    body: { username, password },
  });
  setSessionToken(data.token);
  return {
    token: data.token,
    doctor: data.doctor,
    wards: data.wards ?? [],
  };
}

export async function fetchDoctorDashboard(doctorId, token) {
  return apiFetch('/api/doctor/dashboard', { token });
}

export async function fetchNotes(patientId) {
  const data = await apiFetch(`/api/doctor/patients/${patientId}/notes`);
  return data?.notes ?? [];
}

export async function postNote(patientId, text) {
  const data = await apiFetch(`/api/doctor/patients/${patientId}/notes`, {
    method: 'POST',
    body: { text },
  });
  return data?.notes ?? [];
}

// Patient chart (DoctorAppPatientController): the I/O chart, medications and
// consultant orders. Every call answers { success, message, chart }, where
// `chart` is the whole refreshed chart, so the screen redraws from it.
//
//   GET  /api/doctor/patients/{id}/chart?io_day=Y-m-d
//   POST /api/doctor/patients/{id}/orders               { instruction, urgency, fluid_limit_ml?, urine_min_ml_per_hour? }
//   POST /api/doctor/patients/{id}/orders/{o}/cancel    { reason }

export async function fetchPatientChart(patientId, { ioDay } = {}) {
  const query = ioDay ? `?io_day=${encodeURIComponent(ioDay)}` : '';
  return apiFetch(`/api/doctor/patients/${patientId}/chart${query}`);
}

export async function createOrder(patientId, body) {
  return apiFetch(`/api/doctor/patients/${patientId}/orders`, { method: 'POST', body });
}

export async function cancelOrder(patientId, orderId, reason) {
  return apiFetch(`/api/doctor/patients/${patientId}/orders/${orderId}/cancel`, {
    method: 'POST',
    body: { reason },
  });
}

export async function logout(token) {
  try {
    await apiFetch('/api/doctor/logout', { method: 'POST', token });
  } catch (e) {
    // Logout is best-effort; clearing the local session is what matters.
  }
  setSessionToken(null);
  return { ok: true };
}
