import type { DangerLevel, IncidentCategory, ReportQuota } from './Incident';

export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  is_admin?: boolean;
  created_at: string;
  updated_at: string;
}

export interface AccountSettings {
  alert_filters: { categories: IncidentCategory[]; min_level: DangerLevel };
  check_interval_minutes: number;
  quiet_hours: { enabled: boolean; from: string; to: string };
}

/** GET /me: alles wat de app over het account nodig heeft. */
export interface Account {
  id: number;
  name: string;
  email: string;
  locale: 'nl' | 'en';
  is_admin: boolean;
  subscription: { active: boolean; status: string | null; expires_at: string | null };
  settings: AccountSettings;
  report_quota: ReportQuota;
  ai: { connected: boolean; webhook: boolean };
}
