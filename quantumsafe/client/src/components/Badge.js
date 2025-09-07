import React from 'react';
import { View, Text } from 'react-native';
import { globalStyles } from '../styles/globalStyles';

export const Badge = ({ verdict }) => {
  const verdictLower = verdict?.toLowerCase() || 'safe';
  let style, textStyle;

  switch (verdictLower) {
    case 'fraud':
      style = globalStyles.badgeFraud;
      textStyle = globalStyles.badgeFraudText;
      break;
    case 'suspicious':
      style = globalStyles.badgeWarn;
      textStyle = globalStyles.badgeWarnText;
      break;
    default:
      style = globalStyles.badgeSafe;
      textStyle = globalStyles.badgeSafeText;
  }
  return (
    <View style={[globalStyles.badge, style]}>
      <Text style={[globalStyles.badgeText, textStyle]}>{verdict}</Text>
    </View>
  );
};
