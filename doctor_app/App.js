import React, { useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import LoginScreen from './src/screens/LoginScreen';
import DoctorDashboard from './src/screens/DoctorDashboard';
import { logout } from './src/api/endpoints';
import { setDemoMode } from './src/data/notesStore';
import { resetDemoCharts } from './src/data/mockChart';

export default function App() {
  const [session, setSession] = useState(null);

  function handleLogin(newSession) {
    setDemoMode(!!newSession?.demo);
    setSession(newSession);
  }

  function handleLogout() {
    const token = session?.token;
    const wasDemo = !!session?.demo;
    setSession(null);
    setDemoMode(false);
    resetDemoCharts();
    // Best-effort server-side token invalidation (real sessions only)
    if (!wasDemo && token) {
      logout(token);
    }
  }

  return (
    <SafeAreaProvider>
      {session ? (
        <DoctorDashboard session={session} onLogout={handleLogout} />
      ) : (
        <LoginScreen onLogin={handleLogin} />
      )}
      <StatusBar style="light" />
    </SafeAreaProvider>
  );
}
