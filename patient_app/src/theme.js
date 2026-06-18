// Patient-facing palette — warmer and brighter than the clinical nurse app.
export const colors = {
  bg: '#f1f5fb',
  surface: '#f7faff',
  card: '#ffffff',
  ink: '#0f172a',
  inkSoft: '#334155',
  muted: '#64748b',
  mutedSoft: '#94a3b8',
  line: 'rgba(15, 23, 42, 0.08)',

  // Hero gradient endpoints (used as solid backgrounds — RN core has no gradient)
  headerStart: '#1e3a8a',
  headerMid: '#1d4ed8',
  headerEnd: '#0ea5e9',

  // Brand
  brand50: '#eff6ff',
  brand100: '#dbeafe',
  brand200: '#bfdbfe',
  brand500: '#3b82f6',
  brand600: '#2563eb',
  brand700: '#1d4ed8',
  brand900: '#1e3a8a',

  accent50: '#ecfeff',
  accent100: '#cffafe',
  accent500: '#06b6d4',
  accent600: '#0891b2',
  accent700: '#0e7490',

  emerald50: '#ecfdf5',
  emerald100: '#d1fae5',
  emerald500: '#10b981',
  emerald600: '#059669',
  emerald700: '#047857',

  amber50: '#fffbeb',
  amber100: '#fef3c7',
  amber500: '#f59e0b',
  amber600: '#d97706',
  amber700: '#b45309',

  rose50: '#fff1f2',
  rose100: '#ffe4e6',
  rose500: '#f43f5e',
  rose600: '#e11d48',
  rose700: '#be123c',

  violet50: '#f5f3ff',
  violet100: '#ede9fe',
  violet500: '#8b5cf6',
  violet600: '#7c3aed',
  violet700: '#6d28d9',

  slate50: '#f8fafc',
  slate100: '#f1f5f9',
  slate200: '#e2e8f0',
  slate300: '#cbd5e1',
  slate500: '#64748b',
  slate600: '#475569',
  slate700: '#334155',
  slate800: '#1e293b',
  slate900: '#0f172a',
  slate950: '#020617',
};

export const radius = {
  sm: 10,
  md: 14,
  lg: 18,
  xl: 22,
  xxl: 28,
  pill: 999,
};

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
};

export const shadow = {
  sm: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.05,
    shadowOffset: { width: 0, height: 4 },
    shadowRadius: 8,
    elevation: 1,
  },
  md: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.08,
    shadowOffset: { width: 0, height: 10 },
    shadowRadius: 18,
    elevation: 3,
  },
  lg: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.12,
    shadowOffset: { width: 0, height: 18 },
    shadowRadius: 28,
    elevation: 5,
  },
};
