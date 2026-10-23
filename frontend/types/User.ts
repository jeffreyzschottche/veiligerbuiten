export interface User {
  id: number;
  name: string;
  email: string;
  email_verified_at: string | null;
  is_admin?: boolean;
  premium?: boolean;
  created_at: string;
  updated_at: string;
}
