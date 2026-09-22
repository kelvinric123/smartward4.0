// App configuration store.
//
// The API base URL points at the QMed Smart Ward Laravel server and can be
// changed from the login screen's settings dialog (gear icon, config
// password protected). It persists across app restarts via AsyncStorage.

import AsyncStorage from '@react-native-async-storage/async-storage';
import { Platform } from 'react-native';

// Phone / APK default: the ward's Smart Ward server.
const DEVICE_DEFAULT_BASE_URL = 'http://192.168.0.88:18080';

// In a browser (`npm run web`, for development) the Laravel dev server is on
// the same machine, so default to it: port 8000, as `php artisan serve` uses.
export const DEFAULT_BASE_URL =
  Platform.OS === 'web' && typeof window !== 'undefined' && window.location?.hostname
    ? `${window.location.protocol}//${window.location.hostname}:8000`
    : DEVICE_DEFAULT_BASE_URL;

// Password required to open the settings dialog on the login screen.
export const SETTINGS_PASSWORD = '1324';

const STORAGE_KEY = 'smartward.nurse.baseUrl';

let cachedBaseUrl = DEFAULT_BASE_URL;
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
    loadPromise = AsyncStorage.getItem(STORAGE_KEY)
      .then((stored) => {
        if (stored) cachedBaseUrl = normalizeBaseUrl(stored);
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
