import { NativeModules, Platform } from 'react-native';
import { LocalTrip, SyncQueueAction, SyncQueueItem, StartTripPayload, EndTripPayload } from '../api/types';

interface NativeSQLiteModuleInterface {
  execute(sql: string, params?: any[]): Promise<{ insertId?: number; rowsAffected: number }>;
  query(sql: string, params?: any[]): Promise<any[]>;
  transaction(statements: Array<{ sql: string; params?: any[] }>): Promise<boolean>;
  resetDatabase(): Promise<boolean>;
}

const NativeSQLite: NativeSQLiteModuleInterface = NativeModules.SQLiteModule;

const executeSql = (sql: string, params: any[] = []): Promise<{ insertId?: number; rowsAffected: number }> => {
  if (!NativeSQLite) return Promise.reject(new Error('Native SQLite module unavailable'));
  return NativeSQLite.execute(sql, params || []);
};

const querySql = (sql: string, params: any[] = []): Promise<any[]> => {
  if (!NativeSQLite) return Promise.reject(new Error('Native SQLite module unavailable'));
  return NativeSQLite.query(sql, params || []);
};

/**
 * RFC4122 v4 compliant UUID generator
 */
export function generateUuidV4(): string {
  const gCrypto = (globalThis as any)?.crypto;
  if (gCrypto && typeof gCrypto.randomUUID === 'function') {
    try {
      return gCrypto.randomUUID();
    } catch {
      // Fall through to standard generator
    }
  }

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

class SQLiteService {
  private isInitialized = false;

  /**
   * Initialize local database and guarantee tables are ready
   */
  public async initDb(): Promise<void> {
    if (this.isInitialized) return;
    if (Platform.OS !== 'android' || !NativeSQLite) {
      console.warn('[SQLiteService] Native SQLite module is not available on this platform.');
      return;
    }

    try {
      await executeSql(`
        CREATE TABLE IF NOT EXISTS local_trips (
          client_id TEXT PRIMARY KEY,
          server_id INTEGER,
          driver_id INTEGER NOT NULL,
          vehicle_id INTEGER NOT NULL,
          driver_vehicle_assignment_id INTEGER,
          trip_date TEXT NOT NULL,
          time_in TEXT NOT NULL,
          time_out TEXT,
          origin_latitude REAL NOT NULL,
          origin_longitude REAL NOT NULL,
          origin_accuracy REAL,
          origin_address TEXT,
          destination_latitude REAL,
          destination_longitude REAL,
          destination_accuracy REAL,
          destination_address TEXT,
          status TEXT NOT NULL,
          sync_status TEXT NOT NULL,
          sync_error TEXT,
          remarks TEXT,
          created_at TEXT NOT NULL,
          updated_at TEXT NOT NULL
        );
      `);

      await executeSql(`
        CREATE TABLE IF NOT EXISTS sync_queue (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          client_id TEXT NOT NULL,
          action TEXT NOT NULL,
          payload_json TEXT NOT NULL,
          status TEXT NOT NULL,
          retry_count INTEGER DEFAULT 0,
          last_attempt_at TEXT,
          error_message TEXT,
          created_at TEXT NOT NULL,
          FOREIGN KEY (client_id) REFERENCES local_trips(client_id) ON DELETE CASCADE
        );
      `);

      this.isInitialized = true;
      console.log('[SQLiteService] Local database initialized.');
    } catch (error) {
      console.error('[SQLiteService] Failed to initialize SQLite tables:', error);
      throw error;
    }
  }

  /**
   * Reset stale SYNCING items to PENDING on application startup
   */
  public async recoverStaleSyncingQueue(): Promise<number> {
    await this.initDb();
    try {
      const res = await executeSql(
        "UPDATE sync_queue SET status = 'PENDING' WHERE status = 'SYNCING';"
      );
      await executeSql(
        "UPDATE local_trips SET sync_status = 'PENDING' WHERE sync_status = 'SYNCING';"
      );
      if (res.rowsAffected > 0) {
        console.log(`[SQLiteService] Recovered ${res.rowsAffected} stale SYNCING queue items.`);
      }
      return res.rowsAffected;
    } catch (error) {
      console.warn('[SQLiteService] Error recovering stale queue items:', error);
      return 0;
  }
  }

  /**
   * ATOMIC START TRIP:
   * Atomically inserts the trip into local_trips and enqueues START_TRIP into sync_queue
   */
  public async atomicStartTrip(trip: LocalTrip, payload: StartTripPayload): Promise<void> {
    await this.initDb();

    const insertTripSql = `
      INSERT INTO local_trips (
        client_id, server_id, driver_id, vehicle_id, driver_vehicle_assignment_id,
        trip_date, time_in, time_out,
        origin_latitude, origin_longitude, origin_accuracy, origin_address,
        destination_latitude, destination_longitude, destination_accuracy, destination_address,
        status, sync_status, sync_error, remarks, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
    `;

    const tripParams = [
      trip.client_id,
      trip.server_id,
      trip.driver_id,
      trip.vehicle_id,
      trip.driver_vehicle_assignment_id,
      trip.trip_date,
      trip.time_in,
      trip.time_out,
      trip.origin_latitude,
      trip.origin_longitude,
      trip.origin_accuracy,
      trip.origin_address,
      trip.destination_latitude,
      trip.destination_longitude,
      trip.destination_accuracy,
      trip.destination_address,
      trip.status,
      trip.sync_status,
      trip.sync_error,
      trip.remarks,
      trip.created_at,
      trip.updated_at,
    ];

    const insertQueueSql = `
      INSERT INTO sync_queue (
        client_id, action, payload_json, status, retry_count, created_at
      ) VALUES (?, 'START_TRIP', ?, 'PENDING', 0, ?);
    `;

    const queueParams = [
      trip.client_id,
      JSON.stringify(payload),
      trip.created_at,
    ];

    await NativeSQLite.transaction([
      { sql: insertTripSql, params: tripParams },
      { sql: insertQueueSql, params: queueParams },
    ]);

    console.log(`[SQLiteService] Atomically saved START TRIP for ${trip.client_id}`);
  }

  /**
   * ATOMIC END TRIP:
   * Atomically updates local_trips with destination info and enqueues END_TRIP into sync_queue
   */
  public async atomicEndTrip(
    clientId: string,
    destinationData: {
      time_out: string;
      destination_latitude: number;
      destination_longitude: number;
      destination_accuracy: number | null;
      destination_address: string | null;
      remarks: string | null;
      updated_at: string;
    },
    payload: EndTripPayload
  ): Promise<void> {
    await this.initDb();

    const updateTripSql = `
      UPDATE local_trips SET
        destination_latitude = ?,
        destination_longitude = ?,
        destination_accuracy = ?,
        destination_address = ?,
        time_out = ?,
        status = 'COMPLETED',
        sync_status = 'PENDING',
        remarks = ?,
        updated_at = ?
      WHERE client_id = ?;
    `;

    const tripParams = [
      destinationData.destination_latitude,
      destinationData.destination_longitude,
      destinationData.destination_accuracy,
      destinationData.destination_address,
      destinationData.time_out,
      destinationData.remarks,
      destinationData.updated_at,
      clientId,
    ];

    const insertQueueSql = `
      INSERT INTO sync_queue (
        client_id, action, payload_json, status, retry_count, created_at
      ) VALUES (?, 'END_TRIP', ?, 'PENDING', 0, ?);
    `;

    const queueParams = [
      clientId,
      JSON.stringify(payload),
      destinationData.updated_at,
    ];

    await NativeSQLite.transaction([
      { sql: updateTripSql, params: tripParams },
      { sql: insertQueueSql, params: queueParams },
    ]);

    console.log(`[SQLiteService] Atomically saved END TRIP for ${clientId}`);
  }

  /**
   * ATOMIC CANCEL TRIP:
   * Atomically updates local_trips to CANCELLED and enqueues CANCEL_TRIP into sync_queue
   */
  public async atomicCancelTrip(
    clientId: string,
    remarks: string | null,
    updatedAt: string
  ): Promise<void> {
    await this.initDb();

    const updateTripSql = `
      UPDATE local_trips SET
        status = 'CANCELLED',
        sync_status = 'PENDING',
        remarks = ?,
        updated_at = ?
      WHERE client_id = ?;
    `;

    const tripParams = [remarks, updatedAt, clientId];

    const insertQueueSql = `
      INSERT INTO sync_queue (
        client_id, action, payload_json, status, retry_count, created_at
      ) VALUES (?, 'CANCEL_TRIP', ?, 'PENDING', 0, ?);
    `;

    const queueParams = [
      clientId,
      JSON.stringify({ remarks }),
      updatedAt,
    ];

    await NativeSQLite.transaction([
      { sql: updateTripSql, params: tripParams },
      { sql: insertQueueSql, params: queueParams },
    ]);

    console.log(`[SQLiteService] Atomically saved CANCEL TRIP for ${clientId}`);
  }

  /**
   * Get active trip for the given driver, if any
   */
  public async getActiveTrip(driverId: number): Promise<LocalTrip | null> {
    await this.initDb();
    const rows = await querySql(
      "SELECT * FROM local_trips WHERE driver_id = ? AND status = 'IN_PROGRESS' ORDER BY created_at DESC LIMIT 1;",
      [driverId]
    );

    if (!rows || rows.length === 0) {
      return null;
    }

    return this.mapRowToTrip(rows[0]);
  }

  /**
   * Get single trip by client_id
   */
  public async getTripByClientId(clientId: string): Promise<LocalTrip | null> {
    await this.initDb();
    const rows = await querySql(
      'SELECT * FROM local_trips WHERE client_id = ? LIMIT 1;',
      [clientId]
    );

    if (!rows || rows.length === 0) {
      return null;
    }

    return this.mapRowToTrip(rows[0]);
  }

  /**
   * Get all local trips for a driver, ordered chronologically descending
   */
  public async getAllTrips(driverId?: number): Promise<LocalTrip[]> {
    await this.initDb();
    let sql = 'SELECT * FROM local_trips';
    const params: any[] = [];

    if (driverId !== undefined) {
      sql += ' WHERE driver_id = ?';
      params.push(driverId);
    }

    sql += ' ORDER BY created_at DESC;';

    const rows = await querySql(sql, params);
    return (rows || []).map((r) => this.mapRowToTrip(r));
  }

  /**
   * Get pending queue items ordered monotonically by ID (FIFO)
   */
  public async getPendingQueue(): Promise<SyncQueueItem[]> {
    await this.initDb();
    const rows = await querySql(
      "SELECT * FROM sync_queue WHERE status IN ('PENDING', 'FAILED') ORDER BY id ASC;"
    );

    return (rows || []).map((r) => ({
      id: Number(r.id),
      client_id: String(r.client_id),
      action: r.action as SyncQueueAction,
      payload_json: String(r.payload_json),
      status: r.status as 'PENDING' | 'SYNCING' | 'FAILED',
      retry_count: Number(r.retry_count || 0),
      last_attempt_at: r.last_attempt_at ? String(r.last_attempt_at) : null,
      error_message: r.error_message ? String(r.error_message) : null,
      created_at: String(r.created_at),
    }));
  }

  /**
   * Count total pending sync queue items
   */
  public async getPendingCount(): Promise<number> {
    await this.initDb();
    const rows = await querySql(
      "SELECT COUNT(*) as cnt FROM sync_queue WHERE status IN ('PENDING', 'FAILED', 'SYNCING');"
    );
    if (!rows || rows.length === 0) return 0;
    return Number(rows[0].cnt || 0);
  }

  /**
   * Mark a queue item as currently SYNCING
   */
  public async markQueueItemSyncing(queueId: number, clientId: string): Promise<void> {
    await this.initDb();
    const now = new Date().toISOString();
    await NativeSQLite.transaction([
      {
        sql: "UPDATE sync_queue SET status = 'SYNCING', last_attempt_at = ? WHERE id = ?;",
        params: [now, queueId],
      },
      {
        sql: "UPDATE local_trips SET sync_status = 'SYNCING' WHERE client_id = ?;",
        params: [clientId],
      },
    ]);
  }

  /**
   * Successfully acknowledge and remove a queue item.
   * If serverId is returned, update local_trips with that ID.
   */
  public async completeQueueItem(
    queueId: number,
    clientId: string,
    serverId?: number
  ): Promise<void> {
    await this.initDb();
    const ops: Array<{ sql: string; params?: any[] }> = [
      {
        sql: 'DELETE FROM sync_queue WHERE id = ?;',
        params: [queueId],
      },
    ];

    if (serverId !== undefined && serverId !== null) {
      ops.push({
        sql: "UPDATE local_trips SET server_id = ?, sync_status = 'SYNCED', sync_error = NULL WHERE client_id = ?;",
        params: [serverId, clientId],
      });
    } else {
      ops.push({
        sql: "UPDATE local_trips SET sync_status = 'SYNCED', sync_error = NULL WHERE client_id = ?;",
        params: [clientId],
      });
    }

    await NativeSQLite.transaction(ops);
    console.log(`[SQLiteService] Queue item ${queueId} for ${clientId} COMPLETED.`);
  }

  /**
   * Mark a queue item as FAILED with error message, incrementing retry count
   */
  public async failQueueItem(
    queueId: number,
    clientId: string,
    errorMessage: string
  ): Promise<void> {
    await this.initDb();
    const now = new Date().toISOString();

    await NativeSQLite.transaction([
      {
        sql: `UPDATE sync_queue SET
                status = 'FAILED',
                retry_count = retry_count + 1,
                last_attempt_at = ?,
                error_message = ?
              WHERE id = ?;`,
        params: [now, errorMessage, queueId],
      },
      {
        sql: "UPDATE local_trips SET sync_status = 'FAILED', sync_error = ? WHERE client_id = ?;",
        params: [errorMessage, clientId],
      },
    ]);

    console.warn(`[SQLiteService] Queue item ${queueId} for ${clientId} FAILED: ${errorMessage}`);
  }

  /**
   * Cache a server trip locally in SQLite
   */
  public async saveServerTrip(st: any): Promise<void> {
    await this.initDb();
    if (!st || !st.client_id) return;
    const existing = await this.getTripByClientId(st.client_id);
    if (existing) return;

    const insertTripSql = `
      INSERT OR REPLACE INTO local_trips (
        client_id, server_id, driver_id, vehicle_id, driver_vehicle_assignment_id,
        trip_date, time_in, time_out,
        origin_latitude, origin_longitude, origin_accuracy, origin_address,
        destination_latitude, destination_longitude, destination_accuracy, destination_address,
        status, sync_status, sync_error, remarks, created_at, updated_at
      ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SYNCED', NULL, ?, ?, ?);
    `;

    const params = [
      st.client_id,
      st.id || null,
      st.driver?.id || 2,
      st.vehicle?.id || 1,
      st.driver_vehicle_assignment_id || null,
      st.trip_date || '2026-09-16',
      st.time_in || '08:00:00',
      st.time_out || null,
      st.origin?.latitude || 0,
      st.origin?.longitude || 0,
      st.origin?.accuracy ?? null,
      st.origin?.address ?? null,
      st.destination?.latitude ?? null,
      st.destination?.longitude ?? null,
      st.destination?.accuracy ?? null,
      st.destination?.address ?? null,
      st.status || 'IN_PROGRESS',
      st.remarks || null,
      st.created_at || new Date().toISOString(),
      st.updated_at || new Date().toISOString(),
    ];

    await executeSql(insertTripSql, params);
  }

  /**
   * Update server_id in local_trips (e.g. after START_TRIP finishes)
   */
  public async updateServerId(clientId: string, serverId: number): Promise<void> {
    await this.initDb();
    await executeSql(
      'UPDATE local_trips SET server_id = ? WHERE client_id = ?;',
      [serverId, clientId]
    );
  }

  /**
   * Reset local database (for testing or debugging)
   */
  public async resetAll(): Promise<void> {
    await this.initDb();
    await NativeSQLite.resetDatabase();
  }

  private mapRowToTrip(row: any): LocalTrip {
    return {
      client_id: String(row.client_id),
      server_id: row.server_id ? Number(row.server_id) : null,
      driver_id: Number(row.driver_id),
      vehicle_id: Number(row.vehicle_id),
      driver_vehicle_assignment_id: row.driver_vehicle_assignment_id
        ? Number(row.driver_vehicle_assignment_id)
        : null,
      trip_date: String(row.trip_date),
      time_in: String(row.time_in),
      time_out: row.time_out ? String(row.time_out) : null,
      origin_latitude: Number(row.origin_latitude),
      origin_longitude: Number(row.origin_longitude),
      origin_accuracy: row.origin_accuracy !== null ? Number(row.origin_accuracy) : null,
      origin_address: row.origin_address ? String(row.origin_address) : null,
      destination_latitude:
        row.destination_latitude !== null ? Number(row.destination_latitude) : null,
      destination_longitude:
        row.destination_longitude !== null ? Number(row.destination_longitude) : null,
      destination_accuracy:
        row.destination_accuracy !== null ? Number(row.destination_accuracy) : null,
      destination_address: row.destination_address ? String(row.destination_address) : null,
      status: row.status,
      sync_status: row.sync_status,
      sync_error: row.sync_error ? String(row.sync_error) : null,
      remarks: row.remarks ? String(row.remarks) : null,
      created_at: String(row.created_at),
      updated_at: String(row.updated_at),
    };
  }
}

export const sqliteService = new SQLiteService();
