import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, ScrollView, StyleSheet, ActivityIndicator } from 'react-native';
import { globalStyles, theme } from '../../styles/globalStyles';
import Card from '../../components/Card';
import { analyzeChatbotRequest } from '../../api/commonApi';

// A re-usable component to display the analysis result
const ResultCard = ({ result }) => {
    // ... (Badge component can be extracted and reused here)
    return (
        <Card>
            <Text style={globalStyles.h3}>Result</Text>
            <Text style={globalStyles.note}>Trust Score: {result.score}</Text>
            <Text style={globalStyles.note}>Verdict: {result.verdict}</Text>
            <Text style={[globalStyles.note, {marginTop: 8}]}>Why: {result.reasons?.join(' • ')}</Text>
            <Text style={[globalStyles.note, {fontWeight: 'bold', marginTop: 8}]}>Suggested Action: {result.action}</Text>
        </Card>
    );
};


const ChatbotScreen = ({ navigation }) => {
  const [message, setMessage] = useState('');
  const [upi, setUpi] = useState('');
  const [amount, setAmount] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [result, setResult] = useState(null);
  const [error, setError] = useState('');

  const handleAnalyze = async () => {
    if (!message) {
      setError('Please enter a message to analyze.');
      return;
    }
    setIsLoading(true);
    setError('');
    setResult(null);
    try {
      const response = await analyzeChatbotRequest({
        message,
        upi,
        amount: parseFloat(amount) || 0,
        relationship: 'unknown', // This can be a Picker component later
        history: 0,
      });
      setResult(response);
    } catch (e) {
      setError('Failed to get analysis. Please try again.');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <ScrollView style={globalStyles.container}>
      <Card>
        <Text style={globalStyles.h1}>“Should I send?” Chatbot</Text>
        <Text style={globalStyles.subHeader}>Paste the message you received for a quick recommendation.</Text>
      </Card>
      
      <Card>
        <Text style={globalStyles.h3}>Message Details</Text>
        <TextInput
          style={styles.textInput}
          placeholder="e.g., Urgent! Pay ₹999 to..."
          placeholderTextColor={theme.colors.muted}
          multiline
          value={message}
          onChangeText={setMessage}
        />
        <View style={styles.inputRow}>
            <TextInput style={styles.input} placeholder="Amount (₹)" value={amount} onChangeText={setAmount} keyboardType="numeric" />
            <TextInput style={styles.input} placeholder="UPI / Payee" value={upi} onChangeText={setUpi} />
        </View>
        <TouchableOpacity style={[globalStyles.button, {marginTop: 12}]} onPress={handleAnalyze} disabled={isLoading}>
            <Text style={globalStyles.buttonText}>Analyze</Text>
        </TouchableOpacity>
      </Card>

      {isLoading && <ActivityIndicator size="large" color={theme.colors.accent} style={{marginTop: 20}} />}
      {error && <Text style={styles.errorText}>{error}</Text>}
      {result && <ResultCard result={result} />}

       <TouchableOpacity onPress={() => navigation.goBack()} style={[globalStyles.button, {marginTop: 16, flex: 0, alignSelf: 'flex-start'}]}>
          <Text style={globalStyles.buttonText}>← Back to Menu</Text>
      </TouchableOpacity>
    </ScrollView>
  );
};

const styles = StyleSheet.create({
    textInput: {
        width: '100%',
        minHeight: 140,
        padding: 12,
        borderRadius: 12,
        borderWidth: 1,
        borderColor: theme.colors.border,
        backgroundColor: theme.colors.glass,
        color: theme.colors.txt,
        textAlignVertical: 'top',
    },
    inputRow: {
        flexDirection: 'row',
        gap: 10,
        marginTop: 10,
    },
    input: {
        flex: 1,
        padding: 12,
        borderRadius: 12,
        borderWidth: 1,
        borderColor: theme.colors.border,
        backgroundColor: theme.colors.glass,
        color: theme.colors.txt,
    },
    errorText: {
        color: theme.colors.fraud,
        textAlign: 'center',
        marginTop: 10,
    }
});

export default ChatbotScreen;
