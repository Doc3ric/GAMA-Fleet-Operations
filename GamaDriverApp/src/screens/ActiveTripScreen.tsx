import React, { useState } from 'react';
import {
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { DriverTrip } from '../api/types';
import { Button } from '../components/Button';
import { Card } from '../components/Card';
import { Header } from '../components/Header';
import { SyncStatusBar } from '../components/SyncStatusBar';
import { geocodingService } from '../services/geocodingService';
import { locationService, LocationResult } from '../services/locationService';
import { tripService } from '../services/tripService';

interface ActiveTripScreenProps {
  trip: DriverTrip;
  onTripCompleted: () => void;
  onBackToHome: () => void;
}

export const ActiveTripScreen: React.FC<ActiveTripScreenProps> = ({
  trip,
  onTripCompleted,
  onBackToHome,
}) => {
  type EndTripPhase = 'idle' | 'locating' | 'geocoding' | 'submitting';
  const [endTripPhase, setEndTripPhase] = useState<EndTripPhase>('idle');
  const [isCancelling, setIsCancelling] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleEndTrip = () => {
    if (endTripPhase !== 'idle') {
      return;
    }
    submitEndTrip();
  };

  const submitEndTrip = async () => {
    // 1. Prevent duplicate submissions
    if (endTripPhase !== 'idle') {
      return;
    }

    setErrorMessage(null);

    // 2. Hardware GPS location fix
    setEndTripPhase('locating');
    let location: LocationResult;
    try {
      location = await locationService.getCurrentLocation();
    } catch (locErr: any) {
      setEndTripPhase('idle');
      const err = locErr?.message || 'Unable to acquire destination GPS fix. Please ensure location is enabled.';
      setErrorMessage(err);
      return;
    }

    // 3. Supplementary Nominatim Reverse Geocoding
    setEndTripPhase('geocoding');
    let destinationAddress: string | null = null;
    try {
      destinationAddress = await geocodingService.reverseGeocode(
        location.latitude,
        location.longitude
      );
    } catch {
      destinationAddress = null;
    }

    // 4. Save destination GPS locally and queue synchronization
    setEndTripPhase('submitting');
    const now = new Date();
    const timeOut = now.toTimeString().split(' ')[0] || '10:00:00';

    const payload = {
      time_out: timeOut,
      destination_latitude: location.latitude,
      destination_longitude: location.longitude,
      destination_accuracy: location.accuracy,
      destination_address: destinationAddress,
      remarks: 'Trip ended via GamaDriverApp',
    };

    try {
      await tripService.endTrip(trip.client_id, payload, trip.vehicle);
      setEndTripPhase('idle');
      onTripCompleted();
    } catch (error: any) {
      setEndTripPhase('idle');
      const msg = error?.message || 'Unable to record trip completion.';
      setErrorMessage(msg);
      Alert.alert('End Trip Error', `Could not save trip completion locally.\n\n${msg}`);
    }
  };

  const getEndButtonTitle = () => {
    switch (endTripPhase) {
      case 'locating':
        return 'GETTING LOCATION...';
      case 'geocoding':
        return 'FINDING ADDRESS...';
      case 'submitting':
        return 'COMPLETING TRIP...';
      default:
        return 'END TRIP';
    }
  };

  const handleCancelTrip = () => {
    Alert.alert(
      'Cancel Trip',
      'Are you sure you want to cancel this in-progress trip? This will record the trip as CANCELLED.',
      [
        { text: 'No, Keep Trip', style: 'cancel' },
        {
          text: 'Yes, Cancel Trip',
          style: 'destructive',
          onPress: submitCancelTrip,
        },
      ]
    );
  };

  const submitCancelTrip = async () => {
    setIsCancelling(true);
    setErrorMessage(null);

    try {
      await tripService.cancelTrip(trip.client_id, 'Cancelled by driver from mobile app');
      onTripCompleted();
    } catch (error: any) {
      setErrorMessage(error?.message || 'Unable to cancel trip.');
    } finally {
      setIsCancelling(false);
    }
  };

  return (
    <View style={styles.container}>
      <Header
        onBack={onBackToHome}
        subtitle="In-Progress Trip Tracking"
        title="ACTIVE TRIP"
      />
      <SyncStatusBar />

      <ScrollView contentContainerStyle={styles.content}>
        {/* Live Status Banner */}
        <View style={styles.statusBanner}>
          <View style={styles.liveIndicator}>
            <View style={styles.liveDot} />
            <Text style={styles.liveText}>TRIP IN PROGRESS</Text>
          </View>
          <Text style={styles.tripIdText}>
            {trip.id > 0 ? `Trip #${trip.id}` : `Local #${trip.client_id.substring(0, 8)}`}
          </Text>
        </View>

        {errorMessage ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{errorMessage}</Text>
          </View>
        ) : null}

        {/* Assigned Vehicle Card */}
        <Card>
          <Text style={styles.cardHeaderTitle}>ASSIGNED VEHICLE</Text>
          <View style={styles.vehicleRow}>
            <View style={styles.codeBadge}>
              <Text style={styles.codeText}>{trip.vehicle?.equipment_code || 'VEHICLE'}</Text>
            </View>
            <Text style={styles.plateText}>{trip.vehicle?.plate_number || 'NO PLATE'}</Text>
          </View>
          <Text style={styles.modelText}>{trip.vehicle?.model || 'Standard Unit'}</Text>
        </Card>

        {/* Departure / Origin Details Card */}
        <Card>
          <Text style={styles.cardHeaderTitle}>DEPARTURE / ORIGIN</Text>

          <View style={styles.infoRow}>
            <Text style={styles.label}>DEPARTURE TIME:</Text>
            <Text style={styles.valueHighlight}>{trip.time_in}</Text>
          </View>

          <View style={styles.infoRow}>
            <Text style={styles.label}>DATE:</Text>
            <Text style={styles.value}>{trip.trip_date}</Text>
          </View>

          <View style={styles.divider} />

          <View style={styles.infoRow}>
            <Text style={styles.label}>ORIGIN LOCATION:</Text>
            <Text style={styles.addressValue}>{trip.origin?.address || 'Captured Location'}</Text>
          </View>

          <View style={styles.coordsRow}>
            <Text style={styles.coordsText}>
              GPS: {trip.origin?.latitude?.toFixed(5)}, {trip.origin?.longitude?.toFixed(5)}
            </Text>
            {trip.origin?.accuracy ? (
              <Text style={styles.accuracyText}>±{trip.origin.accuracy}m</Text>
            ) : null}
          </View>
        </Card>

        {/* End Trip Action */}
        <View style={styles.actionsSection}>
          <Button
            disabled={endTripPhase !== 'idle' || isCancelling}
            loading={endTripPhase !== 'idle'}
            onPress={handleEndTrip}
            style={styles.endTripButton}
            title={getEndButtonTitle()}
            variant="danger"
          />
          <Text style={styles.endTip}>
            Tap when you have arrived at your destination to complete this trip ticket.
          </Text>

          <Button
            disabled={endTripPhase !== 'idle' || isCancelling}
            loading={isCancelling}
            onPress={handleCancelTrip}
            style={styles.cancelButton}
            title="Cancel Trip"
            variant="secondary"
          />
        </View>
      </ScrollView>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f1f5f9',
  },
  content: {
    padding: 16,
    paddingBottom: 40,
  },
  statusBanner: {
    backgroundColor: '#0f172a',
    borderRadius: 12,
    padding: 14,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 10,
  },
  liveIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  liveDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: '#22c55e', // Bright green dot
    marginRight: 8,
  },
  liveText: {
    color: '#ffffff',
    fontSize: 13,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  tripIdText: {
    color: '#94a3b8',
    fontSize: 12,
    fontWeight: '600',
  },
  errorBox: {
    backgroundColor: '#fef2f2',
    borderColor: '#fca5a5',
    borderWidth: 1,
    borderRadius: 10,
    padding: 12,
    marginBottom: 10,
  },
  errorText: {
    color: '#b91c1c',
    fontSize: 13,
    fontWeight: '600',
  },
  cardHeaderTitle: {
    fontSize: 11,
    fontWeight: '800',
    color: '#64748b',
    letterSpacing: 0.5,
    marginBottom: 8,
  },
  vehicleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 4,
  },
  codeBadge: {
    backgroundColor: '#1e293b',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 6,
  },
  codeText: {
    color: '#ffffff',
    fontSize: 16,
    fontWeight: '800',
  },
  plateText: {
    fontSize: 15,
    fontWeight: '700',
    color: '#334155',
  },
  modelText: {
    fontSize: 13,
    color: '#64748b',
  },
  infoRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginVertical: 4,
  },
  label: {
    fontSize: 12,
    color: '#64748b',
    fontWeight: '600',
  },
  value: {
    fontSize: 13,
    color: '#0f172a',
    fontWeight: '700',
  },
  valueHighlight: {
    fontSize: 16,
    color: '#2563eb',
    fontWeight: '800',
  },
  addressValue: {
    fontSize: 13,
    color: '#0f172a',
    fontWeight: '600',
    flex: 1,
    textAlign: 'right',
    marginLeft: 8,
  },
  divider: {
    height: 1,
    backgroundColor: '#f1f5f9',
    marginVertical: 8,
  },
  coordsRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 6,
  },
  coordsText: {
    fontSize: 11,
    color: '#94a3b8',
    fontFamily: 'monospace',
  },
  accuracyText: {
    fontSize: 11,
    color: '#16a34a',
    fontWeight: '600',
  },
  devNoteBox: {
    marginTop: 10,
    padding: 8,
    backgroundColor: '#f8fafc',
    borderRadius: 6,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  devNoteText: {
    fontSize: 11,
    color: '#64748b',
    fontStyle: 'italic',
  },
  actionsSection: {
    marginTop: 16,
  },
  endTripButton: {
    minHeight: 58,
    borderRadius: 14,
  },
  endTip: {
    textAlign: 'center',
    color: '#64748b',
    fontSize: 12,
    marginTop: 8,
    marginBottom: 14,
  },
  cancelButton: {
    minHeight: 44,
  },
});
