import React from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import NurseDashboard from './src/screens/NurseDashboard';

export default function App() {
  return (
    <SafeAreaProvider>
      <NurseDashboard />
      <StatusBar style="light" />
    </SafeAreaProvider>
  );
}
