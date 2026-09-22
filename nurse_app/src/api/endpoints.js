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
//   Patient chart (orders, I/O, doses, infusions, alerts, stay timeline):
//   see src/api/patient.js.
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

export async function apiFetch(path, { method = 'GET', body, token } = {}) {
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
        ? `Server did not respond at ${getBaseUrl()}. Check the API path in Settings.`
        : `Cannot reach the server at ${getBaseUrl()}. Check your network and the API path in Settings.`
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
    // Validation errors (422) carry one message per field; the first one is
    // the clearest thing to show on a phone.
    const fieldErrors = data?.errors ? Object.values(data.errors).flat() : [];
    const err = new Error(
      fieldErrors[0] ?? data?.message ?? `Request failed (HTTP ${res.status}).`
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

export async function logout(token) {
  try {
    await apiFetch('/api/nurse/logout', { method: 'POST', token });
  } catch (e) {
    // Logout is best-effort; clearing the local session is what matters.
  }
  setSessionToken(null);
  return { ok: true };
}
