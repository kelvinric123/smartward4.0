import React, { useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import LoginScreen from './src/screens/LoginScreen';
import NurseDashboard from './src/screens/NurseDashboard';
import { logout } from './src/api/endpoints';

export default function App() {
  const [session, setSession] = useState(null);

  function handleLogout() {
    const token = session?.token;
    const wasDemo = !!session?.demo;
    setSession(null);
    // Best-effort server-side token invalidation (real sessions only)
    if (!wasDemo && token) {
      logout(token);
    }
  }

  return (
    <SafeAreaProvider>
      {session ? (
        <NurseDashboard session={session} onLogout={handleLogout} />
      ) : (
        <LoginScreen onLogin={setSession} />
      )}
      <StatusBar style="light" />
    </SafeAreaProvider>
  );
}
