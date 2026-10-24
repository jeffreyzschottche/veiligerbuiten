import { $fetch } from 'ofetch';

interface CommunitySnapshot {
  total: number;
  updated_at: string;
}

/** Only this aggregate crosses the server boundary; never forward upstream errors. */
export default defineCachedEventHandler(async (event) => {
  const config = useRuntimeConfig(event);
  if (!config.siteStatsToken) {
    throw createError({ statusCode: 503, statusMessage: 'Community count temporarily unavailable' });
  }

  try {
    const snapshot = await $fetch<CommunitySnapshot>(`${config.apiOrigin.replace(/\/$/, '')}/api/v1/internal/site-statistics`, {
      headers: { Authorization: `Bearer ${config.siteStatsToken}`, Accept: 'application/json' },
      timeout: 5000,
      retry: 0,
      redirect: 'error',
    });
    if (!Number.isSafeInteger(snapshot.total) || snapshot.total < 976 || !Number.isFinite(Date.parse(snapshot.updated_at))) {
      throw new Error('Invalid community snapshot');
    }
    return { total: snapshot.total, updatedAt: snapshot.updated_at };
  } catch {
    throw createError({ statusCode: 503, statusMessage: 'Community count temporarily unavailable' });
  }
}, { maxAge: 3600, swr: true, name: 'site-community' });
