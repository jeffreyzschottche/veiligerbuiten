import { apiFetch, apiFetchRaw } from '~/services/apiFetch';

/** API-verzoeken met het account-token, het toesteltoken en de taal van de app. */
export function useApi() {
  const authStore = useAuthStore();
  const { locale } = useLocale();

  function headers(extra: Record<string, string> = {}) {
    const headers: Record<string, string> = { 'Accept-Language': locale.value, ...extra };
    if (authStore.token) headers.Authorization = `Bearer ${authStore.token}`;
    const installation = useState<string | null>('installation-token').value;
    if (installation) headers['X-Installation-Token'] = installation;
    return headers;
  }

  async function request<T>(endpoint: string, method: string, data?: unknown): Promise<T> {
    return apiFetch<T>(endpoint, {
      method,
      headers: headers(),
      body: data === undefined ? undefined : JSON.stringify(data),
    });
  }

  return {
    get: <T>(endpoint: string) => request<T>(endpoint, 'GET'),
    /** GET met eigen headers (bijv. If-None-Match); geeft status en headers terug, 304 is geen fout. */
    getRaw: <T>(endpoint: string, extra: Record<string, string> = {}) => apiFetchRaw<T>(endpoint, { method: 'GET', headers: headers(extra) }),
    post: <T>(endpoint: string, data?: unknown) => request<T>(endpoint, 'POST', data ?? {}),
    put: <T>(endpoint: string, data?: unknown) => request<T>(endpoint, 'PUT', data ?? {}),
    patch: <T>(endpoint: string, data?: unknown) => request<T>(endpoint, 'PATCH', data ?? {}),
    delete: <T>(endpoint: string, data?: unknown) => request<T>(endpoint, 'DELETE', data),
  };
}
