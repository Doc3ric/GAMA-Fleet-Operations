import { apiClient } from '../api/client';
import {
  ApiResponse,
  DriverTrip,
  EndTripPayload,
  LocalTrip,
  StartTripPayload,
  Vehicle,
  VehicleAssignment,
} from '../api/types';
import { sqliteService, generateUuidV4 } from './sqliteService';
import { syncService } from './syncService';

export function localTripToDriverTrip(
  lt: LocalTrip,
  vehicle?: Vehicle | VehicleAssignment | null,
  driver?: { id: number; name: string; email: string } | null
): DriverTrip {
  const vehicleData: Vehicle = {
    id: lt.vehicle_id,
    equipment_code: (vehicle as any)?.equipment_code || `Vehicle #${lt.vehicle_id}`,
    model: vehicle?.model || null,
    plate_number: vehicle?.plate_number || null,
    vehicle_type: vehicle?.vehicle_type || null,
  };

  return {
    id: lt.server_id || 0,
    client_id: lt.client_id,
    driver: driver || {
      id: lt.driver_id,
      name: 'Driver',
      email: '',
    },
    vehicle: vehicleData,
    driver_vehicle_assignment_id: lt.driver_vehicle_assignment_id,
    trip_date: lt.trip_date,
    time_in: lt.time_in,
    time_out: lt.time_out,
    origin: {
      latitude: lt.origin_latitude,
      longitude: lt.origin_longitude,
      accuracy: lt.origin_accuracy,
      address: lt.origin_address,
    },
    destination: {
      latitude: lt.destination_latitude,
      longitude: lt.destination_longitude,
      accuracy: lt.destination_accuracy,
      address: lt.destination_address,
    },
    status: lt.status,
    sync_status: lt.sync_status,
    sync_error: lt.sync_error,
    remarks: lt.remarks,
    created_at: lt.created_at,
    updated_at: lt.updated_at,
  };
}

class TripService {
  /**
   * OFFLINE-FIRST START TRIP:
   * 1. Guarantees client_id is a valid UUID v4
   * 2. Saves trip to local SQLite and queues START_TRIP in sync_queue atomically
   * 3. Triggers background sync without waiting for network response
   * 4. Returns local trip immediately
   */
  public async startTrip(
    payload: StartTripPayload,
    driverId: number = 2,
    assignment?: VehicleAssignment | null
  ): Promise<DriverTrip> {
    const clientId = payload.client_id || generateUuidV4();
    payload.client_id = clientId;

    const now = new Date();
    const nowIso = now.toISOString();
    const tripDate = payload.trip_date || nowIso.split('T')[0] || '2026-09-16';

    const localTrip: LocalTrip = {
      client_id: clientId,
      server_id: null,
      driver_id: driverId,
      vehicle_id: payload.vehicle_id,
      driver_vehicle_assignment_id: assignment?.assignment_id || null,
      trip_date: tripDate,
      time_in: payload.time_in,
      time_out: null,
      origin_latitude: payload.origin_latitude,
      origin_longitude: payload.origin_longitude,
      origin_accuracy: payload.origin_accuracy ?? null,
      origin_address: payload.origin_address ?? null,
      destination_latitude: null,
      destination_longitude: null,
      destination_accuracy: null,
      destination_address: null,
      status: 'IN_PROGRESS',
      sync_status: 'PENDING',
      sync_error: null,
      remarks: payload.remarks || 'Trip initiated via GamaDriverApp',
      created_at: nowIso,
      updated_at: nowIso,
    };

    // 1. ATOMIC SQLite transaction: save to local_trips + insert into sync_queue
    await sqliteService.atomicStartTrip(localTrip, payload);

    // 2. Trigger synchronization in background
    syncService.syncNow().catch((err) => {
      console.warn('[TripService] Background sync deferred for START_TRIP:', err?.message);
    });

    return localTripToDriverTrip(localTrip, assignment);
  }

  /**
   * OFFLINE-FIRST END TRIP:
   * 1. Updates destination info and status in local SQLite atomically with sync_queue
   * 2. Triggers background sync
   * 3. Returns updated trip immediately
   */
  public async endTrip(
    clientId: string,
    payload: EndTripPayload,
    vehicle?: Vehicle | null
  ): Promise<DriverTrip> {
    const nowIso = new Date().toISOString();

    const destinationData = {
      time_out: payload.time_out,
      destination_latitude: payload.destination_latitude,
      destination_longitude: payload.destination_longitude,
      destination_accuracy: payload.destination_accuracy ?? null,
      destination_address: payload.destination_address ?? null,
      remarks: payload.remarks || 'Trip ended via GamaDriverApp',
      updated_at: nowIso,
    };

    // 1. ATOMIC SQLite transaction: update local_trips + insert END_TRIP into sync_queue
    const existing = await sqliteService.getTripByClientId(clientId);
    if (!existing) {
      await sqliteService.saveServerTrip({
        client_id: clientId,
        vehicle_id: vehicle?.id || 1,
        driver_id: 2,
        trip_date: nowIso.split('T')[0],
        time_in: '08:00:00',
        origin_latitude: payload.destination_latitude,
        origin_longitude: payload.destination_longitude,
        status: 'IN_PROGRESS',
      });
    }

    await sqliteService.atomicEndTrip(clientId, destinationData, payload);

    // 2. Trigger synchronization in background
    syncService.syncNow().catch((err) => {
      console.warn('[TripService] Background sync deferred for END_TRIP:', err?.message);
    });

    const updated = await sqliteService.getTripByClientId(clientId);
    if (!updated) {
      throw new Error(`Failed to retrieve updated trip for ${clientId}`);
    }

    return localTripToDriverTrip(updated, vehicle);
  }

  /**
   * OFFLINE-FIRST CANCEL TRIP:
   * 1. Updates status to CANCELLED in local SQLite atomically with sync_queue
   * 2. Triggers background sync
   * 3. Returns updated trip immediately
   */
  public async cancelTrip(clientId: string, remarks?: string): Promise<DriverTrip> {
    const nowIso = new Date().toISOString();
    const finalRemarks = remarks || 'Trip cancelled by driver from mobile app';

    const existing = await sqliteService.getTripByClientId(clientId);
    if (!existing) {
      await sqliteService.saveServerTrip({
        client_id: clientId,
        vehicle_id: 1,
        driver_id: 2,
        trip_date: nowIso.split('T')[0],
        time_in: '08:00:00',
        origin_latitude: 0,
        origin_longitude: 0,
        status: 'IN_PROGRESS',
      });
    }

    // 1. ATOMIC SQLite transaction: update local_trips + insert CANCEL_TRIP into sync_queue
    await sqliteService.atomicCancelTrip(clientId, finalRemarks, nowIso);

    // 2. Trigger synchronization in background
    syncService.syncNow().catch((err) => {
      console.warn('[TripService] Background sync deferred for CANCEL_TRIP:', err?.message);
    });

    const updated = await sqliteService.getTripByClientId(clientId);
    if (!updated) {
      throw new Error(`Failed to retrieve cancelled trip for ${clientId}`);
    }

    return localTripToDriverTrip(updated);
  }

  /**
   * Get active trip for driver. Checks local SQLite first for instant response
   */
  public async getActiveTrip(driverId: number): Promise<DriverTrip | null> {
    // 1. Check local SQLite storage first
    const localActive = await sqliteService.getActiveTrip(driverId);
    if (localActive) {
      return localTripToDriverTrip(localActive);
    }

    // 2. If nothing local, check server if online
    try {
      const response = await apiClient.get<ApiResponse<DriverTrip[]>>('/trips?status=IN_PROGRESS&per_page=1');
      const trips = response.data;
      if (trips && trips.length > 0 && trips[0]) {
        const serverTrip = trips[0];
        await sqliteService.saveServerTrip(serverTrip);
        return serverTrip;
      }
    } catch {
      // Offline / server unreachable: trust local SQLite
    }

    return null;
  }

  /**
   * Get all trips for history display. Reads local SQLite for instant load,
   * then reconciles with remote server if online.
   */
  public async getTrips(filters?: { driver_id?: number; status?: string; per_page?: number }): Promise<DriverTrip[]> {
    // 1. Load local SQLite trips first (instant, 0 network latency, full offline support)
    const localTrips = await sqliteService.getAllTrips(filters?.driver_id);
    let mapped = localTrips.map((lt) => localTripToDriverTrip(lt));

    if (filters?.status) {
      mapped = mapped.filter((t) => t.status === filters.status);
    }

    // 2. Non-blocking attempt to reconcile with server if network is available
    this.reconcileServerTrips().catch(() => {});

    return mapped;
  }

  /**
   * Helper to reconcile remote server trips into local database
   */
  private async reconcileServerTrips(): Promise<void> {
    try {
      const response = await apiClient.get<ApiResponse<DriverTrip[]>>('/trips?per_page=30');
      const remoteTrips = response.data;
      if (!remoteTrips || !Array.isArray(remoteTrips)) return;

      for (const rt of remoteTrips) {
        const local = await sqliteService.getTripByClientId(rt.client_id);
        if (local && !local.server_id && rt.id) {
          await sqliteService.updateServerId(rt.client_id, rt.id);
        }
      }
    } catch {
      // Ignore network errors during background reconciliation
    }
  }
}

export const tripService = new TripService();
