import { apiConfig } from './config';
import { ApiError } from './types';

class ApiClient {
  private token: string | null = null;
  private onUnauthorizedCallback: (() => void) | null = null;

  public setToken(token: string | null): void {
    this.token = token;
  }

  public getToken(): string | null {
    return this.token;
  }

  public setOnUnauthorized(callback: () => void): void {
    this.onUnauthorizedCallback = callback;
  }

  public async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const baseUrl = apiConfig.getBaseUrl();
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    const url = `${baseUrl}${cleanEndpoint}`;

    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers as Record<string, string>),
    };

    if (this.token) {
      headers.Authorization = `Bearer ${this.token}`;
    }

    try {
      const response = await fetch(url, {
        ...options,
        headers,
      });

      const responseText = await response.text();
      let data: any = null;

      try {
        data = responseText ? JSON.parse(responseText) : {};
      } catch (parseError) {
        data = { message: responseText };
      }

      if (!response.ok) {
        if (response.status === 401 && this.onUnauthorizedCallback) {
          this.onUnauthorizedCallback();
        }

        const friendlyMessage = this.extractFriendlyErrorMessage(response.status, data);

        const error: ApiError = {
          message: friendlyMessage,
          status: response.status,
          errors: data?.errors,
        };

        throw error;
      }

      return data as T;
    } catch (error: any) {
      if (error?.status) {
        throw error;
      }

      // Network or connection failure
      const networkError: ApiError = {
        message: 'Unable to connect to the fleet server. Please check your internet connection.',
        status: 0,
      };
      throw networkError;
    }
  }

  public get<T>(endpoint: string, options?: RequestInit): Promise<T> {
    return this.request<T>(endpoint, { ...options, method: 'GET' });
  }

  public post<T>(endpoint: string, body?: any, options?: RequestInit): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'POST',
      body: body ? JSON.stringify(body) : undefined,
    });
  }

  public patch<T>(endpoint: string, body?: any, options?: RequestInit): Promise<T> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'PATCH',
      body: body ? JSON.stringify(body) : undefined,
    });
  }

  private extractFriendlyErrorMessage(status: number, data: any): string {
    if (data?.message) {
      // Avoid showing raw database errors to driver
      if (data.message.includes('SQLSTATE') || data.message.includes('QueryException')) {
        return 'Unable to save data. Please contact dispatch if this persists.';
      }
      return data.message;
    }

    if (data?.errors) {
      const firstKey = Object.keys(data.errors)[0];
      if (firstKey && Array.isArray(data.errors[firstKey]) && data.errors[firstKey].length > 0) {
        return data.errors[firstKey][0];
      }
    }

    switch (status) {
      case 401:
        return 'Session expired. Please log in again.';
      case 403:
        return 'Access denied. You are not authorized for this action.';
      case 404:
        return 'Requested information was not found.';
      case 409:
        return 'A conflicting trip already exists.';
      case 422:
        return 'Please check your information and try again.';
      case 500:
      default:
        return 'Server error occurred. Please try again later.';
    }
  }
}

export const apiClient = new ApiClient();
