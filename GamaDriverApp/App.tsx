import React, { useEffect, useState } from 'react';
import { BackHandler, StatusBar, StyleSheet, View } from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import { DriverTrip } from './src/api/types';
import { AuthProvider, useAuth } from './src/context/AuthContext';
import { ActiveTripScreen } from './src/screens/ActiveTripScreen';
import { HomeScreen } from './src/screens/HomeScreen';
import { LoginScreen } from './src/screens/LoginScreen';
import { TripHistoryScreen } from './src/screens/TripHistoryScreen';

import { sqliteService } from './src/services/sqliteService';
import { syncService } from './src/services/syncService';
import { tripService } from './src/services/tripService';

type AppScreen = 'HOME' | 'ACTIVE_TRIP' | 'TRIP_HISTORY';

const AppNavigator: React.FC = () => {
  const { token, user } = useAuth();
  const [currentScreen, setCurrentScreen] = useState<AppScreen>('HOME');
  const [activeTrip, setActiveTrip] = useState<DriverTrip | null>(null);
  const [isCheckingActiveTrip, setIsCheckingActiveTrip] = useState<boolean>(true);

  // Initialize SQLite and Sync Engine on startup
  useEffect(() => {
    const bootstrap = async () => {
      try {
        await sqliteService.initDb();
        await syncService.init();
      } catch (err) {
        console.warn('[App] Error initializing offline services:', err);
      }
    };
    bootstrap();
  }, []);

  // Check for in-progress active trip in local SQLite on startup / user change
  useEffect(() => {
    const recoverActiveTrip = async () => {
      if (!user) {
        setIsCheckingActiveTrip(false);
        return;
      }
      try {
        const active = await tripService.getActiveTrip(user.id);
        if (active && active.status === 'IN_PROGRESS') {
          console.log('[App] Recovered active in-progress trip from SQLite:', active.client_id);
          setActiveTrip(active);
          setCurrentScreen('ACTIVE_TRIP');
        }
      } catch (err) {
        console.warn('[App] Error recovering active trip:', err);
      } finally {
        setIsCheckingActiveTrip(false);
      }
    };

    if (token && user) {
      recoverActiveTrip();
    } else {
      setIsCheckingActiveTrip(false);
    }
  }, [token, user]);

  // Handle Android physical back button
  useEffect(() => {
    const onBackPress = () => {
      if (currentScreen !== 'HOME') {
        setCurrentScreen('HOME');
        return true; // handled
      }
      return false; // let default exit app
    };

    const subscription = BackHandler.addEventListener('hardwareBackPress', onBackPress);
    return () => subscription.remove();
  }, [currentScreen]);

  // Reset to Home screen if user logs out or changes
  useEffect(() => {
    if (!token) {
      setCurrentScreen('HOME');
      setActiveTrip(null);
    }
  }, [token]);

  if (!token || !user) {
    return <LoginScreen />;
  }

  if (isCheckingActiveTrip) {
    return <View style={styles.root} />;
  }

  switch (currentScreen) {
    case 'ACTIVE_TRIP':
      if (!activeTrip) {
        return (
          <HomeScreen
            onNavigateToActiveTrip={(trip) => {
              setActiveTrip(trip);
              setCurrentScreen('ACTIVE_TRIP');
            }}
            onNavigateToHistory={() => setCurrentScreen('TRIP_HISTORY')}
          />
        );
      }
      return (
        <ActiveTripScreen
          onBackToHome={() => setCurrentScreen('HOME')}
          onTripCompleted={() => {
            setActiveTrip(null);
            setCurrentScreen('HOME');
          }}
          trip={activeTrip}
        />
      );

    case 'TRIP_HISTORY':
      return <TripHistoryScreen onBackToHome={() => setCurrentScreen('HOME')} />;

    case 'HOME':
    default:
      return (
        <HomeScreen
          onNavigateToActiveTrip={(trip) => {
            setActiveTrip(trip);
            setCurrentScreen('ACTIVE_TRIP');
          }}
          onNavigateToHistory={() => setCurrentScreen('TRIP_HISTORY')}
        />
      );
  }
};

export default function App(): React.JSX.Element {
  return (
    <SafeAreaProvider>
      <StatusBar barStyle="light-content" />
      <SafeAreaView edges={['top', 'left', 'right']} style={styles.root}>
        <AuthProvider>
          <AppNavigator />
        </AuthProvider>
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
});
