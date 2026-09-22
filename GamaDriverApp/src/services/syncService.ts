import { AppState, AppStateStatus } from 'react-native';
import { sqliteService } from './sqliteService';
import { apiClient } from '../api/client';
import { ApiResponse, DriverTrip, EndTripPayload, StartTripPayload, SyncQueueItem } from '../api/types';

export interface SyncState {
  isSyncing: boolean;
  pendingCount: number;
  lastSyncTime: Date | null;
  syncError: string | null;
}

type SyncStateListener = (state: SyncState) => void;

class SyncService {
  private isSyncing = false;
  private lastSyncTime: Date | null = null;
  private syncError: string | null = null;
  private listeners: Set<SyncStateListener> = new Set();
  private pollingTimer: ReturnType<typeof setInterval> | null = null;
  private isInitialized = false;

  /**
   * Initialize sync engine, recover stale items, and bind lifecycle triggers
   */
  public async init(): Promise<void> {
    if (this.isInitialized) return;

    // 1. Recover any items left in SYNCING state from previous app crash/kill
    await sqliteService.recoverStaleSyncingQueue();

    // 2. Initial state broadcast
    await this.refreshState();

    // 3. Listen to app foreground events
    AppState.addEventListener('change', this.handleAppStateChange);

    // 4. Periodic sync timer (checks every 30 seconds if items are pending)
    this.startPeriodicSync();

    this.isInitialized = true;
    console.log('[SyncService] Initialized.');

    // 5. Trigger initial sync on startup
    this.syncNow().catch((err) => {
      console.warn('[SyncService] Initial sync attempt deferred:', err?.message);
    });
  }

  /**
   * Subscribe to reactive sync state changes
   */
  public subscribe(listener: SyncStateListener): () => void {
    this.listeners.add(listener);
    listener(this.getState());
    return () => this.listeners.delete(listener);
  }

  public getState(): SyncState {
    return {
      isSyncing: this.isSyncing,
      pendingCount: 0, // Updated dynamically via refreshState
      lastSyncTime: this.lastSyncTime,
      syncError: this.syncError,
    };
  }

  private notifyListeners(state: SyncState) {
    this.listeners.forEach((l) => {
      try {
        l(state);
      } catch (err) {
        console.warn('[SyncService] Error in listener callback:', err);
      }
    });
  }

  private async refreshState(): Promise<SyncState> {
    const pendingCount = await sqliteService.getPendingCount();
    const state: SyncState = {
      isSyncing: this.isSyncing,
      pendingCount,
      lastSyncTime: this.lastSyncTime,
      syncError: this.syncError,
    };
    this.notifyListeners(state);
    return state;
  }

  private handleAppStateChange = (nextAppState: AppStateStatus) => {
    if (nextAppState === 'active') {
      console.log('[SyncService] App entered active state; running sync.');
      this.syncNow().catch(() => {});
    }
  };

  private startPeriodicSync() {
    if (this.pollingTimer) clearInterval(this.pollingTimer);
    this.pollingTimer = setInterval(async () => {
      const count = await sqliteService.getPendingCount();
      if (count > 0 && !this.isSyncing) {
        console.log(`[SyncService] Periodic sync triggered: ${count} items pending.`);
        this.syncNow().catch(() => {});
      }
    }, 30000);
  }

  /**
   * Main synchronization worker: processes the sync queue in FIFO order
   */
  public async syncNow(): Promise<{ syncedCount: number; errors: string[] }> {
    if (this.isSyncing) {
      console.log('[SyncService] Sync already in progress, skipping duplicate call.');
      return { syncedCount: 0, errors: [] };
    }

    this.isSyncing = true;
    this.syncError = null;
    await this.refreshState();

    let syncedCount = 0;
    const errors: string[] = [];

    try {
      const queue: SyncQueueItem[] = await sqliteService.getPendingQueue();

      if (queue.length === 0) {
        this.lastSyncTime = new Date();
        return { syncedCount: 0, errors: [] };
      }

      console.log(`[SyncService] Processing queue of ${queue.length} items in FIFO order...`);

      for (const item of queue) {
        try {
          await sqliteService.markQueueItemSyncing(item.id, item.client_id);
          await this.processQueueItem(item);
          syncedCount++;
        } catch (itemError: any) {
          const errMsg = itemError?.message || 'Network error during sync';
          console.warn(`[SyncService] Failed item ${item.id} (${item.action}): ${errMsg}`);
          await sqliteService.failQueueItem(item.id, item.client_id, errMsg);
          errors.push(errMsg);
          this.syncError = errMsg;

          // If network failure / server unreachable, stop processing subsequent items to preserve order
          if (this.isNetworkOrServerUnreachable(itemError)) {
            console.warn('[SyncService] Network unreachable. Pausing remaining queue processing.');
            break;
          }
        }
      }

      this.lastSyncTime = new Date();
    } catch (globalError: any) {
      const msg = globalError?.message || 'Unexpected sync error';
      this.syncError = msg;
      errors.push(msg);
      console.error('[SyncService] Global sync loop error:', globalError);
    } finally {
      this.isSyncing = false;
      await this.refreshState();
    }

    return { syncedCount, errors };
  }

  /**
   * Process an individual sync queue item according to action and dependency rules
   */
  private async processQueueItem(item: SyncQueueItem): Promise<void> {
    const localTrip = await sqliteService.getTripByClientId(item.client_id);
    if (!localTrip) {
      throw new Error(`Orphan queue item: local_trip with client_id ${item.client_id} not found.`);
    }

    switch (item.action) {
      case 'START_TRIP': {
        const payload: StartTripPayload = JSON.parse(item.payload_json);
        // Guarantee the client_id is the persistent idempotency key
        payload.client_id = item.client_id;

        const response = await apiClient.post<ApiResponse<DriverTrip>>('/trips', payload);
        const serverTrip = response.data;
        const serverId = serverTrip.id;

        if (!serverId) {
          throw new Error('API response missing server trip id.');
        }

        // Atomically complete queue item and update local_trips with server_id
        await sqliteService.completeQueueItem(item.id, item.client_id, serverId);
        console.log(`[SyncService] START_TRIP synced successfully. Server ID: ${serverId}`);
        break;
      }

      case 'END_TRIP': {
        // Strict dependency: END_TRIP requires a valid server_id from START_TRIP
        let serverId = localTrip.server_id;

        if (!serverId) {
          // If server_id is missing, look for a pending START_TRIP for this client_id
          console.warn(
            `[SyncService] END_TRIP for ${item.client_id} has no server_id. Checking if START_TRIP needs to sync first.`
          );
          throw new Error(
            `Cannot sync END_TRIP: START_TRIP for ${item.client_id} has not yet received a server_id.`
          );
        }

        const payload: EndTripPayload = JSON.parse(item.payload_json);
        try {
          await apiClient.patch<ApiResponse<DriverTrip>>(`/trips/${serverId}/end`, payload);
        } catch (patchErr: any) {
          const msg = (patchErr?.message || '').toLowerCase();
          if (msg.includes('cannot end trip with status') || msg.includes('completed')) {
            console.log(`[SyncService] Trip ${serverId} is already completed on server. Idempotent success.`);
          } else {
            throw patchErr;
          }
        }

        await sqliteService.completeQueueItem(item.id, item.client_id);
        console.log(`[SyncService] END_TRIP synced successfully for server trip ${serverId}`);
        break;
      }

      case 'CANCEL_TRIP': {
        const serverId = localTrip.server_id;
        if (!serverId) {
          throw new Error(
            `Cannot sync CANCEL_TRIP: START_TRIP for ${item.client_id} has not yet received a server_id.`
          );
        }

        const payload = JSON.parse(item.payload_json);
        try {
          await apiClient.post<ApiResponse<DriverTrip>>(`/trips/${serverId}/cancel`, payload);
        } catch (cancelErr: any) {
          const msg = (cancelErr?.message || '').toLowerCase();
          if (msg.includes('cannot end trip') || msg.includes('cannot cancel') || msg.includes('cancelled') || msg.includes('completed')) {
            console.log(`[SyncService] Trip ${serverId} is already cancelled or completed on server. Idempotent success.`);
          } else {
            throw cancelErr;
          }
        }

        await sqliteService.completeQueueItem(item.id, item.client_id);
        console.log(`[SyncService] CANCEL_TRIP synced successfully for server trip ${serverId}`);
        break;
      }

      default:
        throw new Error(`Unknown queue action: ${(item as any).action}`);
    }
  }

  private isNetworkOrServerUnreachable(error: any): boolean {
    if (!error) return false;
    const msg = (error?.message || '').toLowerCase();
    return (
      msg.includes('network request failed') ||
      msg.includes('connection refused') ||
      msg.includes('timeout') ||
      msg.includes('aborted') ||
      msg.includes('unable to connect') ||
      error?.status === 0 ||
      error?.status === 502 ||
      error?.status === 503 ||
      error?.status === 504
    );
  }
}

export const syncService = new SyncService();
