import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, ScrollView, StyleSheet, ActivityIndicator, FlatList } from 'react-native';
import { globalStyles, theme } from '../../styles/globalStyles';
import Card from '../../components/Card';
import { analyzeMicrofraud } from '../../api/commonApi';
import { Badge } from '../../components/Badge'; // Reusing the Badge component

const MicrofraudScreen = ({ navigation }) => {
  const [transactionsText, setTransactionsText] = useState('');
  const [results, setResults] = useState([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState('');

  const handleAnalyze = async () => {
    if (!transactionsText.trim()) {
      setError('Please paste some transaction data.');
      return;
    }
    setIsLoading(true);
    setError('');
    setResults([]);
    try {
      const response = await analyzeMicrofraud(transactionsText);
      setResults(response);
    } catch (e) {
      setError('Failed to analyze transactions. Please check the format.');
    } finally {
      setIsLoading(false);
    }
  };

  const renderRow = ({ item }) => (
    <View style={styles.tableRow}>
      <Text style={[styles.td, { flex: 1.5 }]}>{item.payee}</Text>
      <Text style={[styles.td, { textAlign: 'center' }]}>{item.count}</Text>
      <Text style={[styles.td, { textAlign: 'right' }]}>{item.total.toFixed(2)}</Text>
      <Text style={[styles.td, { textAlign: 'center' }]}>{item.trust}</Text>
      <View style={[styles.td, { alignItems: 'center' }]}>
        <Badge verdict={item.verdict} />
      </View>
    </View>
  );

  return (
    <ScrollView style={globalStyles.container}>
      <Card>
        <Text style={globalStyles.h1}>Micro-Fraud Detector</Text>
        <Text style={globalStyles.subHeader}>Paste recent transactions to flag suspicious patterns.</Text>
      </Card>

      <Card>
        <TextInput
          style={styles.textArea}
          placeholder={`2025-08-25 12:00, random123@upi, 50\n2025-08-25 12:30, random123@upi, 50`}
          placeholderTextColor={theme.colors.muted}
          multiline
          value={transactionsText}
          onChangeText={setTransactionsText}
        />
        <TouchableOpacity style={[globalStyles.button, { marginTop: 12 }]} onPress={handleAnalyze} disabled={isLoading}>
          <Text style={globalStyles.buttonText}>Analyze</Text>
        </TouchableOpacity>
      </Card>
      
      {isLoading && <ActivityIndicator size="large" color={theme.colors.accent} style={{ marginVertical: 20 }} />}
      {error && <Text style={styles.errorText}>{error}</Text>}

      {results.length > 0 && (
        <Card>
          <Text style={globalStyles.h3}>Analysis Result</Text>
          <View style={styles.tableHeader}>
            <Text style={[styles.th, { flex: 1.5 }]}>Payee</Text>
            <Text style={[styles.th, { textAlign: 'center' }]}>Count</Text>
            <Text style={[styles.th, { textAlign: 'right' }]}>Total (₹)</Text>
            <Text style={[styles.th, { textAlign: 'center' }]}>Trust</Text>
            <Text style={styles.th}>Verdict</Text>
          </View>
          <FlatList
            data={results}
            renderItem={renderRow}
            keyExtractor={(item) => item.payee}
          />
        </Card>
      )}

      <TouchableOpacity onPress={() => navigation.goBack()} style={[globalStyles.button, { marginTop: 16, flex: 0, alignSelf: 'flex-start' }]}>
        <Text style={globalStyles.buttonText}>← Back to Menu</Text>
      </TouchableOpacity>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  textArea: {
    width: '100%',
    minHeight: 160,
    padding: 12,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: theme.colors.border,
    backgroundColor: theme.colors.glass,
    color: theme.colors.txt,
    textAlignVertical: 'top',
    fontSize: 12,
  },
  errorText: { color: theme.colors.fraud, textAlign: 'center', marginVertical: 10 },
  tableHeader: { flexDirection: 'row', paddingBottom: 8, borderBottomWidth: 1, borderBottomColor: theme.colors.border },
  th: { color: theme.colors.muted, fontWeight: 'bold', fontSize: 13, flex: 1 },
  tableRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: theme.colors.border },
  td: { color: theme.colors.txt, fontSize: 12, flex: 1 },
});

export default MicrofraudScreen;
