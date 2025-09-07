import React from 'react';
import { View } from 'react-native';
import { BlurView } from '@react-native-community/blur';
import LinearGradient from 'react-native-linear-gradient';
import { globalStyles } from '../styles/globalStyles';

const Card = ({ children, style }) => {
  return (
    <View style={[globalStyles.card, style]}>
      <BlurView
        style={StyleSheet.absoluteFill}
        blurType="dark"
        blurAmount={14}
      />
      <LinearGradient
        colors={['rgba(255,255,255,.08)', 'rgba(255,255,255,.02)']}
        style={globalStyles.cardBody}>
        {children}
      </LinearGradient>
    </View>
  );
};

// We need StyleSheet.absoluteFill for the BlurView to fill the Card
import { StyleSheet } from 'react-native';

export default Card;