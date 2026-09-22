import React, { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { syncService, SyncState } from '../services/syncService';

export const SyncStatusBar: React.FC = () => {
  const [syncState, setSyncState] = useState<SyncState>(syncService.getState());

  useEffect(() => {
    const unsubscribe = syncService.subscribe((state) => {
      setSyncState(state);
    });
    return unsubscribe;
  }, []);

  const handleManualSync = () => {
    syncService.syncNow().catch(() => {});
  };

  const { isSyncing, pendingCount, syncError } = syncState;

  if (isSyncing) {
    return (
      <View style={[styles.container, styles.syncingContainer]}>
        <ActivityIndicator size="small" color="#1d4ed8" style={styles.indicator} />
        <Text style={[styles.text, styles.syncingText]}>
          Syncing trips with fleet server...
        </Text>
      </View>
    );
  }

  if (syncError && pendingCount > 0) {
    return (
      <View style={[styles.container, styles.errorContainer]}>
        <View style={styles.textColumn}>
          <Text style={[styles.title, styles.errorText]}>SYNC PAUSED (OFFLINE)</Text>
          <Text style={styles.subtext} numberOfLines={1}>
            {pendingCount} trip{pendingCount > 1 ? 's' : ''} saved locally. Waiting for connection.
          </Text>
        </View>
        <TouchableOpacity style={styles.retryButton} onPress={handleManualSync}>
          <Text style={styles.retryButtonText}>Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  if (pendingCount > 0) {
    return (
      <View style={[styles.container, styles.pendingContainer]}>
        <View style={styles.textColumn}>
          <Text style={[styles.title, styles.pendingText]}>OFFLINE MODE • SAVED LOCALLY</Text>
          <Text style={styles.subtext}>
            {pendingCount} trip{pendingCount > 1 ? 's' : ''} waiting for synchronization.
          </Text>
        </View>
        <TouchableOpacity style={styles.syncButton} onPress={handleManualSync}>
          <Text style={styles.syncButtonText}>Sync Now</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={[styles.container, styles.syncedContainer]}>
      <View style={styles.syncedDot} />
      <Text style={styles.syncedText}>All trips backed up to fleet cloud</Text>
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: 16,
    paddingVertical: 10,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
  },
  textColumn: {
    flex: 1,
    marginRight: 12,
  },
  text: {
    fontSize: 13,
    fontWeight: '500',
  },
  title: {
    fontSize: 11,
    fontWeight: '800',
    letterSpacing: 0.5,
  },
  subtext: {
    fontSize: 12,
    color: '#475569',
    marginTop: 2,
  },
  indicator: {
    marginRight: 8,
  },
  syncingContainer: {
    backgroundColor: '#eff6ff',
    borderBottomColor: '#bfdbfe',
    justifyContent: 'flex-start',
  },
  syncingText: {
    color: '#1d4ed8',
  },
  pendingContainer: {
    backgroundColor: '#fffbeb',
    borderBottomColor: '#fde68a',
  },
  pendingText: {
    color: '#b45309',
  },
  syncButton: {
    backgroundColor: '#d97706',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
  },
  syncButtonText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '700',
  },
  errorContainer: {
    backgroundColor: '#fff1f2',
    borderBottomColor: '#fecdd3',
  },
  errorText: {
    color: '#be123c',
  },
  retryButton: {
    backgroundColor: '#e11d48',
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 6,
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 12,
    fontWeight: '700',
  },
  syncedContainer: {
    backgroundColor: '#f8fafc',
    borderBottomColor: '#e2e8f0',
    justifyContent: 'center',
    paddingVertical: 6,
  },
  syncedDot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: '#16a34a',
    marginRight: 8,
  },
  syncedText: {
    fontSize: 11,
    color: '#64748b',
    fontWeight: '600',
  },
});
