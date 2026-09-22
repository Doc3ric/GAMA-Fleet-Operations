import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { DriverTrip, VehicleAssignment } from '../api/types';
import { Button } from '../components/Button';
import { Card } from '../components/Card';
import { Header } from '../components/Header';
import { SyncStatusBar } from '../components/SyncStatusBar';
import { useAuth } from '../context/AuthContext';
import { assignmentService } from '../services/assignmentService';
import { geocodingService } from '../services/geocodingService';
import { locationService, LocationResult } from '../services/locationService';
import { tripService } from '../services/tripService';
import { generateUUID } from '../utils/uuid';

interface HomeScreenProps {
  onNavigateToActiveTrip: (trip: DriverTrip) => void;
  onNavigateToHistory: () => void;
}

export const HomeScreen: React.FC<HomeScreenProps> = ({
  onNavigateToActiveTrip,
  onNavigateToHistory,
}) => {
  const { user, logout } = useAuth();
  const [assignment, setAssignment] = useState<VehicleAssignment | null>(null);
  const [activeTrip, setActiveTrip] = useState<DriverTrip | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  type StartTripPhase = 'idle' | 'locating' | 'geocoding' | 'submitting';
  const [startTripPhase, setStartTripPhase] = useState<StartTripPhase>('idle');
  const [isRefreshing, setIsRefreshing] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const loadData = useCallback(async () => {
    setErrorMessage(null);
    try {
      // 1. Fetch active assignment
      const activeAssignment = await assignmentService.getActiveAssignment();
      setAssignment(activeAssignment);

      // 2. Check if an active IN_PROGRESS trip exists in local SQLite or server
      const active = await tripService.getActiveTrip(user?.id || 2);
      setActiveTrip(active);
    } catch (error: any) {
      setErrorMessage(error?.message || 'Failed to load vehicle assignment.');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleRefresh = () => {
    setIsRefreshing(true);
    loadData();
  };

  const handleStartTrip = async () => {
    // 1. Prevent duplicate button taps
    if (startTripPhase !== 'idle') {
      return;
    }

    if (!assignment) {
      Alert.alert('No Vehicle', 'Cannot start trip without an active vehicle assignment.');
      return;
    }

    setErrorMessage(null);

    // 2. Obtain single hardware GPS fix
    setStartTripPhase('locating');
    let location: LocationResult;
    try {
      location = await locationService.getCurrentLocation();
    } catch (locErr: any) {
      setStartTripPhase('idle');
      const err = locErr?.message || 'Unable to acquire GPS fix. Please ensure location is enabled.';
      setErrorMessage(err);
      return;
    }

    // 3. Supplementary Nominatim Reverse Geocoding
    setStartTripPhase('geocoding');
    let originAddress: string | null = null;
    try {
      originAddress = await geocodingService.reverseGeocode(
        location.latitude,
        location.longitude
      );
    } catch {
      // Nominatim failure must never block trip saving
      originAddress = null;
    }

    // 4. Submit real coordinates and address to Laravel API
    setStartTripPhase('submitting');
    const now = new Date();
    const timeIn = now.toTimeString().split(' ')[0] || '08:00:00';
    const tripDate = now.toISOString().split('T')[0];
    const clientId = generateUUID();

    const payload = {
      client_id: clientId,
      vehicle_id: assignment.vehicle_id,
      trip_date: tripDate,
      time_in: timeIn,
      origin_latitude: location.latitude,
      origin_longitude: location.longitude,
      origin_accuracy: location.accuracy,
      origin_address: originAddress,
      remarks: 'Trip initiated via GamaDriverApp',
    };

    try {
      const createdTrip = await tripService.startTrip(payload, user?.id || 2, assignment);
      setActiveTrip(createdTrip);
      setStartTripPhase('idle');
      onNavigateToActiveTrip(createdTrip);
    } catch (error: any) {
      setStartTripPhase('idle');
      const msg = error?.message || 'Unable to record trip locally.';
      setErrorMessage(msg);
      Alert.alert('Start Trip Error', `Could not save trip to local storage.\n\n${msg}`);
    }
  };

  const getStartButtonTitle = () => {
    if (!assignment) return 'NO VEHICLE ASSIGNED';
    switch (startTripPhase) {
      case 'locating':
        return 'GETTING LOCATION...';
      case 'geocoding':
        return 'FINDING ADDRESS...';
      case 'submitting':
        return 'STARTING TRIP...';
      default:
        return 'START TRIP';
    }
  };

  const handleLogout = () => {
    Alert.alert('Sign Out', 'Are you sure you want to log out of GamaDriverApp?', [
      { text: 'Cancel', style: 'cancel' },
      { text: 'Log Out', style: 'destructive', onPress: logout },
    ]);
  };

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator color="#2563eb" size="large" />
        <Text style={styles.loadingText}>Loading driver information...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <Header
        onLogout={handleLogout}
        subtitle={user?.email}
        title="GAMA DRIVER"
      />
      <SyncStatusBar />

      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={<RefreshControl onRefresh={handleRefresh} refreshing={isRefreshing} />}
      >
        {/* Welcome Greeting */}
        <View style={styles.greetingSection}>
          <Text style={styles.welcomeText}>Good day,</Text>
          <Text style={styles.driverName}>{user?.name || 'Driver'}</Text>
        </View>

        {/* Error Notification */}
        {errorMessage ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{errorMessage}</Text>
          </View>
        ) : null}

        {/* Active Trip Banner if In Progress */}
        {activeTrip ? (
          <Card style={styles.activeTripCard}>
            <View style={styles.activeBadgeRow}>
              <View style={styles.pulseDot} />
              <Text style={styles.activeTripBadge}>TRIP IN PROGRESS</Text>
            </View>

            <Text style={styles.activeTripInfo}>
              Vehicle: <Text style={styles.boldText}>{activeTrip.vehicle?.equipment_code || 'Assigned Vehicle'}</Text>
            </Text>
            <Text style={styles.activeTripTime}>Started at: {activeTrip.time_in}</Text>

            <Button
              onPress={() => onNavigateToActiveTrip(activeTrip)}
              style={styles.continueButton}
              title="Continue to Active Trip →"
              variant="primary"
            />
          </Card>
        ) : null}

        {/* Assigned Vehicle Card */}
        <Card style={styles.vehicleCard}>
          <View style={styles.sectionHeaderRow}>
            <Text style={styles.sectionHeaderTitle}>ASSIGNED VEHICLE</Text>
            <TouchableOpacity onPress={handleRefresh}>
              <Text style={styles.refreshLink}>↻ Refresh</Text>
            </TouchableOpacity>
          </View>

          {assignment ? (
            <View style={styles.vehicleInfo}>
              <View style={styles.codePlateRow}>
                <View style={styles.codeBadge}>
                  <Text style={styles.codeText}>{assignment.equipment_code}</Text>
                </View>
                <Text style={styles.plateText}>{assignment.plate_number || 'NO PLATE'}</Text>
              </View>

              <View style={styles.divider} />

              <View style={styles.metaRow}>
                <Text style={styles.metaLabel}>MODEL:</Text>
                <Text style={styles.metaValue}>{assignment.model || 'Standard'}</Text>
              </View>
              <View style={styles.metaRow}>
                <Text style={styles.metaLabel}>TYPE:</Text>
                <Text style={styles.metaValue}>{assignment.vehicle_type || 'Commercial'}</Text>
              </View>
              <View style={styles.metaRow}>
                <Text style={styles.metaLabel}>ASSIGNMENT DATE:</Text>
                <Text style={styles.metaValue}>{assignment.assigned_from}</Text>
              </View>
            </View>
          ) : (
            <View style={styles.noAssignmentBox}>
              <Text style={styles.noAssignmentTitle}>No Vehicle Assigned</Text>
              <Text style={styles.noAssignmentSub}>
                There is currently no vehicle assigned to your driver account for today. Please contact dispatch.
              </Text>
            </View>
          )}
        </Card>

        {/* Action Button: START TRIP */}
        {!activeTrip && (
          <View style={styles.actionContainer}>
            <Button
              disabled={!assignment || startTripPhase !== 'idle'}
              loading={startTripPhase !== 'idle'}
              onPress={handleStartTrip}
              style={styles.startTripButton}
              title={getStartButtonTitle()}
              variant="success"
            />
            {assignment && (
              <Text style={styles.startTipText}>
                Tapping will record your departure and origin location.
              </Text>
            )}
          </View>
        )}

        {/* Navigation to History */}
        <TouchableOpacity
          activeOpacity={0.7}
          onPress={onNavigateToHistory}
          style={styles.historyNavCard}
        >
          <View style={styles.historyNavContent}>
            <Text style={styles.historyNavTitle}>📋 Trip History</Text>
            <Text style={styles.historyNavSub}>View past completed and cancelled trips</Text>
          </View>
          <Text style={styles.chevron}>›</Text>
        </TouchableOpacity>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f1f5f9',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f1f5f9',
  },
  loadingText: {
    marginTop: 12,
    color: '#64748b',
    fontSize: 14,
  },
  content: {
    padding: 16,
    paddingBottom: 40,
  },
  greetingSection: {
    marginBottom: 12,
  },
  welcomeText: {
    fontSize: 14,
    color: '#64748b',
    fontWeight: '500',
  },
  driverName: {
    fontSize: 22,
    fontWeight: '800',
    color: '#0f172a',
  },
  errorBox: {
    backgroundColor: '#fef2f2',
    borderColor: '#fca5a5',
    borderWidth: 1,
    borderRadius: 10,
    padding: 12,
    marginBottom: 12,
  },
  errorText: {
    color: '#b91c1c',
    fontSize: 13,
    fontWeight: '600',
  },
  activeTripCard: {
    backgroundColor: '#eff6ff',
    borderColor: '#93c5fd',
    borderWidth: 1.5,
  },
  activeBadgeRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 8,
  },
  pulseDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#2563eb',
    marginRight: 8,
  },
  activeTripBadge: {
    fontSize: 13,
    fontWeight: '800',
    color: '#1d4ed8',
    letterSpacing: 0.5,
  },
  activeTripInfo: {
    fontSize: 15,
    color: '#1e293b',
    marginBottom: 4,
  },
  boldText: {
    fontWeight: '700',
  },
  activeTripTime: {
    fontSize: 13,
    color: '#64748b',
    marginBottom: 14,
  },
  continueButton: {
    backgroundColor: '#2563eb',
    minHeight: 46,
  },
  vehicleCard: {
    padding: 16,
  },
  sectionHeaderRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  sectionHeaderTitle: {
    fontSize: 12,
    fontWeight: '800',
    color: '#475569',
    letterSpacing: 0.5,
  },
  refreshLink: {
    fontSize: 12,
    color: '#2563eb',
    fontWeight: '600',
  },
  vehicleInfo: {
    marginTop: 4,
  },
  codePlateRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  codeBadge: {
    backgroundColor: '#0f172a',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 8,
  },
  codeText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  plateText: {
    fontSize: 16,
    fontWeight: '700',
    color: '#334155',
  },
  divider: {
    height: 1,
    backgroundColor: '#e2e8f0',
    marginVertical: 10,
  },
  metaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginVertical: 4,
  },
  metaLabel: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '600',
  },
  metaValue: {
    fontSize: 13,
    color: '#0f172a',
    fontWeight: '700',
  },
  noAssignmentBox: {
    paddingVertical: 16,
    alignItems: 'center',
  },
  noAssignmentTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#64748b',
    marginBottom: 6,
  },
  noAssignmentSub: {
    fontSize: 13,
    color: '#94a3b8',
    textAlign: 'center',
    lineHeight: 18,
  },
  actionContainer: {
    marginVertical: 14,
  },
  startTripButton: {
    minHeight: 58,
    borderRadius: 14,
  },
  startTipText: {
    textAlign: 'center',
    color: '#64748b',
    fontSize: 12,
    marginTop: 8,
  },
  historyNavCard: {
    backgroundColor: '#ffffff',
    borderRadius: 12,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: '#e2e8f0',
    marginTop: 10,
  },
  historyNavContent: {
    flex: 1,
  },
  historyNavTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: '#0f172a',
    marginBottom: 2,
  },
  historyNavSub: {
    fontSize: 12,
    color: '#64748b',
  },
  chevron: {
    fontSize: 24,
    color: '#94a3b8',
    fontWeight: '300',
    marginLeft: 10,
  },
});
