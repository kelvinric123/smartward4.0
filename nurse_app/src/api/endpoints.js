// API client for the QMed Smart Ward Laravel backend.
//
// Auth model: nurse logs in -> receives a token -> all subsequent calls
// include `Authorization: Bearer <token>`. The backend scopes the response to
// the beds assigned to that nurse (current-shift schedule assignments plus
// patients where the nurse is the primary nurse).
//
// Laravel endpoints (routes/web.php, NurseAppApiController):
//
//   POST  {BASE_URL}/api/nurse/login
//         body:  { username, password }
//         resp:  { success, token, nurse: { id, name, designation, ward_id } }
//
//   GET   {BASE_URL}/api/nurse/dashboard
//         resp:  { nurse, current_shift, ward, summary, beds: [...] }
//
//   GET   {BASE_URL}/api/nurse/handovers
//         resp:  { from_shift, to_shift, patients: [...], nurses,
//                  suggested_receivers, outgoing: [...], incoming: [...] }
//         Scoped per shift following the ward roster: `patients` are the
//         ones this nurse hands over (current shift, or the shift that just
//         ended), `outgoing` what they sent for that shift change and
//         `incoming` handovers into their current or upcoming shift.
//   POST  {BASE_URL}/api/nurse/handovers
//         body:  { to_nurse_id?, items: [{ patient_id, condition_status,
//                  patient_condition, nursing_plan }] }
//   POST  {BASE_URL}/api/nurse/handovers/receive   body: { ids: [...] }
//         (both POSTs respond with the refreshed handover state)
//
//   POST  {BASE_URL}/api/nurse/logout
//   POST  {BASE_URL}/api/nurse/ping

import { getBaseUrl, ensureConfigLoaded } from '../config';

export const API_TIMEOUT_MS = 15000;

// Current session token, set on login.
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
  return apiFetch('/api/nurse/ping', { method: 'POST' });
}

export async function loginNurse({ username, password }) {
  const data = await apiFetch('/api/nurse/login', {
    method: 'POST',
    body: { username, password },
  });
  setSessionToken(data.token);
  return {
    token: data.token,
    nurse: data.nurse,
  };
}

export async function fetchNurseDashboard(token) {
  return apiFetch('/api/nurse/dashboard', { token });
}

export async function fetchHandovers() {
  return apiFetch('/api/nurse/handovers');
}

export async function postHandovers({ toNurseId, items }) {
  return apiFetch('/api/nurse/handovers', {
    method: 'POST',
    body: { to_nurse_id: toNurseId ?? null, items },
  });
}

export async function postReceiveHandovers(ids) {
  return apiFetch('/api/nurse/handovers/receive', {
    method: 'POST',
    body: { ids },
  });
}

export async function logout(token) {
  try {
    await apiFetch('/api/nurse/logout', { method: 'POST', token });
  } catch (e) {
    // Logout is best-effort; clearing the local session is what matters.
  }
  setSessionToken(null);
  return { ok: true };
}
