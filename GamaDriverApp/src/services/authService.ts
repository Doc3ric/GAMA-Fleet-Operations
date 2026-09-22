import { apiClient } from '../api/client';
import { ApiResponse, User } from '../api/types';

interface LoginResponseData {
  user: User;
  token: string;
}

export const authService = {
  async login(email: string, password: string, deviceName = 'GamaDriverApp Android'): Promise<LoginResponseData> {
    const response = await apiClient.post<ApiResponse<LoginResponseData>>('/auth/login', {
      email,
      password,
      device_name: deviceName,
    });

    apiClient.setToken(response.data.token);
    return response.data;
  },

  async logout(): Promise<void> {
    try {
      await apiClient.post<{ message: string }>('/auth/logout');
    } finally {
      apiClient.setToken(null);
    }
  },

  async getProfile(): Promise<User> {
    const response = await apiClient.get<ApiResponse<User>>('/profile');
    return response.data;
  },
};
