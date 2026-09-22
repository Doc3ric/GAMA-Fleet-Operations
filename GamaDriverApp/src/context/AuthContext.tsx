import React, { createContext, useContext, useEffect, useState } from 'react';
import { apiClient } from '../api/client';
import { User } from '../api/types';
import { authService } from '../services/authService';

interface AuthContextType {
  user: User | null;
  token: string | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  refreshProfile: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const AuthProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);

  useEffect(() => {
    // Register 401 handler on API client
    apiClient.setOnUnauthorized(() => {
      setUser(null);
      setToken(null);
      apiClient.setToken(null);
    });
  }, []);

  const login = async (email: string, password: string): Promise<void> => {
    setIsLoading(true);
    try {
      const data = await authService.login(email, password);
      setUser(data.user);
      setToken(data.token);
    } finally {
      setIsLoading(false);
    }
  };

  const logout = async (): Promise<void> => {
    setIsLoading(true);
    try {
      await authService.logout();
    } catch {
      // Ignore API logout error, clear local state
    } finally {
      setUser(null);
      setToken(null);
      apiClient.setToken(null);
      setIsLoading(false);
    }
  };

  const refreshProfile = async (): Promise<void> => {
    if (!token) return;
    try {
      const updatedUser = await authService.getProfile();
      setUser(updatedUser);
    } catch {
      // Ignore
    }
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        token,
        isLoading,
        login,
        logout,
        refreshProfile,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};
