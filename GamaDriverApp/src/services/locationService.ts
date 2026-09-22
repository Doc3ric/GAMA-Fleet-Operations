import { PermissionsAndroid, Platform } from 'react-native';
import Geolocation from '@react-native-community/geolocation';

export interface LocationResult {
  latitude: number;
  longitude: number;
  accuracy: number;
  timestamp: number;
}

export class LocationError extends Error {
  constructor(
    public code: 'PERMISSION_DENIED' | 'PERMISSION_BLOCKED' | 'SERVICES_DISABLED' | 'TIMEOUT' | 'INVALID_FIX' | 'UNKNOWN',
    message: string
  ) {
    super(message);
    this.name = 'LocationError';
  }
}

/**
 * Configure Geolocation once for the application lifecycle
 */
Geolocation.setRNConfiguration({
  skipPermissionRequests: true, // Explicitly managed via PermissionsAndroid
  authorizationLevel: 'whenInUse',
  enableBackgroundLocationUpdates: false,
});

class LocationService {
  /**
   * Check if foreground location permission is granted
   */
  public async checkPermission(): Promise<boolean> {
    if (Platform.OS !== 'android') {
      return true;
    }

    const fineGranted = await PermissionsAndroid.check(
      PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION
    );
    const coarseGranted = await PermissionsAndroid.check(
      PermissionsAndroid.PERMISSIONS.ACCESS_COARSE_LOCATION
    );

    return fineGranted || coarseGranted;
  }

  /**
   * Request native foreground location permission
   */
  public async requestPermission(): Promise<'granted' | 'denied' | 'blocked'> {
    if (Platform.OS !== 'android') {
      return 'granted';
    }

    try {
      const results = await PermissionsAndroid.requestMultiple([
        PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION,
        PermissionsAndroid.PERMISSIONS.ACCESS_COARSE_LOCATION,
      ]);

      const fine = results[PermissionsAndroid.PERMISSIONS.ACCESS_FINE_LOCATION];
      const coarse = results[PermissionsAndroid.PERMISSIONS.ACCESS_COARSE_LOCATION];

      if (
        fine === PermissionsAndroid.RESULTS.GRANTED ||
        coarse === PermissionsAndroid.RESULTS.GRANTED
      ) {
        return 'granted';
      }

      if (
        fine === PermissionsAndroid.RESULTS.NEVER_ASK_AGAIN ||
        coarse === PermissionsAndroid.RESULTS.NEVER_ASK_AGAIN
      ) {
        return 'blocked';
      }

      return 'denied';
    } catch (err) {
      return 'denied';
    }
  }

  /**
   * Obtain a SINGLE hardware location fix.
   * Does NOT watch or continuously track the driver's location.
   */
  public async getCurrentLocation(timeoutMs: number = 15000): Promise<LocationResult> {
    // 1. Verify/request permission
    const hasPermission = await this.checkPermission();
    if (!hasPermission) {
      const permissionStatus = await this.requestPermission();
      if (permissionStatus === 'denied') {
        throw new LocationError(
          'PERMISSION_DENIED',
          'Location permission is required to record the trip. Please allow location access.'
        );
      }
      if (permissionStatus === 'blocked') {
        throw new LocationError(
          'PERMISSION_BLOCKED',
          'Location permission has been disabled. Please enable Location in your device Settings to record trips.'
        );
      }
    }

    // 2. Request single GPS fix
    return new Promise<LocationResult>((resolve, reject) => {
      Geolocation.getCurrentPosition(
        (position) => {
          const { latitude, longitude, accuracy } = position.coords;

          // 3. Coordinate validation
          if (
            typeof latitude !== 'number' ||
            typeof longitude !== 'number' ||
            isNaN(latitude) ||
            isNaN(longitude) ||
            latitude < -90 ||
            latitude > 90 ||
            longitude < -180 ||
            longitude > 180
          ) {
            return reject(
              new LocationError(
                'INVALID_FIX',
                'Invalid GPS coordinates returned by device sensor. Please try again.'
              )
            );
          }

          const resolvedAccuracy =
            typeof accuracy === 'number' && !isNaN(accuracy) && accuracy >= 0
              ? Math.round(accuracy * 10) / 10
              : 0;

          resolve({
            latitude,
            longitude,
            accuracy: resolvedAccuracy,
            timestamp: position.timestamp || Date.now(),
          });
        },
        (error) => {
          // Map native error codes: 1 = PERMISSION_DENIED, 2 = POSITION_UNAVAILABLE, 3 = TIMEOUT
          if (error.code === 1) {
            reject(
              new LocationError(
                'PERMISSION_DENIED',
                'Location permission was denied. Please allow location access to continue.'
              )
            );
          } else if (error.code === 2) {
            reject(
              new LocationError(
                'SERVICES_DISABLED',
                'Unable to get your current location. Please make sure Location (GPS) is enabled on your device and try again.'
              )
            );
          } else if (error.code === 3) {
            reject(
              new LocationError(
                'TIMEOUT',
                'GPS location request timed out. Please ensure you have a clear view of the sky or network connectivity, and try again.'
              )
            );
          } else {
            reject(
              new LocationError(
                'UNKNOWN',
                'Unable to acquire location fix. Please make sure Location is enabled and try again.'
              )
            );
          }
        },
        {
          enableHighAccuracy: true, // Prefer GPS hardware sensor over approximate cell tower
          timeout: timeoutMs,
          maximumAge: 0, // Never accept stale cached location fix; require fresh hardware reading
        }
      );
    });
  }
}

export const locationService = new LocationService();
