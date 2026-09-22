import React, { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { DriverTrip, SyncStatus } from '../api/types';
import { Card } from '../components/Card';
import { Header } from '../components/Header';
import { SyncStatusBar } from '../components/SyncStatusBar';
import { syncService } from '../services/syncService';
import { tripService } from '../services/tripService';

interface TripHistoryScreenProps {
  onBackToHome: () => void;
}

export const TripHistoryScreen: React.FC<TripHistoryScreenProps> = ({ onBackToHome }) => {
  const [trips, setTrips] = useState<DriverTrip[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const loadTrips = useCallback(async () => {
    setErrorMessage(null);
    try {
      const data = await tripService.getTrips({ per_page: 30 });
      setTrips(data || []);
    } catch (error: any) {
      setErrorMessage(error?.message || 'Failed to load trip history.');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  useEffect(() => {
    loadTrips();

    // Subscribe to sync events to reload trips when queue items are processed
    const unsubscribe = syncService.subscribe(() => {
      loadTrips();
    });

    return () => {
      unsubscribe();
    };
  }, [loadTrips]);

  const handleRefresh = async () => {
    setIsRefreshing(true);
    try {
      await syncService.syncNow();
    } catch {
      // Sync handled gracefully
    }
    await loadTrips();
  };

  const renderStatusBadge = (status: string) => {
    let backgroundColor = '#f1f5f9';
    let textColor = '#475569';

    if (status === 'COMPLETED') {
      backgroundColor = '#dcfce7';
      textColor = '#15803d';
    } else if (status === 'IN_PROGRESS') {
      backgroundColor = '#dbeafe';
      textColor = '#1d4ed8';
    } else if (status === 'CANCELLED') {
      backgroundColor = '#fee2e2';
      textColor = '#b91c1c';
    }

    return (
      <View style={[styles.statusBadge, { backgroundColor }]}>
        <Text style={[styles.statusBadgeText, { color: textColor }]}>{status}</Text>
      </View>
    );
  };

  const renderSyncBadge = (syncStatus?: SyncStatus) => {
    if (!syncStatus) return null;

    let backgroundColor = '#f1f5f9';
    let textColor = '#64748b';
    let label: string = syncStatus;

    switch (syncStatus) {
      case 'SYNCED':
        backgroundColor = '#ecfdf5';
        textColor = '#059669';
        label = '✓ SYNCED';
        break;
      case 'PENDING':
        backgroundColor = '#fef3c7';
        textColor = '#d97706';
        label = '⏳ PENDING';
        break;
      case 'SYNCING':
        backgroundColor = '#eff6ff';
        textColor = '#2563eb';
        label = '🔄 SYNCING';
        break;
      case 'FAILED':
        backgroundColor = '#fee2e2';
        textColor = '#dc2626';
        label = '⚠️ FAILED';
        break;
    }

    return (
      <View style={[styles.syncBadge, { backgroundColor }]}>
        <Text style={[styles.syncBadgeText, { color: textColor }]}>{label}</Text>
      </View>
    );
  };

  const renderTripItem = ({ item }: { item: DriverTrip }) => {
    const isLocalOnly = !item.id || item.id === 0;

    return (
      <Card style={styles.tripCard}>
        {/* Header row: Date, Ticket #, and Badges */}
        <View style={styles.tripCardHeader}>
          <View>
            <Text style={styles.tripDate}>{item.trip_date}</Text>
            <Text style={styles.tripId}>
              {isLocalOnly
                ? `Ticket #Local (${item.client_id.substring(0, 8)})`
                : `Ticket #${item.id}`}
            </Text>
          </View>
          <View style={styles.badgeColumn}>
            {renderStatusBadge(item.status)}
            {renderSyncBadge(item.sync_status)}
          </View>
        </View>

        {/* Vehicle row */}
        <View style={styles.vehicleRow}>
          <Text style={styles.vehicleCode}>
            {item.vehicle?.equipment_code || 'VEHICLE'}
          </Text>
          <Text style={styles.plateNumber}>
            {item.vehicle?.plate_number || ''}
          </Text>
        </View>

        <View style={styles.divider} />

        {/* Origin row */}
        <View style={styles.locationRow}>
          <View style={styles.originIcon}>
            <View style={styles.greenCircle} />
          </View>
          <View style={styles.locationDetails}>
            <Text style={styles.locationTime}>{item.time_in}</Text>
            <Text numberOfLines={1} style={styles.locationAddress}>
              {item.origin?.address || 'Origin Recorded'}
            </Text>
          </View>
        </View>

        {/* Destination row */}
        <View style={styles.locationRow}>
          <View style={styles.destIcon}>
            <View style={styles.redSquare} />
          </View>
          <View style={styles.locationDetails}>
            <Text style={styles.locationTime}>{item.time_out || 'In Progress'}</Text>
            <Text numberOfLines={1} style={styles.locationAddress}>
              {item.status === 'IN_PROGRESS'
                ? 'Traveling...'
                : item.destination?.address || 'Destination Recorded'}
            </Text>
          </View>
        </View>

        {item.remarks ? (
          <View style={styles.remarksBox}>
            <Text style={styles.remarksText}>Note: {item.remarks}</Text>
          </View>
        ) : null}

        {item.sync_error ? (
          <View style={styles.syncErrorBox}>
            <Text style={styles.syncErrorText}>⚠️ Sync Error: {item.sync_error}</Text>
          </View>
        ) : null}
      </Card>
    );
  };

  return (
    <View style={styles.container}>
      <Header
        onBack={onBackToHome}
        subtitle="Completed & In-Progress Trips"
        title="TRIP HISTORY"
      />
      <SyncStatusBar />

      {isLoading ? (
        <View style={styles.loadingContainer}>
          <ActivityIndicator color="#2563eb" size="large" />
          <Text style={styles.loadingText}>Loading trip history...</Text>
        </View>
      ) : (
        <FlatList
          contentContainerStyle={styles.listContent}
          data={trips}
          keyExtractor={(item) => item.client_id || item.id.toString()}
          ListEmptyComponent={
            <View style={styles.emptyContainer}>
              <Text style={styles.emptyTitle}>No Trips Found</Text>
              <Text style={styles.emptySub}>
                You have not started or completed any trips yet today.
              </Text>
            </View>
          }
          refreshControl={
            <RefreshControl onRefresh={handleRefresh} refreshing={isRefreshing} />
          }
          renderItem={renderTripItem}
        />
      )}
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
  },
  loadingText: {
    marginTop: 10,
    color: '#64748b',
    fontSize: 13,
  },
  listContent: {
    padding: 16,
    paddingBottom: 32,
  },
  tripCard: {
    padding: 14,
    marginVertical: 6,
  },
  tripCardHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 8,
  },
  tripDate: {
    fontSize: 14,
    fontWeight: '800',
    color: '#0f172a',
  },
  tripId: {
    fontSize: 11,
    color: '#94a3b8',
    marginTop: 2,
  },
  badgeColumn: {
    alignItems: 'flex-end',
    gap: 4,
  },
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  statusBadgeText: {
    fontSize: 10,
    fontWeight: '800',
    letterSpacing: 0.3,
  },
  syncBadge: {
    paddingHorizontal: 6,
    paddingVertical: 2,
    borderRadius: 4,
  },
  syncBadgeText: {
    fontSize: 9,
    fontWeight: '800',
    letterSpacing: 0.2,
  },
  vehicleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  vehicleCode: {
    fontSize: 15,
    fontWeight: '800',
    color: '#2563eb',
  },
  plateNumber: {
    fontSize: 13,
    color: '#64748b',
    fontWeight: '600',
  },
  divider: {
    height: 1,
    backgroundColor: '#f1f5f9',
    marginVertical: 8,
  },
  locationRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginVertical: 4,
  },
  originIcon: {
    width: 20,
    alignItems: 'center',
    marginRight: 8,
  },
  greenCircle: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#16a34a',
  },
  destIcon: {
    width: 20,
    alignItems: 'center',
    marginRight: 8,
  },
  redSquare: {
    width: 8,
    height: 8,
    borderRadius: 2,
    backgroundColor: '#dc2626',
  },
  locationDetails: {
    flex: 1,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  locationTime: {
    fontSize: 12,
    fontWeight: '700',
    color: '#1e293b',
    width: 75,
  },
  locationAddress: {
    fontSize: 12,
    color: '#64748b',
    flex: 1,
    textAlign: 'right',
  },
  remarksBox: {
    marginTop: 8,
    paddingTop: 6,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
  },
  remarksText: {
    fontSize: 11,
    color: '#64748b',
    fontStyle: 'italic',
  },
  syncErrorBox: {
    marginTop: 6,
    padding: 6,
    backgroundColor: '#fef2f2',
    borderRadius: 4,
    borderLeftWidth: 3,
    borderLeftColor: '#ef4444',
  },
  syncErrorText: {
    fontSize: 10,
    color: '#b91c1c',
    fontWeight: '600',
  },
  emptyContainer: {
    padding: 32,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emptyTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: '#64748b',
    marginBottom: 6,
  },
  emptySub: {
    fontSize: 13,
    color: '#94a3b8',
    textAlign: 'center',
  },
});
