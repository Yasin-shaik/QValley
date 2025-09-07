import React from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet } from 'react-native';
import { globalStyles, theme } from '../../styles/globalStyles';
import Card from '../../components/Card';

const Tile = ({ title, description, buttonText, onPress }) => (
  <Card style={styles.tile}>
    <Text style={globalStyles.h3}>{title}</Text>
    <Text style={[globalStyles.note, styles.tileDescription]}>{description}</Text>
    <TouchableOpacity style={globalStyles.button} onPress={onPress}>
      <Text style={globalStyles.buttonText}>{buttonText}</Text>
    </TouchableOpacity>
  </Card>
);

const MenuScreen = ({ navigation }) => {
  return (
    <ScrollView style={globalStyles.container}>
      <View style={styles.header}>
        <View style={styles.logo}>
          <Text style={styles.logoText}>QS</Text>
        </View>
        <View>
          <Text style={globalStyles.h1}>QuantumSafe – Common Version</Text>
          <Text style={globalStyles.subHeader}>Verify before you pay: Tools for everyone</Text>
        </View>
      </View>

      <View>
        <Tile
          title="Screenshot / Invoice / QR Analyzer"
          description="Upload a payment screenshot or QR. We scan for tampering & risky patterns."
          buttonText="Open Analyzer →"
          onPress={() => navigation.navigate('Screenshot')}
        />
        <Tile
          title="“Should I send?” Chatbot"
          description="Type the request you received. Get a quick Safe / Suspicious / Fraud verdict."
          buttonText="Ask the Bot →"
          onPress={() => navigation.navigate('Chatbot')}
        />
        <Tile
          title="Micro-Fraud Detector"
          description="Paste recent payments. We flag small, repeated or unusual patterns."
          buttonText="Scan History →"
          onPress={() => navigation.navigate('Microfraud')}
        />
         <Tile
          title="Bank / FinTech Dashboard"
          description="Analyze bulk transaction CSVs for institutional risk monitoring."
          buttonText="Go to Dashboard →"
          onPress={() => navigation.navigate('Dashboard')}
        />
        <Tile
          title="View Past Results"
          description="Browse, filter, and sort all historical analyses from your tools."
          buttonText="Open Results →"
          onPress={() => navigation.navigate('Results')}
        />

        <Tile
          title="Bank / FinTech Dashboard"
          description="Analyze bulk transaction CSVs for institutional risk monitoring."
          buttonText="Go to Dashboard →"
          onPress={() => navigation.navigate('Dashboard')}
        />
      </View>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    marginBottom: 18,
  },
  logo: {
    width: 48,
    height: 48,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: 'rgba(255,255,255,.08)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  logoText: {
    color: theme.colors.txt,
    fontWeight: '800',
    fontSize: 20,
  },
  tile: {
    marginBottom: 16,
  },
  tileDescription: {
    minHeight: 40,
    marginBottom: 12,
    fontSize: 14,
  },
});

export default MenuScreen;
