import React from 'react';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import PatientHome from './src/screens/PatientHome';

export default function App() {
  return (
    <SafeAreaProvider>
      <PatientHome />
      <StatusBar style="light" />
    </SafeAreaProvider>
  );
}
