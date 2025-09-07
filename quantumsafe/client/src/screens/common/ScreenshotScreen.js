import React, { useState } from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet, ActivityIndicator, Image } from 'react-native';
import DocumentPicker from 'react-native-document-picker';
import { globalStyles, theme } from '../../styles/globalStyles';
import Card from '../../components/Card';
import { analyzeImage } from '../../api/commonApi';
// Note: For a real app, you would install a QR decoding library.
// We will simulate the function for this build.
// import QrDecoder from 'react-native-qr-decode-image-camera';

// Simulated QR Decoder function
const decodeQrCode = async (fileUri) => {
    // In a real app, this would use a library to decode the image at fileUri
    // For this demo, we'll return a mock value if the filename suggests it's a QR
    if (fileUri.toLowerCase().includes('qr')) {
        console.log(`Simulating QR scan for: ${fileUri}`);
        return `upi://pay?pa=test-phishing-link@upi&pn=Test&am=100.00`;
    }
    return '';
};


const FilePickerSection = ({ title, onFilesSelect, selectedFiles }) => (
    <View style={{marginBottom: 16}}>
        <Text style={globalStyles.h3}>{title}</Text>
        <TouchableOpacity style={styles.filePickerButton} onPress={onFilesSelect}>
            <Text style={globalStyles.buttonText}>
                {selectedFiles.length > 0 ? `${selectedFiles.length} file(s) selected` : 'Select Files'}
            </Text>
        </TouchableOpacity>
    </View>
);

const ResultDisplay = ({ results }) => (
    results.map((r, index) => (
        <Card key={index} style={{marginBottom: 10}}>
            <Text style={globalStyles.note}>File: {r.fileName}</Text>
            {r.previewUri && <Image source={{ uri: r.previewUri }} style={styles.previewImage} />}
            <Text style={globalStyles.note}>Score: {r.score} | Verdict: {r.verdict}</Text>
            <Text style={[globalStyles.note, {marginTop: 8}]}>Why: {r.reasons?.join(' • ')}</Text>
        </Card>
    ))
);


const ScreenshotScreen = ({ navigation }) => {
    const [shots, setShots] = useState([]);
    const [invoices, setInvoices] = useState([]);
    const [qrs, setQrs] = useState([]);
    
    const [results, setResults] = useState({ screenshot: [], invoice: [], qr: [] });
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');

    const handleSelectFiles = async (setter) => {
        try {
            const docs = await DocumentPicker.pick({
                type: [DocumentPicker.types.images],
                allowMultiSelection: true,
            });
            setter(docs);
        } catch (err) {
            if (!DocumentPicker.isCancel(err)) setError('Error selecting files.');
        }
    };

    const handleAnalyze = async () => {
        setIsLoading(true);
        setError('');
        setResults({ screenshot: [], invoice: [], qr: [] });
        let allResults = { screenshot: [], invoice: [], qr: [] };

        try {
            for (const file of shots) {
                const res = await analyzeImage(file, '', 'screenshot');
                allResults.screenshot.push({ ...res, fileName: file.name, previewUri: file.uri });
            }
            for (const file of invoices) {
                const res = await analyzeImage(file, '', 'invoice');
                allResults.invoice.push({ ...res, fileName: file.name, previewUri: file.uri });
            }
            for (const file of qrs) {
                const decodedText = await decodeQrCode(file.uri); // Client-side QR decoding
                const res = await analyzeImage(file, decodedText, 'qr');
                allResults.qr.push({ ...res, fileName: file.name, previewUri: file.uri });
            }
            setResults(allResults);
        } catch (e) {
            setError('An error occurred during analysis.');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <ScrollView style={globalStyles.container}>
            <Card>
                <Text style={globalStyles.h1}>Receipt • Invoice • QR Analyzer</Text>
                <Text style={globalStyles.subHeader}>Upload images to detect tampering and risk.</Text>
            </Card>

            <Card>
                <FilePickerSection title="1) Receipt / Screenshot" onFilesSelect={() => handleSelectFiles(setShots)} selectedFiles={shots} />
                <FilePickerSection title="2) Invoice Analyzer" onFilesSelect={() => handleSelectFiles(setInvoices)} selectedFiles={invoices} />
                <FilePickerSection title="3) QR Code Verifier" onFilesSelect={() => handleSelectFiles(setQrs)} selectedFiles={qrs} />
            </Card>

            <TouchableOpacity style={[globalStyles.button, {marginVertical: 16}]} onPress={handleAnalyze} disabled={isLoading}>
                <Text style={globalStyles.buttonText}>Analyze All</Text>
            </TouchableOpacity>

            {isLoading && <ActivityIndicator size="large" color={theme.colors.accent} />}
            {error && <Text style={styles.errorText}>{error}</Text>}

            {(results.screenshot.length > 0 || results.invoice.length > 0 || results.qr.length > 0) && (
                 <Card>
                    <Text style={globalStyles.h3}>Analysis Results</Text>
                    {results.screenshot.length > 0 && <ResultDisplay results={results.screenshot} />}
                    {results.invoice.length > 0 && <ResultDisplay results={results.invoice} />}
                    {results.qr.length > 0 && <ResultDisplay results={results.qr} />}
                </Card>
            )}

            <TouchableOpacity onPress={() => navigation.goBack()} style={[globalStyles.button, { marginTop: 16, flex: 0, alignSelf: 'flex-start' }]}>
                <Text style={globalStyles.buttonText}>← Back to Menu</Text>
            </TouchableOpacity>
        </ScrollView>
    );
};

const styles = StyleSheet.create({
    filePickerButton: { ...globalStyles.button, flex: 0 },
    errorText: { color: theme.colors.fraud, textAlign: 'center', marginVertical: 10 },
    previewImage: { width: '100%', height: 150, borderRadius: 12, resizeMode: 'contain', marginTop: 8 }
});

export default ScreenshotScreen;
