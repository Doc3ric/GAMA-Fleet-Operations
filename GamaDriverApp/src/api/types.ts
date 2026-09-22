export type UserRole = 'admin' | 'operator' | 'driver';

export interface User {
  id: number;
  name: string;
  email: string;
  role: UserRole;
}

export interface Vehicle {
  id: number;
  equipment_code: string;
  model: string | null;
  plate_number: string | null;
  vehicle_type: string | null;
}

export interface VehicleAssignment {
  assignment_id: number;
  vehicle_id: number;
  equipment_code: string;
  model: string | null;
  plate_number: string | null;
  vehicle_type: string | null;
  assigned_from: string;
  assigned_until: string | null;
  notes: string | null;
}

export type TripStatus = 'IN_PROGRESS' | 'COMPLETED' | 'CANCELLED';

export interface LocationPoint {
  latitude: number | null;
  longitude: number | null;
  accuracy: number | null;
  address: string | null;
}

export interface DriverTrip {
  id: number;
  client_id: string;
  driver: {
    id: number;
    name: string;
    email: string;
  };
  vehicle: Vehicle;
  driver_vehicle_assignment_id: number | null;
  trip_date: string;
  time_in: string;
  time_out: string | null;
  origin: LocationPoint;
  destination: LocationPoint;
  status: TripStatus;
  sync_status?: SyncStatus;
  sync_error?: string | null;
  remarks: string | null;
  created_at: string;
  updated_at: string;
}

export interface StartTripPayload {
  client_id: string;
  vehicle_id: number;
  trip_date?: string;
  time_in: string;
  origin_latitude: number;
  origin_longitude: number;
  origin_accuracy?: number | null;
  origin_address?: string | null;
  remarks?: string | null;
}

export interface EndTripPayload {
  time_out: string;
  destination_latitude: number;
  destination_longitude: number;
  destination_accuracy?: number | null;
  destination_address?: string | null;
  remarks?: string | null;
}

export interface ApiResponse<T> {
  data: T;
  message?: string;
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
  status?: number;
}

export type SyncStatus = 'PENDING' | 'SYNCING' | 'SYNCED' | 'FAILED';

export type SyncQueueAction = 'START_TRIP' | 'END_TRIP' | 'CANCEL_TRIP';

export interface LocalTrip {
  client_id: string;
  server_id: number | null;
  driver_id: number;
  vehicle_id: number;
  driver_vehicle_assignment_id: number | null;
  trip_date: string;
  time_in: string;
  time_out: string | null;
  origin_latitude: number;
  origin_longitude: number;
  origin_accuracy: number | null;
  origin_address: string | null;
  destination_latitude: number | null;
  destination_longitude: number | null;
  destination_accuracy: number | null;
  destination_address: string | null;
  status: TripStatus;
  sync_status: SyncStatus;
  sync_error: string | null;
  remarks: string | null;
  created_at: string;
  updated_at: string;
}

export interface SyncQueueItem {
  id: number;
  client_id: string;
  action: SyncQueueAction;
  payload_json: string;
  status: 'PENDING' | 'SYNCING' | 'FAILED';
  retry_count: number;
  last_attempt_at: string | null;
  error_message: string | null;
  created_at: string;
}

