import React, { useState } from 'react';
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
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { colors, radius } from '../theme';
import { loginConsultant } from '../api/endpoints';

export default function LoginScreen({ onLogin }) {
  const [username, setUsername] = useState('drrajan');
  const [password, setPassword] = useState('demo');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState(null);

  async function handleLogin() {
    setError(null);
    if (!username.trim() || !password) {
      setError('Please enter your username and password.');
      return;
    }
    setBusy(true);
    try {
      const session = await loginConsultant({ username: username.trim(), password });
      onLogin(session);
    } catch (e) {
      setError('Login failed. Please try again.');
    } finally {
      setBusy(false);
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
                  <Text style={styles.brandSub}>Consultant Mobile Access</Text>
                </View>
              </View>

              <View style={styles.brandTagCard}>
                <Text style={styles.brandTagEyebrow}>LINKED TO</Text>
                <Text style={styles.brandTagName}>QMed Smart Ward System</Text>
                <Text style={styles.brandTagDesc}>
                  Sign in as a consultant to view every bed under your care across all wards in
                  real-time.
                </Text>
              </View>
            </View>

            <View style={styles.card}>
              <Text style={styles.cardEyebrow}>CONSULTANT LOGIN</Text>
              <Text style={styles.cardTitle}>Welcome back, Doctor</Text>
              <Text style={styles.cardMeta}>
                Use your QMed Smart Ward credentials. Your access scope is limited to patients
                under your consultancy.
              </Text>

              <View style={styles.field}>
                <Text style={styles.fieldLabel}>USERNAME / MMC NO.</Text>
                <TextInput
                  style={styles.input}
                  value={username}
                  onChangeText={setUsername}
                  autoCapitalize="none"
                  autoCorrect={false}
                  placeholder="e.g. drrajan"
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
                  {busy ? 'Signing in...' : 'Login as Consultant'}
                </Text>
              </TouchableOpacity>

              <View style={styles.mockNote}>
                <Text style={styles.mockNoteTitle}>DEMO BUILD</Text>
                <Text style={styles.mockNoteText}>
                  This is a showcase APK with mock data. Any credentials will log you in. The
                  production build will authenticate against the QMed Smart Ward Laravel backend.
                </Text>
              </View>
            </View>

            <View style={styles.footer}>
              <Text style={styles.footerText}>QMed Smart Ward · Doctor App · v1.0.0</Text>
            </View>
          </ScrollView>
        </KeyboardAvoidingView>
      </SafeAreaView>
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
    backgroundColor: colors.blue600,
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
    color: '#bfdbfe',
    fontSize: 12,
    marginTop: 2,
    fontWeight: '600',
    letterSpacing: 0.3,
  },
  brandTagCard: {
    marginTop: 16,
    padding: 14,
    borderRadius: radius.lg,
    backgroundColor: 'rgba(255,255,255,0.08)',
    borderWidth: 1,
    borderColor: 'rgba(191,219,254,0.18)',
  },
  brandTagEyebrow: {
    color: '#bfdbfe',
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
    color: 'rgba(219, 234, 254, 0.85)',
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
    color: colors.blue700,
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
    backgroundColor: colors.blue700,
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
  mockNote: {
    marginTop: 16,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.amber100,
    backgroundColor: colors.amber50,
    padding: 12,
  },
  mockNoteTitle: {
    color: colors.amber700,
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 1.8,
  },
  mockNoteText: {
    marginTop: 4,
    color: colors.amber700,
    fontSize: 12,
    lineHeight: 17,
  },
  footer: {
    marginTop: 18,
    alignItems: 'center',
  },
  footerText: {
    color: 'rgba(219, 234, 254, 0.6)',
    fontSize: 11,
  },
});
