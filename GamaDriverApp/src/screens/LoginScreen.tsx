import React, { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { apiConfig } from '../api/config';
import { Button } from '../components/Button';
import { useAuth } from '../context/AuthContext';

export const LoginScreen: React.FC = () => {
  const { login, isLoading } = useAuth();
  const [email, setEmail] = useState('driver@gama.com');
  const [password, setPassword] = useState('password');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [showConfig, setShowConfig] = useState(false);
  const [customHost, setCustomHost] = useState(apiConfig.getBaseUrl());

  const handleLogin = async () => {
    if (!email.trim() || !password) {
      setErrorMessage('Please enter your email and password.');
      return;
    }

    setErrorMessage(null);
    try {
      await login(email.trim(), password);
    } catch (error: any) {
      setErrorMessage(error?.message || 'Login failed. Please verify credentials.');
    }
  };

  const handleSaveHost = () => {
    if (customHost.trim()) {
      apiConfig.setBaseUrl(customHost.trim());
      setShowConfig(false);
    }
  };

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      style={styles.container}
    >
      <ScrollView contentContainerStyle={styles.scrollContainer} keyboardShouldPersistTaps="handled">
        {/* Branding */}
        <View style={styles.brandContainer}>
          <View style={styles.logoBadge}>
            <Text style={styles.logoText}>GAMA</Text>
          </View>
          <Text style={styles.brandTitle}>Fleet Operations</Text>
          <Text style={styles.brandSubtitle}>Driver Mobile Portal</Text>
        </View>

        {/* Error Alert */}
        {errorMessage ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{errorMessage}</Text>
          </View>
        ) : null}

        {/* Form */}
        <View style={styles.form}>
          <Text style={styles.label}>DRIVER EMAIL</Text>
          <TextInput
            autoCapitalize="none"
            autoCorrect={false}
            keyboardType="email-address"
            onChangeText={(text) => {
              setEmail(text);
              if (errorMessage) setErrorMessage(null);
            }}
            placeholder="driver@gama.com"
            placeholderTextColor="#94a3b8"
            style={styles.input}
            value={email}
          />

          <Text style={styles.label}>PASSWORD</Text>
          <TextInput
            onChangeText={(text) => {
              setPassword(text);
              if (errorMessage) setErrorMessage(null);
            }}
            placeholder="••••••••"
            placeholderTextColor="#94a3b8"
            secureTextEntry
            style={styles.input}
            value={password}
          />

          <Button
            disabled={isLoading}
            loading={isLoading}
            onPress={handleLogin}
            style={styles.loginButton}
            title="Sign In"
          />

          {/* Quick Credential Helper for Dev/Testing */}
          <View style={styles.devHelpContainer}>
            <Text style={styles.devHelpLabel}>DEVELOPMENT QUICK-FILL:</Text>
            <View style={styles.devRow}>
              <TouchableOpacity
                onPress={() => {
                  setEmail('driver@gama.com');
                  setPassword('password');
                  setErrorMessage(null);
                }}
                style={styles.chip}
              >
                <Text style={styles.chipText}>Driver (Valid)</Text>
              </TouchableOpacity>
              <TouchableOpacity
                onPress={() => {
                  setEmail('admin@gama.com');
                  setPassword('password');
                  setErrorMessage(null);
                }}
                style={[styles.chip, styles.chipOperator]}
              >
                <Text style={styles.chipText}>Operator (403 Test)</Text>
              </TouchableOpacity>
            </View>
          </View>

          {/* API Server Host Toggle */}
          <TouchableOpacity
            onPress={() => setShowConfig(!showConfig)}
            style={styles.configToggle}
          >
            <Text style={styles.configToggleText}>
              {showConfig ? 'Hide Server Settings' : '⚙ Server Settings'}
            </Text>
          </TouchableOpacity>

          {showConfig && (
            <View style={styles.configBox}>
              <Text style={styles.configLabel}>API BASE URL:</Text>
              <TextInput
                autoCapitalize="none"
                autoCorrect={false}
                onChangeText={setCustomHost}
                style={styles.configInput}
                value={customHost}
              />
              <Button
                onPress={handleSaveHost}
                style={styles.saveHostButton}
                title="Update Server URL"
                variant="secondary"
              />
            </View>
          )}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
  scrollContainer: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 24,
  },
  brandContainer: {
    alignItems: 'center',
    marginBottom: 32,
  },
  logoBadge: {
    backgroundColor: '#dc2626',
    paddingHorizontal: 20,
    paddingVertical: 8,
    borderRadius: 8,
    marginBottom: 12,
  },
  logoText: {
    color: '#ffffff',
    fontSize: 24,
    fontWeight: '900',
    letterSpacing: 2,
  },
  brandTitle: {
    color: '#ffffff',
    fontSize: 22,
    fontWeight: '700',
  },
  brandSubtitle: {
    color: '#94a3b8',
    fontSize: 14,
    marginTop: 4,
  },
  errorBox: {
    backgroundColor: '#fef2f2',
    borderColor: '#fca5a5',
    borderWidth: 1,
    borderRadius: 10,
    padding: 12,
    marginBottom: 20,
  },
  errorText: {
    color: '#b91c1c',
    fontSize: 13,
    fontWeight: '600',
    textAlign: 'center',
  },
  form: {
    backgroundColor: '#ffffff',
    borderRadius: 16,
    padding: 24,
    elevation: 4,
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.15,
    shadowRadius: 6,
  },
  label: {
    color: '#475569',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.5,
    marginBottom: 6,
    marginTop: 8,
  },
  input: {
    backgroundColor: '#f8fafc',
    borderColor: '#cbd5e1',
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 16,
    paddingVertical: 12,
    fontSize: 15,
    color: '#0f172a',
    marginBottom: 14,
  },
  loginButton: {
    marginTop: 12,
  },
  devHelpContainer: {
    marginTop: 20,
    paddingTop: 16,
    borderTopWidth: 1,
    borderTopColor: '#f1f5f9',
  },
  devHelpLabel: {
    fontSize: 11,
    color: '#64748b',
    fontWeight: '700',
    marginBottom: 8,
  },
  devRow: {
    flexDirection: 'row',
    gap: 8,
  },
  chip: {
    backgroundColor: '#eff6ff',
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#bfdbfe',
  },
  chipOperator: {
    backgroundColor: '#fef3c7',
    borderColor: '#fde68a',
  },
  chipText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#1e40af',
  },
  configToggle: {
    marginTop: 16,
    alignItems: 'center',
  },
  configToggleText: {
    color: '#64748b',
    fontSize: 12,
    textDecorationLine: 'underline',
  },
  configBox: {
    marginTop: 12,
    padding: 12,
    backgroundColor: '#f8fafc',
    borderRadius: 8,
    borderWidth: 1,
    borderColor: '#e2e8f0',
  },
  configLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#475569',
    marginBottom: 4,
  },
  configInput: {
    backgroundColor: '#ffffff',
    borderColor: '#cbd5e1',
    borderWidth: 1,
    borderRadius: 6,
    paddingHorizontal: 10,
    paddingVertical: 6,
    fontSize: 12,
    color: '#0f172a',
    marginBottom: 8,
  },
  saveHostButton: {
    minHeight: 36,
    paddingVertical: 6,
  },
});
