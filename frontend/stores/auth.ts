import { defineStore } from 'pinia';
import { Capacitor } from '@capacitor/core';
import type { Account, AccountSettings, User } from '~/types/User';
import type { LoginResponse, RegisterResponse, MessageResponse } from '~/types/ApiResponse';
import { storage } from '~/utils/storage';

const TOKEN_KEY = 'vb_auth_token';

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(null);
  const user = ref<User | null>(null);
  const account = ref<Account | null>(null);

  const isLoggedIn = computed(() => !!token.value);
  const isSubscribed = computed(() => !!account.value?.subscription.active);

  async function setSession(newToken: string, newUser: User) {
    token.value = newToken;
    user.value = newUser;
    await storage.set(TOKEN_KEY, newToken);
    await refreshAccount();
  }

  async function restore() {
    if (!import.meta.client) return;
    // Oudere installaties bewaarden het token nog in localStorage.
    const legacy = localStorage.getItem('auth_token');
    token.value = (await storage.get<string>(TOKEN_KEY)) ?? legacy;
    if (legacy) {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('auth_user');
      await storage.set(TOKEN_KEY, legacy);
    }
    account.value = await storage.get<Account>('vb_account');
    if (token.value) refreshAccount().catch(() => undefined);
  }

  async function refreshAccount() {
    if (!token.value) return null;
    try {
      const { data } = await useApi().get<{ data: Account }>('/me');
      await applyAccount(data);
      return data;
    } catch (error: any) {
      if (error?.status === 401) await clear();
      throw error;
    }
  }

  async function applyAccount(data: Account) {
    account.value = data;
    await storage.set('vb_account', data);
    const { locale, setLocale } = useLocale();
    if (data.locale !== locale.value) setLocale(data.locale);
  }

  async function updateSettings(changes: Partial<AccountSettings> & { locale?: 'nl' | 'en' }) {
    const { data } = await useApi().put<{ data: Account }>('/me/settings', changes);
    await applyAccount(data);
    return data;
  }

  async function login(email: string, password: string) {
    const response = await useApi().post<LoginResponse>('/login', { email, password });
    await setSession(response.token, response.user);
    return response.user;
  }

  async function register(name: string, email: string, password: string) {
    if (Capacitor.isNativePlatform()) {
      throw new Error('Maak je account op veiligerbuiten.nl/aanmelden.');
    }
    const response = await useApi().post<RegisterResponse>('/register', {
      name, email, password, password_confirmation: password, locale: useLocale().locale.value,
    });
    await setSession(response.token, response.user);
    return response.user;
  }

  async function logout() {
    await useApi().post('/logout').catch(() => undefined);
    await clear();
  }

  async function deleteAccount(password: string) {
    await useApi().delete('/profile', { password });
    await clear();
  }

  async function clear() {
    token.value = null;
    user.value = null;
    account.value = null;
    await storage.remove(TOKEN_KEY);
    await storage.remove('vb_account');
  }

  async function forgotPassword(email: string) {
    return useApi().post<MessageResponse>('/forgot-password', { email });
  }

  async function resetPassword(resetToken: string, email: string, password: string, password_confirmation: string) {
    return useApi().post<MessageResponse>('/reset-password', { token: resetToken, email, password, password_confirmation });
  }

  async function resendVerification() {
    return useApi().post<MessageResponse>('/email/resend');
  }

  return {
    token, user, account, isLoggedIn, isSubscribed,
    setSession, restore, refreshAccount, applyAccount, updateSettings,
    login, register, logout, deleteAccount, clear,
    forgotPassword, resetPassword, resendVerification,
  };
});
