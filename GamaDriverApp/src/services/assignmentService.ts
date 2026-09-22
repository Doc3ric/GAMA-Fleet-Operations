import { apiClient } from '../api/client';
import { ApiResponse, VehicleAssignment } from '../api/types';

export const assignmentService = {
  async getActiveAssignment(): Promise<VehicleAssignment | null> {
    const response = await apiClient.get<ApiResponse<VehicleAssignment | null>>('/assignment');
    return response.data;
  },
};
