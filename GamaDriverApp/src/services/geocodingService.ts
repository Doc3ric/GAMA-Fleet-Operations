/**
 * Service for reverse geocoding GPS coordinates into human-readable addresses
 * using OpenStreetMap Nominatim.
 *
 * Requirements:
 * - Coordinates are authoritative; address is supplementary.
 * - If reverse geocoding fails or times out, return null without preventing trip creation.
 * - Uses compliant User-Agent header as required by Nominatim usage policy.
 */
class GeocodingService {
  private readonly NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org/reverse';
  private readonly USER_AGENT = 'GamaDriverApp/1.0 (fleet-monitoring@gama.com)';
  private readonly TIMEOUT_MS = 25000; // 25 seconds timeout to accommodate uncached Nominatim DB lookups

  /**
   * Reverse geocode latitude/longitude into a clean street address.
   * Returns null if network fails, times out, or geocoding is unavailable.
   */
  public async reverseGeocode(
    latitude: number,
    longitude: number
  ): Promise<string | null> {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), this.TIMEOUT_MS);

    try {
      const url = `${this.NOMINATIM_BASE_URL}?format=jsonv2&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1&email=fleet-monitoring@gama.com`;

      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'User-Agent': this.USER_AGENT,
          Accept: 'application/json',
        },
        signal: controller.signal,
      });

      clearTimeout(timeoutId);

      if (!response.ok) {
        console.warn(`[Geocoding] Non-200 HTTP response: ${response.status}`);
        return null;
      }

      const data = await response.json();
      const address = this.formatAddress(data);
      console.log(`[Geocoding] Resolved address: ${address}`);
      return address;
    } catch (error) {
      clearTimeout(timeoutId);
      console.warn('[Geocoding] Error during reverse geocoding:', error);
      // Nominatim failure must never block trip saving
      return null;
    }
  }

  /**
   * Formats Nominatim jsonv2 response into a readable, concise street address.
   */
  private formatAddress(data: any): string | null {
    if (!data) return null;

    const address = data.address;
    if (!address) {
      return data.display_name ? data.display_name.slice(0, 255) : null;
    }

    const parts: string[] = [];

    // 1. Building / POI / Street line
    const primary =
      address.building ||
      address.road ||
      address.pedestrian ||
      address.highway ||
      address.commercial ||
      address.industrial;

    if (primary) {
      if (address.house_number && primary !== address.house_number) {
        parts.push(`${address.house_number} ${primary}`);
      } else {
        parts.push(primary);
      }
    }

    // 2. District / Barangay / Suburb
    const subArea =
      address.neighbourhood ||
      address.quarter ||
      address.suburb ||
      address.city_district;

    if (subArea && !parts.includes(subArea)) {
      parts.push(subArea);
    }

    // 3. City / Municipality
    const city =
      address.city ||
      address.town ||
      address.municipality ||
      address.county;

    if (city && !parts.includes(city)) {
      parts.push(city);
    }

    // 4. Region / State (if different)
    const province = address.province || address.state || address.region;
    if (province && !parts.includes(province) && parts.length < 3) {
      parts.push(province);
    }

    if (parts.length > 0) {
      return parts.join(', ').slice(0, 255);
    }

    return data.display_name ? data.display_name.slice(0, 255) : null;
  }
}

export const geocodingService = new GeocodingService();
