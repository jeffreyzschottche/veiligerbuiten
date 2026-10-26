/** Same-origin gateway for the web app. The mobile build uses its configured API directly. */
export default defineEventHandler(async (event) => {
  const path = getRouterParam(event, 'path') || '';
  // Internal endpoints must never be accessible through the public gateway.
  if (path.split('/').some((part) => part === 'internal' || part === '.' || part === '..') || /[%\\]/.test(path)) {
    throw createError({ statusCode: 404, statusMessage: 'Not found' });
  }
  const config = useRuntimeConfig(event);
  const query = getRequestURL(event).search;
  const target = `${config.apiOrigin.replace(/\/$/, '')}/api/v1/${path}${query}`;
  try {
    // h3 laat Accept weg bij proxyen; zonder die header antwoordt Laravel met HTML en redirects in plaats van JSON.
    return await proxyRequest(event, target, { headers: { accept: 'application/json' }, fetchOptions: { redirect: 'manual' } });
  } catch {
    throw createError({ statusCode: 502, statusMessage: 'Service temporarily unavailable' });
  }
});
