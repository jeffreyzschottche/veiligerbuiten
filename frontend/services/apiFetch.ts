import { Capacitor, CapacitorHttp, type HttpResponse } from '@capacitor/core';
import { ApiError } from './ApiError';

function getApiBaseUrl(): string {
  const config = useRuntimeConfig();
  return config.public.apiBase as string;
}

function normalizeHeaders(headers?: HeadersInit): Record<string, string> {
  if (!headers) {
    return {};
  }

  if (headers instanceof Headers) {
    return Object.fromEntries(headers.entries());
  }

  if (Array.isArray(headers)) {
    return headers.reduce<Record<string, string>>((acc, [key, value]) => {
      acc[key] = value;
      return acc;
    }, {});
  }

  return { ...headers };
}

function parseBody(body?: BodyInit | null): any {
  if (!body) {
    return undefined;
  }

  if (typeof body === 'string') {
    try {
      return JSON.parse(body);
    } catch {
      return body;
    }
  }

  return body;
}

export interface RawResponse<T> {
  status: number;
  data: T;
  /** Kleine letters, zoals fetch ze ook teruggeeft. */
  headers: Record<string, string>;
}

/** Eén verzoek, zonder te gooien bij een foutstatus. Native via CapacitorHttp (comprimeert en decomprimeert zelf). */
async function send<T>(endpoint: string, options: RequestInit = {}): Promise<RawResponse<T>> {
  const baseUrl = getApiBaseUrl();
  const url = endpoint.startsWith('http') ? endpoint : `${baseUrl}${endpoint}`;
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...normalizeHeaders(options.headers),
  };

  if (Capacitor.isNativePlatform()) {
    const response: HttpResponse = await CapacitorHttp.request({
      url,
      method: (options.method || 'GET') as any,
      headers,
      data: parseBody(options.body),
    });
    const lower = Object.fromEntries(Object.entries(response.headers ?? {}).map(([key, value]) => [key.toLowerCase(), String(value)]));
    return { status: response.status, data: response.data as T, headers: lower };
  }

  const response = await fetch(url, { ...options, headers });
  const data = response.status === 304 ? null : await response.json().catch(() => null);

  return { status: response.status, data: data as T, headers: Object.fromEntries(response.headers.entries()) };
}

export async function apiFetch<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
  const { status, data } = await send<T>(endpoint, options);

  if (status < 200 || status >= 300) {
    throw new ApiError(
      (data as any)?.message || 'An error occurred',
      status,
      data
    );
  }

  return data;
}

/** Zoals apiFetch, maar een 304 (niets veranderd) is een geldig antwoord en de headers komen mee. */
export async function apiFetchRaw<T>(endpoint: string, options: RequestInit = {}): Promise<RawResponse<T | null>> {
  const response = await send<T>(endpoint, options);

  if (response.status !== 304 && (response.status < 200 || response.status >= 300)) {
    throw new ApiError((response.data as any)?.message || 'An error occurred', response.status, response.data);
  }

  return response.status === 304 ? { ...response, data: null } : response;
}
