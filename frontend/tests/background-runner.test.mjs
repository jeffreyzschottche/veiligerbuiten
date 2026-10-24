import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const code = readFileSync(new URL('../public/runners/background.js', import.meta.url), 'utf8');
const baseTime = Date.parse('2026-10-24T12:00:00Z');
const start = { latitude: 52.37, longitude: 4.88, accuracy: 10 };
const destination = { latitude: 52.37, longitude: 4.90, accuracy: 10 };
function incident(overrides = {}) {
  return { id: 1, title: 'Brand', category: 'brand', level: 'danger', status: 'officieel', latitude: destination.latitude, longitude: destination.longitude, alertRadius: 300, expiresAt: new Date(baseTime + 3_600_000).toISOString(), ...overrides };
}
function runner(options = {}) {
  let now = baseTime;
  const kv = new Map();
  const events = new Map();
  const messages = [];
  let point = options.point ?? start;
  let connected = options.connected ?? true;
  let fetches = 0;
  const config = { token: 'account-token', apiBase: 'https://private.test/api/v1', interval: 15, locale: 'nl', minLevel: 'warn', strings: { near: '{d} van je af', offlineNear: 'Eerder opgeslagen · {d}', offlineLabel: 'Eerder opgeslagen', bundle: '{n} situaties' }, ...options.config };
  kv.set('config', JSON.stringify(config));
  if (options.cache !== null) kv.set('cache', JSON.stringify(options.cache ?? { at: now, center: start, items: [incident()] }));
  class Clock extends Date { constructor(...args) { super(...(args.length ? args : [now])); } static now() { return now; } }
  const context = vm.createContext({
    Date: Clock, console: { warn() {}, error() {} },
    setTimeout: options.fastTimeout ? (fn) => setTimeout(fn, 2) : setTimeout, clearTimeout,
    addEventListener: (name, callback) => events.set(name, callback),
    CapacitorKV: { get: (key) => ({ value: kv.get(key) }), set: (key, value) => kv.set(key, value), remove: (key) => kv.delete(key) },
    CapacitorDevice: { getNetworkStatus: () => ({ connected }) },
    CapacitorGeolocation: { getCurrentPosition: () => point },
    CapacitorNotifications: { schedule: (items) => { if (options.notificationFailure) throw new Error('permission denied'); messages.push(...items); } },
    fetch: async (...args) => { fetches++; return options.fetch ? options.fetch(...args) : { ok: true, status: 200, json: async () => ({ data: [incident()] }) }; },
  });
  vm.runInContext(code, context);
  const dispatch = (name, args = {}) => new Promise((resolve, reject) => events.get(name)(resolve, reject, args));
  return { kv, messages, dispatch, check: (args = {}) => dispatch('checkIn', { force: true, ...args }), move: (next) => { point = next; }, network: (value) => { connected = value; }, advance: (ms) => { now += ms; }, get fetches() { return fetches; }, read: (key) => JSON.parse(kv.get(key) || 'null') };
}

test('sync first, lose internet, move near a saved incident: local notification without another fetch', async () => {
  const app = runner({ cache: null });
  assert.equal((await app.check()).notified, 0);
  assert.equal(app.fetches, 1);
  assert.ok(app.read('cache').items[0].expiresAt);
  app.network(false);
  app.move(destination);
  const result = await app.check();
  assert.equal(result.notified, 1);
  assert.equal(result.offline, true);
  assert.equal(app.fetches, 1);
  assert.match(app.messages[0].body, /Eerder opgeslagen/);
  assert.equal((await app.check()).notified, 0, 'same severity must not notify twice');
});

test('a rejected sync request still evaluates valid saved reports', async () => {
  const app = runner({ point: destination, cache: { at: baseTime - 3_600_000, center: start, items: [incident()] }, fetch: () => { throw new Error('offline'); } });
  const result = await app.check();
  assert.equal(result.notified, 1);
  assert.equal(result.offline, true);
});

test('Wi-Fi without working internet times out and uses the local snapshot', async () => {
  const app = runner({ point: destination, fastTimeout: true, cache: { at: baseTime - 3_600_000, center: start, items: [incident()] }, fetch: () => new Promise(() => {}) });
  assert.equal((await app.check()).notified, 1);
});

test('a stalled response body also falls back within the sync deadline', async () => {
  const app = runner({ point: destination, fastTimeout: true, cache: { at: baseTime - 3_600_000, center: start, items: [incident()] }, fetch: async () => ({ ok: true, status: 200, json: () => new Promise(() => {}) }) });
  assert.equal((await app.check()).notified, 1);
});

test('expired and resolved reports cannot trigger offline notifications', async () => {
  const app = runner({ point: destination, connected: false, cache: { at: baseTime, center: start, items: [incident({ expiresAt: new Date(baseTime - 1).toISOString() }), incident({ id: 2, status: 'opgelost' }), incident({ id: 3, status: 'verlopen' }), incident({ id: 4, expiresAt: 'invalid' })] } });
  assert.equal((await app.check()).notified, 0);
});

test('snapshots older than six hours or outside their saved area are not used', async () => {
  const old = runner({ point: destination, connected: false, cache: { at: baseTime - 6 * 3_600_000 - 1, center: start, items: [incident()] } });
  assert.equal((await old.check()).reason, 'no_data');
  const far = runner({ point: { latitude: 53, longitude: 5, accuracy: 10 }, connected: false });
  assert.equal((await far.check()).reason, 'no_data');
});

test('offline checks also cover saved places when a server push cannot arrive', async () => {
  const app = runner({ point: destination, connected: false, config: { places: [{ ...destination, radius_m: 500, alerts_enabled: true }] } });
  assert.equal((await app.check()).notified, 1);
});

test('foreground GPS updates evaluate locally and honour an observed connectivity failure', async () => {
  const app = runner({ config: { places: [{ ...destination, radius_m: 500, alerts_enabled: true }] } });
  const result = await app.check({ position: destination, localOnly: true, offline: true });
  assert.equal(result.notified, 1);
  assert.equal(result.offline, true);
  assert.equal(app.fetches, 0);
});

test('online checks leave saved-place notifications to server push', async () => {
  const app = runner({ point: destination, config: { places: [{ ...destination, radius_m: 500, alerts_enabled: true }] } });
  assert.equal((await app.check()).notified, 0);
});

test('an expired subscription or rejected authentication stops cached notifications', async () => {
  const expired = runner({ point: destination, connected: false, config: { validUntil: new Date(baseTime - 1).toISOString() } });
  assert.equal((await expired.check()).reason, 'subscription_expired');
  for (const status of [401, 402]) {
    const app = runner({ point: destination, cache: { at: baseTime - 3_600_000, center: start, items: [incident()] }, fetch: async () => ({ ok: false, status }) });
    assert.equal((await app.check()).reason, 'no_data');
    assert.equal(app.read('config'), null);
    assert.equal(app.read('cache'), null);
  }
});

test('location filters and category preferences apply offline', async () => {
  const app = runner({ point: destination, connected: false, config: { categories: ['weer'] } });
  assert.equal((await app.check()).notified, 0);
  const inaccurate = runner({ point: { ...destination, accuracy: 5000 }, connected: false });
  assert.equal((await inaccurate.check()).reason, 'no_location');
});

test('old fallback positions do not create misleading proximity alerts', async () => {
  const app = runner({ point: destination, connected: false });
  await app.check();
  app.move(null);
  app.advance(6 * 60_000);
  assert.equal((await app.check()).reason, 'no_location');
});

test('notification scheduling failure does not mark the report as delivered', async () => {
  const app = runner({ point: destination, connected: false, notificationFailure: true });
  assert.equal((await app.check()).reason, 'error');
  assert.equal(app.read('notified'), null);
});

test('danger cap retains unannounced reports for a later check', async () => {
  const app = runner({ point: destination, connected: false, cache: { at: baseTime, center: start, items: [1, 2, 3, 4].map(id => incident({ id })) } });
  assert.equal((await app.check()).notified, 3);
  assert.equal((await app.check()).notified, 1);
});

test('changing accounts and signing out clear the previous private snapshot', async () => {
  const app = runner();
  await app.dispatch('configure', { token: 'another-account-token' });
  assert.equal(app.read('cache'), null);
  await app.dispatch('configure', {});
  assert.equal(app.read('config'), null);
  assert.equal(app.read('lastPosition'), null);
});

test('the selected interval governs background refreshes', async () => {
  const app = runner({ config: { interval: 30 } });
  app.advance(16 * 60_000);
  await app.check();
  assert.equal(app.fetches, 0);
  app.advance(15 * 60_000);
  await app.check();
  assert.equal(app.fetches, 1);
});

const area = { latitude: 52.35, longitude: 4.9 };
function syncedCache(overrides = {}) {
  return { at: baseTime - 3_600_000, center: area, items: [incident()], hotspots: [], cursor: 1000, etag: 'W/"v1"', ...overrides };
}
function response(status, body = null, etag = 'W/"v2"') {
  return { ok: status < 400, status, headers: { get: (name) => (name === 'ETag' ? etag : null) }, json: async () => body };
}

test('an unchanged area costs only an empty 304 and keeps the saved list', async () => {
  let request;
  const app = runner({ point: start, cache: syncedCache(), fetch: (url, options) => { request = { url, options }; return response(304); } });
  await app.check();
  assert.match(request.url, /latitude=52\.35&longitude=4\.9&since=1000/);
  assert.equal(request.options.headers['If-None-Match'], 'W/"v1"');
  assert.equal(app.read('cache').at, baseTime);
  assert.equal(app.read('cache').items.length, 1);
});

test('a delta adds, replaces and removes only what changed', async () => {
  const app = runner({ point: start, cache: syncedCache({ items: [incident(), incident({ id: 2, title: 'Oud' }), incident({ id: 3 })] }), fetch: () => response(200, {
    mode: 'delta', changes: [incident({ id: 2, title: 'Nieuw' }), incident({ id: 4 })], removed: [3], center: area, cursor: 2000, hotspots: [{ key: 'h1', category: 'verkeer', latitude: 52.37, longitude: 4.9, radius: 200, count: 4, days: 3 }],
  }) });
  await app.check();
  const cache = app.read('cache');
  assert.deepEqual(cache.items.map((item) => item.id).sort(), [1, 2, 4]);
  assert.equal(cache.items.find((item) => item.id === 2).title, 'Nieuw');
  assert.equal(cache.cursor, 2000);
  assert.equal(cache.etag, 'W/"v2"');
  assert.equal(cache.hotspots.length, 1);
});

test('moving to another grid square asks for a full list instead of a delta', async () => {
  let url;
  const app = runner({ point: { latitude: 52.42, longitude: 4.9, accuracy: 10 }, cache: syncedCache(), fetch: (requested) => { url = requested; return response(200, { mode: 'full', data: [], center: { latitude: 52.4, longitude: 4.9 }, cursor: 3000 }); } });
  await app.check();
  assert.doesNotMatch(url, /since=/);
  assert.equal(app.read('cache').center.latitude, 52.4);
});

test('the server can ask phones to sync less often when it is busy', async () => {
  const app = runner({ point: start, config: { interval: 15 }, cache: syncedCache({ at: baseTime - 20 * 60_000, minSyncAfter: 3600 }) });
  await app.check();
  assert.equal(app.fetches, 0);
});

test('a known dangerous spot gives one quiet heads-up per week', async () => {
  const hotspots = [{ key: 'h1', category: 'verkeer', latitude: start.latitude, longitude: start.longitude, radius: 300, count: 4, days: 3 }];
  const strings = { near: '{d}', bundle: '{n}', hotspotTitles: { verkeer: 'Hier gebeuren vaak ongevallen' }, hotspotBody: '{n} ongevallen in de afgelopen weken' };
  const app = runner({ connected: false, config: { strings }, cache: { at: baseTime, center: start, items: [], hotspots } });
  assert.equal((await app.check()).notified, 1);
  assert.equal(app.messages[0].title, 'Hier gebeuren vaak ongevallen');
  assert.equal(app.messages[0].channelId, 'vb_info');
  assert.equal((await app.check()).notified, 0);
  app.advance(8 * 24 * 3_600_000);
  app.kv.set('cache', JSON.stringify({ at: baseTime + 8 * 24 * 3_600_000, center: start, items: [], hotspots }));
  assert.equal((await app.check()).notified, 1);
});
