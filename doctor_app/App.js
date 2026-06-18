import React, { useState } from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import LoginScreen from './src/screens/LoginScreen';
import DoctorDashboard from './src/screens/DoctorDashboard';

export default function App() {
  const [session, setSession] = useState(null);

  return (
    <SafeAreaProvider>
      {session ? (
        <DoctorDashboard session={session} onLogout={() => setSession(null)} />
      ) : (
        <LoginScreen onLogin={setSession} />
      )}
      <StatusBar style="light" />
    </SafeAreaProvider>
  );
}
