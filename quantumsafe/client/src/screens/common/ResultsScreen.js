import React, { useState, useEffect, useCallback } from 'react';
import { View, Text, TouchableOpacity, ScrollView, StyleSheet, ActivityIndicator, FlatList } from 'react-native';
import { Picker } from '@react-native-picker/picker';
import { globalStyles, theme } from '../../styles/globalStyles';
import Card from '../../components/Card';
import { getResults } from '../../api/commonApi';
import { Badge } from '../../components/Badge';

const KpiCard = ({ label, value, color }) => (
    <View style={styles.kpi}>
        <Text style={styles.kpiLabel}>{label}</Text>
        <Text style={[styles.kpiValue, { color: color || theme.colors.txt }]}>{value}</Text>
    </View>
);

const ResultsScreen = ({ navigation }) => {
    const [results, setResults] = useState([]);
    const [filters, setFilters] = useState({
        feature: 'all',
        order: 'new',
    });
    const [page, setPage] = useState(1);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [kpiCounts, setKpiCounts] = useState({ TOTAL: 0, SAFE: 0, SUSPICIOUS: 0, FRAUD: 0 }); // Mocked for now

    const fetchData = useCallback(async () => {
        setIsLoading(true);
        setError('');
        try {
            const data = await getResults({ ...filters, page });
            setResults(data);
            // In a real app, the API would also return the counts for the KPIs
            // For now, we simulate it based on the current page's data
            const counts = data.reduce((acc, item) => {
                acc[item.verdict] = (acc[item.verdict] || 0) + 1;
                return acc;
            }, { SAFE: 0, SUSPICIOUS: 0, FRAUD: 0 });
            counts.TOTAL = data.length;
            setKpiCounts(counts);

        } catch (e) {
            setError('Failed to fetch results.');
        } finally {
            setIsLoading(false);
        }
    }, [filters, page]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const handleFilterChange = (key, value) => {
        setFilters(prev => ({ ...prev, [key]: value }));
        setPage(1); // Reset to first page on filter change
    };

    const renderRow = ({ item }) => (
        <View style={styles.tableRow}>
            <Text style={[styles.td, { flex: 1.5 }]}>{new Date(item.createdAt).toLocaleString()}</Text>
            <Text style={[styles.td, { textTransform: 'capitalize' }]}>{item.feature}</Text>
            <View style={[styles.td, { alignItems: 'center' }]}>
                <Badge verdict={item.verdict} />
            </View>
            <Text style={[styles.td, { textAlign: 'center' }]}>{item.score}</Text>
        </View>
    );

    return (
        <ScrollView style={globalStyles.container}>
            <Card>
                <Text style={globalStyles.h1}>Results</Text>
                <Text style={globalStyles.subHeader}>View and filter past analyses.</Text>
                <View style={styles.kpiContainer}>
                    <KpiCard label="Total" value={kpiCounts.TOTAL} />
                    <KpiCard label="Safe" value={kpiCounts.SAFE} color={theme.colors.safe} />
                    <KpiCard label="Suspicious" value={kpiCounts.SUSPICIOUS} color={theme.colors.warn} />
                    <KpiCard label="Fraud" value={kpiCounts.FRAUD} color={theme.colors.fraud} />
                </View>
            </Card>

            <Card>
                <View style={styles.filterContainer}>
                    <View style={styles.pickerWrapper}>
                        <Text style={styles.filterLabel}>Feature</Text>
                        <Picker
                            selectedValue={filters.feature}
                            style={styles.picker}
                            dropdownIconColor={theme.colors.txt}
                            onValueChange={(itemValue) => handleFilterChange('feature', itemValue)}>
                            <Picker.Item label="All" value="all" />
                            <Picker.Item label="Screenshot" value="screenshot" />
                            <Picker.Item label="Invoice" value="invoice" />
                            <Picker.Item label="QR" value="qr" />
                            <Picker.Item label="Chatbot" value="chatbot" />
                            <Picker.Item label="Micro-Fraud" value="microfraud" />
                        </Picker>
                    </View>
                     <View style={styles.pickerWrapper}>
                        <Text style={styles.filterLabel}>Sort</Text>
                        <Picker
                            selectedValue={filters.order}
                             style={styles.picker}
                             dropdownIconColor={theme.colors.txt}
                            onValueChange={(itemValue) => handleFilterChange('order', itemValue)}>
                            <Picker.Item label="Newest" value="new" />
                            <Picker.Item label="Oldest" value="old" />
                            <Picker.Item label="Highest Score" value="hi" />
                            <Picker.Item label="Lowest Score" value="lo" />
                        </Picker>
                    </View>
                </View>
            </Card>

            <Card>
                {isLoading ? <ActivityIndicator size="large" color={theme.colors.accent} /> : 
                error ? <Text style={styles.errorText}>{error}</Text> :
                (
                    <>
                        <View style={styles.tableHeader}>
                            <Text style={[styles.th, { flex: 1.5 }]}>When</Text>
                            <Text style={styles.th}>Feature</Text>
                            <Text style={styles.th}>Verdict</Text>
                            <Text style={[styles.th, { textAlign: 'center' }]}>Score</Text>
                        </View>
                        <FlatList
                            data={results}
                            renderItem={renderRow}
                            keyExtractor={(item) => item.id}
                            ListEmptyComponent={<Text style={styles.emptyText}>No records match your filters.</Text>}
                        />
                        <View style={styles.pagination}>
                            <TouchableOpacity style={globalStyles.button} onPress={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}>
                                <Text style={globalStyles.buttonText}>Prev</Text>
                            </TouchableOpacity>
                            <Text style={styles.pageText}>Page {page}</Text>
                            <TouchableOpacity style={globalStyles.button} onPress={() => setPage(p => p + 1)} disabled={results.length < 50}>
                                <Text style={globalStyles.buttonText}>Next</Text>
                            </TouchableOpacity>
                        </View>
                    </>
                )}
            </Card>

             <TouchableOpacity onPress={() => navigation.goBack()} style={[globalStyles.button, { marginTop: 16, flex: 0, alignSelf: 'flex-start' }]}>
                <Text style={globalStyles.buttonText}>← Back to Menu</Text>
            </TouchableOpacity>

        </ScrollView>
    );
};

const styles = StyleSheet.create({
    kpiContainer: { flexDirection: 'row', justifyContent: 'space-around', gap: 10 },
    kpi: { flex: 1, padding: 12, borderRadius: 12, backgroundColor: theme.colors.glass, borderWidth: 1, borderColor: theme.colors.border, alignItems: 'center' },
    kpiLabel: { color: theme.colors.muted, fontSize: 12, marginBottom: 4 },
    kpiValue: { fontSize: 18, fontWeight: '800' },
    filterContainer: { flexDirection: 'row', gap: 10 },
    pickerWrapper: { flex: 1, borderWidth: 1, borderColor: theme.colors.border, borderRadius: 12, backgroundColor: theme.colors.glass },
    filterLabel: { color: theme.colors.muted, fontSize: 12, paddingLeft: 12, paddingTop: 6 },
    picker: { color: theme.colors.txt, height: 40 },
    tableHeader: { flexDirection: 'row', paddingBottom: 8, borderBottomWidth: 1, borderBottomColor: theme.colors.border },
    th: { color: theme.colors.muted, fontWeight: 'bold', fontSize: 13, flex: 1 },
    tableRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 12, borderBottomWidth: 1, borderBottomColor: theme.colors.border },
    td: { color: theme.colors.txt, fontSize: 12, flex: 1 },
    pagination: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginTop: 16, gap: 10 },
    pageText: { color: theme.colors.muted },
    errorText: { color: theme.colors.fraud, textAlign: 'center', marginVertical: 10 },
    emptyText: { color: theme.colors.muted, textAlign: 'center', marginTop: 20, fontStyle: 'italic' },
});

export default ResultsScreen;
