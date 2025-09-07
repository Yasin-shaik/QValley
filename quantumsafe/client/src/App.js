import React from 'react';
import { SafeAreaView, StatusBar, StyleSheet } from 'react-native';
import LinearGradient from 'react-native-linear-gradient';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';

import { theme, globalStyles } from './styles/globalStyles';

// Import all screens
import DashboardScreen from './screens/bank/DashboardScreen';
import MenuScreen from './screens/common/MenuScreen';
import ChatbotScreen from './screens/common/ChatbotScreen';
import MicrofraudScreen from './screens/common/MicrofraudScreen';
import ScreenshotScreen from './screens/common/ScreenshotScreen';
import ResultsScreen from './screens/common/ResultsScreen'; // <-- Import the final screen

const Stack = createNativeStackNavigator();

const App = () => {
  return (
    <LinearGradient
      colors={[theme.colors.gradientStart, theme.colors.bg]}
      style={globalStyles.background}
      start={{ x: 0.1, y: 0.1 }}
      end={{ x: 0.5, y: 0.9 }}>
      <SafeAreaView style={styles.safeArea}>
        <StatusBar barStyle="light-content" />
        <NavigationContainer>
          <Stack.Navigator
            initialRouteName="Menu"
            screenOptions={{
              headerShown: false,
            }}>
            <Stack.Screen name="Menu" component={MenuScreen} />
            <Stack.Screen name="Dashboard" component={DashboardScreen} />
            <Stack.Screen name="Chatbot" component={ChatbotScreen} />
            <Stack.Screen name="Microfraud" component={MicrofraudScreen} />
            <Stack.Screen name="Screenshot" component={ScreenshotScreen} />
            <Stack.Screen name="Results" component={ResultsScreen} /> {/* <-- Add to navigator */}
          </Stack.Navigator>
        </NavigationContainer>
      </SafeAreaView>
    </LinearGradient>
  );
};

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
  },
});

export default App;