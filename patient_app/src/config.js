// App configuration store.
//
// The API base URL points at the QMed Smart Ward Laravel server and can be
// changed from the connect screen's settings dialog (gear icon, config
// password protected). It persists across app restarts via AsyncStorage.

import AsyncStorage from '@react-native-async-storage/async-storage';

export const DEFAULT_BASE_URL = 'http://192.168.0.88:18080';

// Password required to open the settings dialog on the connect screen.
export const SETTINGS_PASSWORD = '1324';

const STORAGE_KEY = 'smartward.patient.baseUrl';
const DEVICE_KEY = 'smartward.patient.device';

let cachedBaseUrl = DEFAULT_BASE_URL;
// Pairing with a bed (POST /api/terminal/pair, nurse login):
// { token, bedId, baseUrl, pairedBy }. The token is only good for that bed
// on that server; without it the server shows the patient's initials only.
let cachedDevice = null;
let loadPromise = null;

export function normalizeBaseUrl(url) {
  let u = (url ?? '').trim();
  if (!u) return DEFAULT_BASE_URL;
  if (!/^https?:\/\//i.test(u)) u = `http://${u}`;
  return u.replace(/\/+$/, '');
}

/**
 * Load the persisted base URL into the in-memory cache.
 * Safe to call multiple times; only reads storage once.
 */
export function ensureConfigLoaded() {
  if (!loadPromise) {
    loadPromise = Promise.all([AsyncStorage.getItem(STORAGE_KEY), AsyncStorage.getItem(DEVICE_KEY)])
      .then(([stored, device]) => {
        if (stored) cachedBaseUrl = normalizeBaseUrl(stored);
        if (device) cachedDevice = JSON.parse(device);
      })
      .catch(() => {});
  }
  return loadPromise;
}

export function getBaseUrl() {
  return cachedBaseUrl;
}

export async function setBaseUrl(url) {
  cachedBaseUrl = normalizeBaseUrl(url);
  try {
    await AsyncStorage.setItem(STORAGE_KEY, cachedBaseUrl);
  } catch (e) {
    // Non-fatal: the URL still applies for this session.
  }
  return cachedBaseUrl;
}

export async function resetBaseUrl() {
  return setBaseUrl(DEFAULT_BASE_URL);
}

/** This device's pairing with the current server, or null. */
export function getDevicePairing() {
  return cachedDevice && cachedDevice.baseUrl === cachedBaseUrl ? cachedDevice : null;
}

export async function setDevicePairing(pairing) {
  cachedDevice = pairing;
  try {
    if (pairing) {
      await AsyncStorage.setItem(DEVICE_KEY, JSON.stringify(pairing));
    } else {
      await AsyncStorage.removeItem(DEVICE_KEY);
    }
  } catch (e) {
    // Non-fatal: the pairing still applies for this session.
  }
}
