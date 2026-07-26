import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { StatusBar } from 'expo-status-bar';
import { useKeepAwake } from 'expo-keep-awake';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { WebView } from 'react-native-webview';

// Kiosk shell for the QMed Smart Ward bedside Patient Terminal.
//
// The terminal UI itself is the web app served by the Smart Ward server at
// {SERVER}/terminal (deployed by "patient information terminal/apk_build.bat").
// This app shows it full-screen, keeps the tablet awake, and provides a
// staff-only settings dialog (config password) to change the server address.
// Bed binding / demo-vs-live data is configured inside the terminal web app
// (Settings -> Admin settings, same passcode).

const DEFAULT_BASE_URL = 'http://192.168.0.88:18080';
const TERMINAL_PATH = '/terminal';
const SETTINGS_PASSWORD = '1324';
const STORAGE_KEY = 'smartward.patientterminal.baseUrl';

function normalizeBaseUrl(url) {
  let u = (url ?? '').trim();
  if (!u) return DEFAULT_BASE_URL;
  if (!/^https?:\/\//i.test(u)) u = `http://${u}`;
  return u.replace(/\/+$/, '');
}

export default function App() {
  useKeepAwake();

  const [ready, setReady] = useState(false);
  const [baseUrl, setBaseUrl] = useState(DEFAULT_BASE_URL);
  const [webKey, setWebKey] = useState(0);
  const [loadFailed, setLoadFailed] = useState(false);
  const webRef = useRef(null);

  // Settings dialog state
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [unlocked, setUnlocked] = useState(false);
  const [configPass, setConfigPass] = useState('');
  const [configError, setConfigError] = useState(null);
  const [urlDraft, setUrlDraft] = useState(DEFAULT_BASE_URL);
  const [testing, setTesting] = useState(false);
  const [testResult, setTestResult] = useState(null);

  useEffect(() => {
    AsyncStorage.getItem(STORAGE_KEY)
      .then((stored) => {
        if (stored) setBaseUrl(normalizeBaseUrl(stored));
      })
      .catch(() => {})
      .finally(() => setReady(true));
  }, []);

  const terminalUrl = `${baseUrl}${TERMINAL_PATH}`;

  const reload = useCallback(() => {
    setLoadFailed(false);
    setWebKey((k) => k + 1);
  }, []);

  function openSettings() {
    setSettingsOpen(true);
    setUnlocked(false);
    setConfigPass('');
    setConfigError(null);
    setTestResult(null);
    setUrlDraft(baseUrl);
  }

  function closeSettings() {
    setSettingsOpen(false);
  }

  function unlock() {
    if (configPass === SETTINGS_PASSWORD) {
      setUnlocked(true);
      setConfigError(null);
      setUrlDraft(baseUrl);
    } else {
      setConfigError('Incorrect config password.');
    }
  }

  async function testConnection() {
    setTesting(true);
    setTestResult(null);
    const url = normalizeBaseUrl(urlDraft);
    try {
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 10000);
      const res = await fetch(`${url}/api/terminal/ping`, {
        headers: { Accept: 'application/json' },
        signal: controller.signal,
      });
      clearTimeout(timer);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setTestResult({ ok: true, message: 'Connected to QMed Smart Ward server.' });
    } catch (e) {
      setTestResult({ ok: false, message: 'Could not reach the server at this address.' });
    } finally {
      setTesting(false);
    }
  }

  async function saveSettings() {
    const url = normalizeBaseUrl(urlDraft);
    setBaseUrl(url);
    try {
      await AsyncStorage.setItem(STORAGE_KEY, url);
    } catch (e) {
      // Non-fatal
    }
    closeSettings();
    reload();
  }

  if (!ready) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" color="#0e7490" />
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <StatusBar hidden />

      {loadFailed ? (
        <View style={styles.center}>
          <Text style={styles.errTitle}>Cannot reach the Smart Ward server</Text>
          <Text style={styles.errMeta}>
            Tried to load {terminalUrl}. Check that the tablet is on the ward network and the
            server address is correct.
          </Text>
          <View style={styles.errBtnRow}>
            <TouchableOpacity style={[styles.errBtn, styles.errBtnPrimary]} onPress={reload} activeOpacity={0.85}>
              <Text style={styles.errBtnPrimaryText}>Retry</Text>
            </TouchableOpacity>
            <TouchableOpacity style={[styles.errBtn, styles.errBtnGhost]} onPress={openSettings} activeOpacity={0.85}>
              <Text style={styles.errBtnGhostText}>Settings</Text>
            </TouchableOpacity>
          </View>
        </View>
      ) : (
        <WebView
          key={webKey}
          ref={webRef}
          source={{ uri: terminalUrl }}
          style={styles.web}
          javaScriptEnabled
          domStorageEnabled
          allowsFullscreenVideo
          setBuiltInZoomControls={false}
          onError={() => setLoadFailed(true)}
          onHttpError={(e) => {
            if (e?.nativeEvent?.statusCode >= 500 || e?.nativeEvent?.statusCode === 404) {
              setLoadFailed(true);
            }
          }}
          startInLoadingState
          renderLoading={() => (
            <View style={[StyleSheet.absoluteFill, styles.center]}>
              <ActivityIndicator size="large" color="#0e7490" />
              <Text style={styles.loadingText}>Loading Patient Terminal…</Text>
            </View>
          )}
        />
      )}

      {/* Staff-only settings trigger (config password protected) */}
      <TouchableOpacity style={styles.gear} onPress={openSettings} activeOpacity={0.6}>
        <Text style={styles.gearText}>⚙</Text>
      </TouchableOpacity>

      <Modal visible={settingsOpen} animationType="fade" transparent onRequestClose={closeSettings}>
        <View style={styles.backdrop}>
          <View style={styles.sheet}>
            <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={{ padding: 22 }}>
              {!unlocked ? (
                <>
                  <Text style={styles.sheetEyebrow}>SETTINGS</Text>
                  <Text style={styles.sheetTitle}>Config password required</Text>
                  <Text style={styles.sheetMeta}>
                    Staff only. Enter the config password to change the server connection.
                  </Text>

                  <TextInput
                    style={styles.input}
                    value={configPass}
                    onChangeText={setConfigPass}
                    secureTextEntry
                    keyboardType="number-pad"
                    placeholder="Config password"
                    placeholderTextColor="#94a3b8"
                    onSubmitEditing={unlock}
                  />
                  {configError ? <Text style={styles.errorText}>{configError}</Text> : null}

                  <View style={styles.actionRow}>
                    <TouchableOpacity style={[styles.btn, styles.btnCancel]} onPress={closeSettings} activeOpacity={0.85}>
                      <Text style={styles.btnCancelText}>Cancel</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[styles.btn, styles.btnSave]} onPress={unlock} activeOpacity={0.85}>
                      <Text style={styles.btnSaveText}>Unlock</Text>
                    </TouchableOpacity>
                  </View>
                </>
              ) : (
                <>
                  <Text style={styles.sheetEyebrow}>SETTINGS</Text>
                  <Text style={styles.sheetTitle}>Smart Ward server</Text>
                  <Text style={styles.sheetMeta}>
                    Address of the QMed Smart Ward server. The terminal loads from{' '}
                    {normalizeBaseUrl(urlDraft)}{TERMINAL_PATH}.
                  </Text>

                  <TextInput
                    style={styles.input}
                    value={urlDraft}
                    onChangeText={setUrlDraft}
                    autoCapitalize="none"
                    autoCorrect={false}
                    keyboardType="url"
                    placeholder={DEFAULT_BASE_URL}
                    placeholderTextColor="#94a3b8"
                  />
                  <Text style={styles.hint}>Default: {DEFAULT_BASE_URL}</Text>

                  {testResult ? (
                    <Text style={[styles.testText, { color: testResult.ok ? '#059669' : '#e11d48' }]}>
                      {testResult.message}
                    </Text>
                  ) : null}

                  <TouchableOpacity
                    style={[styles.testBtn, testing && { opacity: 0.6 }]}
                    onPress={testConnection}
                    disabled={testing}
                    activeOpacity={0.85}
                  >
                    <Text style={styles.testBtnText}>{testing ? 'Testing…' : 'Test Connection'}</Text>
                  </TouchableOpacity>

                  <TouchableOpacity onPress={() => setUrlDraft(DEFAULT_BASE_URL)} activeOpacity={0.7}>
                    <Text style={styles.resetText}>Reset to default</Text>
                  </TouchableOpacity>

                  <View style={styles.actionRow}>
                    <TouchableOpacity style={[styles.btn, styles.btnCancel]} onPress={closeSettings} activeOpacity={0.85}>
                      <Text style={styles.btnCancelText}>Cancel</Text>
                    </TouchableOpacity>
                    <TouchableOpacity style={[styles.btn, styles.btnSave]} onPress={saveSettings} activeOpacity={0.85}>
                      <Text style={styles.btnSaveText}>Save &amp; Reload</Text>
                    </TouchableOpacity>
                  </View>
                </>
              )}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#0b2435',
  },
  web: {
    flex: 1,
    backgroundColor: '#f7fbff',
  },
  center: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#0b2435',
    padding: 32,
  },
  loadingText: {
    marginTop: 14,
    color: '#0e7490',
    fontSize: 15,
    fontWeight: '700',
  },
  errTitle: {
    color: '#fff',
    fontSize: 22,
    fontWeight: '800',
    textAlign: 'center',
  },
  errMeta: {
    marginTop: 10,
    color: 'rgba(207,250,254,0.8)',
    fontSize: 14,
    lineHeight: 21,
    textAlign: 'center',
    maxWidth: 520,
  },
  errBtnRow: {
    flexDirection: 'row',
    gap: 12,
    marginTop: 24,
  },
  errBtn: {
    paddingHorizontal: 26,
    paddingVertical: 13,
    borderRadius: 14,
  },
  errBtnPrimary: {
    backgroundColor: '#0e7490',
  },
  errBtnPrimaryText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '800',
  },
  errBtnGhost: {
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.35)',
  },
  errBtnGhostText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '800',
  },
  gear: {
    position: 'absolute',
    right: 10,
    bottom: 10,
    width: 40,
    height: 40,
    borderRadius: 999,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(2,6,23,0.25)',
  },
  gearText: {
    color: 'rgba(255,255,255,0.85)',
    fontSize: 18,
  },

  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(2,6,23,0.6)',
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
  },
  sheet: {
    width: '100%',
    maxWidth: 460,
    maxHeight: '90%',
    backgroundColor: '#f7fbff',
    borderRadius: 22,
    overflow: 'hidden',
  },
  sheetEyebrow: {
    color: '#0e7490',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  sheetTitle: {
    marginTop: 2,
    color: '#0f172a',
    fontSize: 19,
    fontWeight: '800',
  },
  sheetMeta: {
    marginTop: 6,
    color: '#64748b',
    fontSize: 12.5,
    lineHeight: 18,
  },
  input: {
    marginTop: 16,
    borderWidth: 1,
    borderColor: '#e2e8f0',
    backgroundColor: '#fff',
    borderRadius: 12,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 15,
    color: '#0f172a',
  },
  hint: {
    marginTop: 6,
    fontSize: 11.5,
    color: '#64748b',
  },
  errorText: {
    marginTop: 10,
    color: '#e11d48',
    fontSize: 12.5,
    fontWeight: '600',
  },
  testText: {
    marginTop: 12,
    fontSize: 12.5,
    fontWeight: '700',
  },
  testBtn: {
    marginTop: 14,
    borderWidth: 1,
    borderColor: '#0e7490',
    borderRadius: 14,
    paddingVertical: 12,
    alignItems: 'center',
  },
  testBtnText: {
    color: '#0e7490',
    fontSize: 13.5,
    fontWeight: '800',
  },
  resetText: {
    marginTop: 12,
    color: '#64748b',
    fontSize: 12.5,
    fontWeight: '600',
    textDecorationLine: 'underline',
    alignSelf: 'center',
  },
  actionRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 20,
  },
  btn: {
    flex: 1,
    paddingVertical: 13,
    borderRadius: 14,
    alignItems: 'center',
  },
  btnCancel: {
    backgroundColor: '#f1f5f9',
  },
  btnCancelText: {
    color: '#334155',
    fontSize: 14,
    fontWeight: '800',
  },
  btnSave: {
    backgroundColor: '#0e7490',
  },
  btnSaveText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },
});
