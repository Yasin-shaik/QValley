import { StyleSheet } from 'react-native';

// Color palette directly from the PHP CSS variables
export const theme = {
  colors: {
    bg: '#0b1022',
    bg2: '#0f1630',
    glass: 'rgba(255, 255, 255, 0.06)',
    border: 'rgba(255, 255, 255, 0.12)',
    txt: '#e6edf3',
    muted: '#9aa4b2',
    safe: '#22c55e',
    warn: '#f59e0b',
    fraud: '#ef4444',
    accent: '#3b82f6',
    gradientStart: '#15224a',
  },
};

export const globalStyles = StyleSheet.create({
  // Main background using LinearGradient
  background: {
    flex: 1,
  },
  container: {
    flex: 1,
    padding: 16,
    paddingTop: 32,
  },
  // --- Typography ---
  h1: {
    fontSize: 28,
    fontWeight: 'bold',
    color: theme.colors.txt,
    marginBottom: 6,
  },
  h3: {
    fontSize: 18,
    fontWeight: 'bold',
    color: theme.colors.txt,
    marginBottom: 10,
  },
  subHeader: {
    fontSize: 16,
    color: theme.colors.muted,
    marginBottom: 18,
  },
  note: {
    fontSize: 12,
    color: theme.colors.muted,
    marginTop: 6,
    lineHeight: 18,
  },
  // --- Card (Glassmorphism) ---
  card: {
    borderRadius: 18,
    borderWidth: 1,
    borderColor: theme.colors.border,
    overflow: 'hidden', // Necessary for blur and gradient to respect border radius
    marginBottom: 16,
  },
  cardBody: {
    padding: 18,
    backgroundColor: theme.colors.glass, // Fallback color
  },
  // --- Grid & Row Layouts ---
  grid: {
    // We will use Flexbox in components to achieve grid-like layouts
  },
  row: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 10,
  },
  // --- Buttons ---
  button: {
    flex: 1,
    paddingVertical: 12,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.glass,
    alignItems: 'center',
    justifyContent: 'center',
  },
  buttonText: {
    color: theme.colors.txt,
    fontWeight: 'bold',
  },
  // --- KPI Summary Box ---
  kpi: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: theme.colors.glass,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  kpiText: {
    color: theme.colors.txt,
  },
  kpiNum: {
    color: theme.colors.txt,
    fontWeight: '800',
    fontSize: 16,
  },
  // --- Table Styles ---
  table: {
    marginTop: 12,
  },
  tableHeader: {
    flexDirection: 'row',
  },
  th: {
    flex: 1,
    paddingVertical: 6,
    paddingHorizontal: 10,
    fontSize: 13,
    color: theme.colors.muted,
    fontWeight: 'bold',
  },
  tableRow: {
    flexDirection: 'row',
    backgroundColor: theme.colors.glass,
    borderRadius: 12,
    marginBottom: 8,
    padding: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
  },
  td: {
    flex: 1,
    fontSize: 14,
    color: theme.colors.txt,
  },
  // --- Badges ---
  badge: {
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
    alignSelf: 'flex-start', // Important for the badge to wrap content
  },
  badgeText: {
    fontWeight: '700',
    fontSize: 12,
  },
  badgeSafe: {
    borderColor: 'rgba(34,197,94,0.35)',
    backgroundColor: 'rgba(34,197,94,0.08)',
  },
  badgeSafeText: {
    color: theme.colors.safe,
  },
  badgeWarn: {
    borderColor: 'rgba(245,158,11,0.35)',
    backgroundColor: 'rgba(245,158,11,0.08)',
  },
  badgeWarnText: {
    color: theme.colors.warn,
  },
  badgeFraud: {
    borderColor: 'rgba(239,68,68,0.35)',
    backgroundColor: 'rgba(239,68,68,0.10)',
  },
  badgeFraudText: {
    color: theme.colors.fraud,
  },
});
