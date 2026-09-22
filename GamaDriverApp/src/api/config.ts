import { Platform } from 'react-native';

/**
 * Default API host addresses:
 * - Android Emulator: 10.0.2.2 maps to host machine 127.0.0.1
 * - Physical device or LAN: 10.10.10.150 (from .env)
 * - iOS Simulator / Local: 127.0.0.1
 */
const DEFAULT_HOST = Platform.select({
  android: 'http://10.0.2.2:8000/api/v1',
  default: 'http://127.0.0.1:8000/api/v1',
});

class ApiConfig {
  private baseUrl: string = DEFAULT_HOST;

  public getBaseUrl(): string {
    return this.baseUrl;
  }

  public setBaseUrl(url: string): void {
    this.baseUrl = url.replace(/\/+$/, '');
  }
}

export const apiConfig = new ApiConfig();
