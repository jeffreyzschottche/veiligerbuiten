import { Capacitor } from '@capacitor/core';
import { Preferences } from '@capacitor/preferences';

/**
 * Kleine sleutel-waardeopslag. Op de telefoon via Preferences (iOS kan
 * localStorage van een WebView opruimen), in de browser via localStorage.
 */
export const storage = {
  async get<T>(key: string): Promise<T | null> {
    try {
      const raw = Capacitor.isNativePlatform() ? (await Preferences.get({ key })).value : localStorage.getItem(key);
      return raw ? JSON.parse(raw) as T : null;
    } catch {
      return null;
    }
  },
  async set(key: string, value: unknown) {
    const raw = JSON.stringify(value);
    try {
      if (Capacitor.isNativePlatform()) await Preferences.set({ key, value: raw });
      else localStorage.setItem(key, raw);
    } catch { /* Opslag vol of geblokkeerd: de app werkt verder zonder cache. */ }
  },
  async remove(key: string) {
    try {
      if (Capacitor.isNativePlatform()) await Preferences.remove({ key });
      else localStorage.removeItem(key);
    } catch { /* niets te doen */ }
  },
};
