import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  TextInput,
  TouchableOpacity,
  StatusBar,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Modal,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import { loginNurse, pingServer } from '../api/endpoints';
import * as mock from '../data/mockData';
import {
  DEFAULT_BASE_URL,
  SETTINGS_PASSWORD,
  ensureConfigLoaded,
  getBaseUrl,
  setBaseUrl,
  normalizeBaseUrl,
} from '../config';

export default function LoginScreen({ onLogin }) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  // Settings dialog state
  const [settingsOpen, setSettingsOpen] = useState(false);
  const [unlocked, setUnlocked] = useState(false);
  const [configPass, setConfigPass] = useState('');
  const [configError, setConfigError] = useState(null);
  const [apiUrl, setApiUrl] = useState(DEFAULT_BASE_URL);
  const [testResult, setTestResult] = useState(null);
  const [testing, setTesting] = useState(false);
  const [currentUrl, setCurrentUrl] = useState(DEFAULT_BASE_URL);

  useEffect(() => {
    ensureConfigLoaded().then(() => setCurrentUrl(getBaseUrl()));
  }, []);

  async function handleLogin() {
    setError(null);
    if (!username.trim() || !password) {
      setError('Please enter your username and password.');
      return;
    }
    setBusy(true);
    try {
      const session = await loginNurse({ username: username.trim(), password });
      onLogin(session);
    } catch (e) {
      setError(e?.message ?? 'Login failed. Please try again.');
    } finally {
      setBusy(false);
    }
  }

  function handleDemoLogin() {
    setError(null);
    onLogin({
      demo: true,
      token: null,
      nurse: mock.nurse,
    });
  }

  function openSettings() {
    setSettingsOpen(true);
    setUnlocked(false);
    setConfigPass('');
    setConfigError(null);
    setTestResult(null);
    setApiUrl(getBaseUrl());
  }

  function closeSettings() {
    setSettingsOpen(false);
    setUnlocked(false);
    setConfigPass('');
    setConfigError(null);
    setTestResult(null);
  }

  function unlock() {
    if (configPass === SETTINGS_PASSWORD) {
      setUnlocked(true);
      setConfigError(null);
      setApiUrl(getBaseUrl());
    } else {
      setConfigError('Incorrect config password.');
    }
  }

  async function saveSettings() {
    const saved = await setBaseUrl(apiUrl);
    setApiUrl(saved);
    setCurrentUrl(saved);
    setTestResult(null);
    closeSettings();
  }

  async function resetToDefault() {
    setApiUrl(DEFAULT_BASE_URL);
    setTestResult(null);
  }

  async function testConnection() {
    setTesting(true);
    setTestResult(null);
    // Apply the URL being edited so the ping targets it
    const saved = await setBaseUrl(apiUrl);
    setApiUrl(saved);
    setCurrentUrl(saved);
    try {
      await pingServer();
      setTestResult({ ok: true, message: 'Connected to QMed Smart Ward server.' });
    } catch (e) {
      setTestResult({ ok: false, message: e?.message ?? 'Could not reach the server.' });
    } finally {
      setTesting(false);
    }
  }

  return (
    <View style={styles.root}>
      <StatusBar barStyle="light-content" backgroundColor={colors.headerStart} />
      <SafeAreaView edges={['top', 'bottom']} style={{ flex: 1 }}>
        <KeyboardAvoidingView
          style={{ flex: 1 }}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <ScrollView
            contentContainerStyle={styles.scroll}
            keyboardShouldPersistTaps="handled"
          >
            <View style={styles.brandBlock}>
              <View style={styles.brandRow}>
                <View style={styles.logoBadge}>
                  <Text style={styles.logoBadgeText}>Q</Text>
                </View>
                <View style={{ flex: 1, marginLeft: 12 }}>
                  <Text style={styles.brandTitle}>QMed Smart Ward</Text>
                  <Text style={styles.brandSub}>Nurse Mobile Access</Text>
                </View>
                <TouchableOpacity
                  style={styles.settingsBtn}
                  onPress={openSettings}
                  activeOpacity={0.8}
                >
                  <Text style={styles.settingsBtnText}>⚙</Text>
                </TouchableOpacity>
              </View>

              <View style={styles.brandTagCard}>
                <Text style={styles.brandTagEyebrow}>LINKED TO</Text>
                <Text style={styles.brandTagName}>QMed Smart Ward System</Text>
                <Text style={styles.brandTagDesc}>
                  Sign in as a nurse to view the beds assigned to you for the current shift.
                </Text>
              </View>
            </View>

            <View style={styles.card}>
              <Text style={styles.cardEyebrow}>NURSE LOGIN</Text>
              <Text style={styles.cardTitle}>Welcome back</Text>
              <Text style={styles.cardMeta}>
                Use the app credentials configured for you in the QMed Smart Ward system. Your
                view is limited to the beds assigned to you.
              </Text>

              <View style={styles.field}>
                <Text style={styles.fieldLabel}>USERNAME / REG NO.</Text>
                <TextInput
                  style={styles.input}
                  value={username}
                  onChangeText={setUsername}
                  autoCapitalize="none"
                  autoCorrect={false}
                  placeholder="e.g. srmaria"
                  placeholderTextColor={colors.mutedSoft}
                />
              </View>

              <View style={styles.field}>
                <Text style={styles.fieldLabel}>PASSWORD</Text>
                <TextInput
                  style={styles.input}
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry
                  placeholder="Enter your password"
                  placeholderTextColor={colors.mutedSoft}
                />
              </View>

              {error ? <Text style={styles.errorText}>{error}</Text> : null}

              <TouchableOpacity
                style={[styles.loginBtn, busy && { opacity: 0.7 }]}
                onPress={handleLogin}
                activeOpacity={0.85}
                disabled={busy}
              >
                <Text style={styles.loginBtnText}>
                  {busy ? 'Signing in...' : 'Login as Nurse'}
                </Text>
              </TouchableOpacity>

              <View style={styles.dividerRow}>
                <View style={styles.dividerLine} />
                <Text style={styles.dividerText}>OR</Text>
                <View style={styles.dividerLine} />
              </View>

              <TouchableOpacity
                style={styles.demoBtn}
                onPress={handleDemoLogin}
                activeOpacity={0.85}
                disabled={busy}
              >
                <Text style={styles.demoBtnText}>Demo Login</Text>
              </TouchableOpacity>
              <Text style={styles.demoHint}>
                Explore the app with sample data — no server connection needed.
              </Text>
            </View>

            <View style={styles.footer}>
              <Text style={styles.footerText}>QMed Smart Ward · Nurse App · v1.0.0</Text>
              <Text style={styles.footerUrl}>{currentUrl}</Text>
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>

      <Modal
        visible={settingsOpen}
        animationType="slide"
        transparent
        statusBarTranslucent
        onRequestClose={closeSettings}
      >
        <View style={styles.backdrop}>
          <TouchableOpacity style={styles.backdropTap} activeOpacity={1} onPress={closeSettings} />
          <View style={styles.sheet}>
            <View style={styles.sheetHandle} />
            <ScrollView
              keyboardShouldPersistTaps="handled"
              contentContainerStyle={{ padding: 18, paddingBottom: 28 }}
              showsVerticalScrollIndicator={false}
            >
              {!unlocked ? (
                <>
                  <Text style={styles.sheetEyebrow}>SETTINGS</Text>
                  <Text style={styles.sheetTitle}>Config password required</Text>
                  <Text style={styles.sheetMeta}>
                    Enter the config password to change the connection settings of this app.
                  </Text>

                  <View style={styles.field}>
                    <Text style={styles.fieldLabel}>CONFIG PASSWORD</Text>
                    <TextInput
                      style={styles.input}
                      value={configPass}
                      onChangeText={setConfigPass}
                      secureTextEntry
                      keyboardType="number-pad"
                      placeholder="Enter config password"
                      placeholderTextColor={colors.mutedSoft}
                      onSubmitEditing={unlock}
                    />
                  </View>

                  {configError ? <Text style={styles.errorText}>{configError}</Text> : null}

                  <View style={styles.actionRow}>
                    <TouchableOpacity
                      style={[styles.btn, styles.btnCancel]}
                      onPress={closeSettings}
                      activeOpacity={0.85}
                    >
                      <Text style={styles.btnCancelText}>Cancel</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={[styles.btn, styles.btnSave]}
                      onPress={unlock}
                      activeOpacity={0.85}
                    >
                      <Text style={styles.btnSaveText}>Unlock</Text>
                    </TouchableOpacity>
                  </View>
                </>
              ) : (
                <>
                  <Text style={styles.sheetEyebrow}>SETTINGS</Text>
                  <Text style={styles.sheetTitle}>API Connection</Text>
                  <Text style={styles.sheetMeta}>
                    Address of the QMed Smart Ward server this app connects to. Nurse logins are
                    validated against this server.
                  </Text>

                  <View style={styles.field}>
                    <Text style={styles.fieldLabel}>API PATH</Text>
                    <TextInput
                      style={styles.input}
                      value={apiUrl}
                      onChangeText={setApiUrl}
                      autoCapitalize="none"
                      autoCorrect={false}
                      keyboardType="url"
                      placeholder={DEFAULT_BASE_URL}
                      placeholderTextColor={colors.mutedSoft}
                    />
                    <Text style={styles.fieldHint}>
                      Default: {DEFAULT_BASE_URL}
                    </Text>
                  </View>

                  {testResult ? (
                    <Text style={[styles.testText, { color: testResult.ok ? colors.emerald600 : colors.rose600 }]}>
                      {testResult.message}
                    </Text>
                  ) : null}

                  <TouchableOpacity
                    style={[styles.testBtn, testing && { opacity: 0.6 }]}
                    onPress={testConnection}
                    disabled={testing}
                    activeOpacity={0.85}
                  >
                    <Text style={styles.testBtnText}>
                      {testing ? 'Testing...' : 'Test Connection'}
                    </Text>
                  </TouchableOpacity>

                  <TouchableOpacity onPress={resetToDefault} activeOpacity={0.7}>
                    <Text style={styles.resetText}>Reset to default</Text>
                  </TouchableOpacity>

                  <View style={styles.actionRow}>
                    <TouchableOpacity
                      style={[styles.btn, styles.btnCancel]}
                      onPress={closeSettings}
                      activeOpacity={0.85}
                    >
                      <Text style={styles.btnCancelText}>Cancel</Text>
                    </TouchableOpacity>
                    <TouchableOpacity
                      style={[styles.btn, styles.btnSave, !normalizeBaseUrl(apiUrl) && { opacity: 0.5 }]}
                      onPress={saveSettings}
                      activeOpacity={0.85}
                    >
                      <Text style={styles.btnSaveText}>Save</Text>
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
    backgroundColor: colors.headerStart,
  },
  scroll: {
    flexGrow: 1,
    paddingHorizontal: 18,
    paddingTop: 24,
    paddingBottom: 24,
  },
  brandBlock: {
    marginBottom: 18,
  },
  brandRow: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  logoBadge: {
    width: 52,
    height: 52,
    borderRadius: 14,
    backgroundColor: colors.cyan700,
    alignItems: 'center',
    justifyContent: 'center',
    shadowColor: '#000',
    shadowOpacity: 0.3,
    shadowOffset: { width: 0, height: 8 },
    shadowRadius: 12,
    elevation: 4,
  },
  logoBadgeText: {
    color: '#fff',
    fontSize: 26,
    fontWeight: '900',
  },
  brandTitle: {
    color: '#fff',
    fontSize: 20,
    fontWeight: '800',
  },
  brandSub: {
    color: '#cffafe',
    fontSize: 12,
    marginTop: 2,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  settingsBtn: {
    width: 40,
    height: 40,
    borderRadius: 999,
    backgroundColor: 'rgba(255,255,255,0.1)',
    borderColor: 'rgba(255,255,255,0.15)',
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 10,
  },
  settingsBtnText: {
    color: '#fff',
    fontSize: 18,
    lineHeight: 20,
  },
  brandTagCard: {
    marginTop: 16,
    padding: 14,
    borderRadius: radius.lg,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderWidth: 1,
    borderColor: 'rgba(165,243,252,0.18)',
  },
  brandTagEyebrow: {
    color: '#cffafe',
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  brandTagName: {
    marginTop: 2,
    color: '#fff',
    fontSize: 16,
    fontWeight: '800',
  },
  brandTagDesc: {
    marginTop: 6,
    color: 'rgba(207, 250, 254, 0.85)',
    fontSize: 12,
    lineHeight: 18,
  },
  card: {
    backgroundColor: colors.card,
    borderRadius: radius.xl,
    padding: 18,
    shadowColor: '#000',
    shadowOpacity: 0.18,
    shadowOffset: { width: 0, height: 18 },
    shadowRadius: 28,
    elevation: 6,
  },
  cardEyebrow: {
    color: colors.cyan700,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.4,
  },
  cardTitle: {
    marginTop: 4,
    color: colors.slate900,
    fontSize: 22,
    fontWeight: '800',
  },
  cardMeta: {
    marginTop: 6,
    color: colors.muted,
    fontSize: 12,
    lineHeight: 18,
  },
  field: {
    marginTop: 16,
  },
  fieldLabel: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.8,
    color: colors.muted,
  },
  fieldHint: {
    marginTop: 6,
    fontSize: 11,
    color: colors.muted,
  },
  input: {
    marginTop: 6,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
    borderRadius: radius.md,
    paddingHorizontal: 12,
    paddingVertical: Platform.OS === 'ios' ? 12 : 10,
    fontSize: 15,
    color: colors.slate900,
  },
  errorText: {
    marginTop: 12,
    color: colors.rose600,
    fontSize: 12,
    fontWeight: '600',
  },
  loginBtn: {
    marginTop: 20,
    backgroundColor: colors.cyan700,
    borderRadius: radius.lg,
    paddingVertical: 14,
    alignItems: 'center',
    justifyContent: 'center',
  },
  loginBtnText: {
    color: '#fff',
    fontSize: 15,
    fontWeight: '800',
    letterSpacing: 0.4,
  },
  dividerRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 18,
    gap: 10,
  },
  dividerLine: {
    flex: 1,
    height: 1,
    backgroundColor: colors.slate200,
  },
  dividerText: {
    color: colors.mutedSoft,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2,
  },
  demoBtn: {
    marginTop: 14,
    borderWidth: 1,
    borderColor: colors.amber500,
    backgroundColor: colors.amber50,
    borderRadius: radius.lg,
    paddingVertical: 12,
    alignItems: 'center',
  },
  demoBtnText: {
    color: colors.amber700,
    fontSize: 14,
    fontWeight: '800',
    letterSpacing: 0.4,
  },
  demoHint: {
    marginTop: 8,
    color: colors.muted,
    fontSize: 11,
    textAlign: 'center',
  },
  footer: {
    marginTop: 18,
    alignItems: 'center',
  },
  footerText: {
    color: 'rgba(207, 250, 254, 0.6)',
    fontSize: 11,
  },
  footerUrl: {
    marginTop: 4,
    color: 'rgba(207, 250, 254, 0.45)',
    fontSize: 10,
  },

  // Settings sheet
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(2,6,23,0.55)',
    justifyContent: 'flex-end',
  },
  backdropTap: {
    flex: 1,
  },
  sheet: {
    backgroundColor: colors.surface,
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
  },
  sheetHandle: {
    alignSelf: 'center',
    width: 44,
    height: 4,
    borderRadius: 2,
    backgroundColor: colors.slate300,
    marginTop: 8,
  },
  sheetEyebrow: {
    color: colors.cyan700,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 2.2,
  },
  sheetTitle: {
    marginTop: 2,
    color: colors.slate900,
    fontSize: 18,
    fontWeight: '800',
  },
  sheetMeta: {
    marginTop: 6,
    color: colors.muted,
    fontSize: 12,
    lineHeight: 17,
  },
  testText: {
    marginTop: 12,
    fontSize: 12,
    fontWeight: '600',
  },
  testBtn: {
    marginTop: 14,
    borderWidth: 1,
    borderColor: colors.cyan700,
    borderRadius: radius.lg,
    paddingVertical: 12,
    alignItems: 'center',
  },
  testBtnText: {
    color: colors.cyan700,
    fontSize: 13,
    fontWeight: '800',
  },
  resetText: {
    marginTop: 12,
    color: colors.muted,
    fontSize: 12,
    fontWeight: '600',
    textDecorationLine: 'underline',
    alignSelf: 'center',
  },
  actionRow: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 18,
  },
  btn: {
    flex: 1,
    paddingVertical: 14,
    borderRadius: radius.lg,
    alignItems: 'center',
  },
  btnCancel: {
    backgroundColor: colors.slate100,
  },
  btnCancelText: {
    color: colors.slate700,
    fontSize: 14,
    fontWeight: '800',
  },
  btnSave: {
    backgroundColor: colors.cyan700,
  },
  btnSaveText: {
    color: '#fff',
    fontSize: 14,
    fontWeight: '800',
  },
});
