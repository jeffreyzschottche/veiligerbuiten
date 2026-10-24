/*
 * VeiligBuiten achtergrondcontrole.
 *
 * Draait buiten de WebView (Capacitor Background Runner), zonder DOM en
 * zonder imports. Het besturingssysteem start 'checkIn' ongeveer elke 15
 * minuten; wij houden de door de gebruiker gekozen frequentie aan.
 *
 * De telefoon haalt op de gekozen frequentie (of na een verplaatsing van
 * meer dan 10 km) alles binnen 30 km op en vergelijkt daarna lokaal. Bij
 * netwerkuitval blijven nog geldige, bewaarde situaties bruikbaar. De
 * locatie verlaat de telefoon alleen als middelpunt van die sync-aanvraag.
 *
 * Plekken worden door de server bewaakt (push). Wat binnen het bereik van
 * een plek valt, slaan we online over; offline controleert de telefoon het zelf.
 *
 * Zuinig met de server: het middelpunt valt op een raster van ~5 km, de runner
 * stuurt de ETag en cursor van de vorige sync mee en krijgt dan een lege 304
 * (niets veranderd) of alleen de wijzigingen. De server kan een ondergrens
 * voor het interval meegeven (min_sync_after) als het druk is.
 */

var MAX_CACHE_AGE_MS = 6 * 60 * 60 * 1000;
var MAX_POSITION_AGE_MS = 5 * 60 * 1000;
var CACHE_RADIUS_M = 30000;
var RESYNC_DISTANCE_M = 10000;
var WARN_PER_HOUR = 4;
var MAX_DANGER_PER_CHECK = 3;
var BUNDLE_ID_BASE = 2000000000;
var HOTSPOT_ID_BASE = 2100000000;
var HOTSPOT_REPEAT_MS = 7 * 24 * 60 * 60 * 1000;
var SNAP_DEGREES = 0.05;
var RANK = { info: 1, warn: 2, danger: 3 };

function read(key, fallback) {
  try {
    var result = CapacitorKV.get(key);
    var raw = result && result.value;
    return raw ? JSON.parse(raw) : fallback;
  } catch (error) {
    return fallback;
  }
}

function write(key, value) {
  CapacitorKV.set(key, JSON.stringify(value));
}

function distance(a, b) {
  var rad = Math.PI / 180;
  var dLat = (b.latitude - a.latitude) * rad;
  var dLng = (b.longitude - a.longitude) * rad;
  var h = Math.pow(Math.sin(dLat / 2), 2) + Math.cos(a.latitude * rad) * Math.cos(b.latitude * rad) * Math.pow(Math.sin(dLng / 2), 2);
  return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
}

function fill(template, values) {
  return String(template).replace(/\{(\w+)\}/g, function (_, key) { return values[key] === undefined ? '' : String(values[key]); });
}

function distanceText(meters, locale) {
  if (meters < 1000) return Math.max(10, Math.round(meters / 10) * 10) + ' m';
  var km = (meters / 1000).toFixed(1);
  return (locale === 'nl' ? km.replace('.', ',') : km) + ' km';
}

function minutesOfDay(hhmm) {
  var parts = String(hhmm || '00:00').split(':');
  return Number(parts[0]) * 60 + Number(parts[1] || 0);
}

/** Laatste zondag van een maand, 01:00 UTC: het moment waarop de zomertijd wisselt. */
function lastSundayUtc(year, month) {
  var date = new Date(Date.UTC(year, month + 1, 0, 1));
  date.setUTCDate(date.getUTCDate() - date.getUTCDay());
  return date.getTime();
}

/** Minuut van de dag in Nederlandse tijd. Zonder Intl, want niet elke runner-engine heeft het. */
function amsterdamMinute() {
  var now = new Date();
  var summer = now.getTime() >= lastSundayUtc(now.getUTCFullYear(), 2) && now.getTime() < lastSundayUtc(now.getUTCFullYear(), 9);
  return (now.getUTCHours() * 60 + now.getUTCMinutes() + (summer ? 120 : 60)) % 1440;
}

/** Stille uren in Nederlandse tijd; ook als ze over middernacht lopen. */
function isQuiet(quiet) {
  if (!quiet || !quiet.enabled) return false;
  var minute = amsterdamMinute();
  var from = minutesOfDay(quiet.from);
  var to = minutesOfDay(quiet.to);
  return from <= to ? minute >= from && minute < to : minute >= from || minute < to;
}

function slim(item) {
  return { id: item.id, title: item.title, level: item.level, category: item.category, latitude: item.latitude, longitude: item.longitude, alertRadius: item.alertRadius, confirmations: item.confirmations, official: item.official, expiresAt: item.expiresAt, status: item.status };
}

function slimHotspot(spot) {
  return { key: spot.key, category: spot.category, latitude: spot.latitude, longitude: spot.longitude, radius: spot.radius, count: spot.count, days: spot.days };
}

/** Zelfde raster als de server (AreaSync::snap): bepaalt of een delta op de bewaarde lijst past. */
function snap(point) {
  return {
    latitude: Math.round(Math.round(point.latitude / SNAP_DEGREES) * SNAP_DEGREES * 10000) / 10000,
    longitude: Math.round(Math.round(point.longitude / SNAP_DEGREES) * SNAP_DEGREES * 10000) / 10000,
  };
}

/** Verwerkt een sync-antwoord tot een nieuwe snapshot; een delta bouwt voort op de vorige. */
function applySync(previous, body, etag, now, point) {
  var items;
  if (body.mode === 'delta' && previous && Array.isArray(previous.items)) {
    var removed = {};
    (body.removed || []).concat((body.changes || []).map(function (item) { return item.id; })).forEach(function (id) { removed[id] = true; });
    items = previous.items.filter(function (item) { return !removed[item.id]; }).concat((body.changes || []).map(slim));
  } else {
    items = body.data.map(slim);
  }
  return {
    at: now,
    center: validPoint(body.center) ? body.center : point,
    items: items,
    hotspots: Array.isArray(body.hotspots) ? body.hotspots.map(slimHotspot) : (previous && previous.hotspots) || [],
    cursor: typeof body.cursor === 'number' ? body.cursor : null,
    etag: etag || null,
    minSyncAfter: typeof body.min_sync_after === 'number' ? body.min_sync_after : null,
  };
}

function validPoint(point) {
  return point && typeof point.latitude === 'number' && typeof point.longitude === 'number' &&
    isFinite(point.latitude) && isFinite(point.longitude) && Math.abs(point.latitude) <= 90 && Math.abs(point.longitude) <= 180 &&
    (point.accuracy === undefined || (isFinite(point.accuracy) && point.accuracy >= 0 && point.accuracy <= 1000));
}

async function position(provided) {
  if (validPoint(provided)) {
    var fresh = { latitude: provided.latitude, longitude: provided.longitude, accuracy: provided.accuracy, at: Date.now() };
    write('lastPosition', fresh);
    return fresh;
  }
  try {
    var current = await CapacitorGeolocation.getCurrentPosition();
    if (validPoint(current)) {
      var point = { latitude: current.latitude, longitude: current.longitude, accuracy: current.accuracy, at: Date.now() };
      write('lastPosition', point);
      return point;
    }
  } catch (error) {
    console.warn('VeiligBuiten: background location unavailable');
  }
  var last = read('lastPosition', null);
  return validPoint(last) && last.at && Date.now() - last.at >= 0 && Date.now() - last.at <= MAX_POSITION_AGE_MS ? last : null;
}

async function networkConnected() {
  try {
    var status = await CapacitorDevice.getNetworkStatus();
    return status && typeof status.connected === 'boolean' ? status.connected : null;
  } catch (error) { return null; }
}

function usableCache(cache, point, offline) {
  var age = cache ? Date.now() - cache.at : Infinity;
  if (!cache || !validPoint(cache.center) || !Array.isArray(cache.items) || age < 0 || age > MAX_CACHE_AGE_MS || distance(point, cache.center) > CACHE_RADIUS_M) return null;
  return { at: cache.at, center: cache.center, items: cache.items, hotspots: cache.hotspots || [], offline: offline };
}

async function cacheFor(config, point, localOnly, offlineHint) {
  var cache = read('cache', null);
  var now = Date.now();
  var connected = await networkConnected();
  if (connected === false || offlineHint) return usableCache(cache, point, true);
  // Foreground location events only evaluate the snapshot; useSync owns their network refresh.
  if (localOnly) return usableCache(cache, point, connected !== true);
  var interval = ([15, 30, 60].indexOf(config.interval) >= 0 ? config.interval : 60) * 60000;
  // De server mag bij drukte een ruimer interval vragen.
  if (cache && cache.minSyncAfter) interval = Math.max(interval, cache.minSyncAfter * 1000);
  var fresh = cache && validPoint(cache.center) && now - cache.at >= 0 && now - cache.at < interval && distance(point, cache.center) < RESYNC_DISTANCE_M;
  if (fresh) return usableCache(cache, point, false);

  // Zelfde rastervak als de bewaarde lijst: dan volstaat 'wat veranderde er sinds'.
  var area = snap(point);
  var incremental = cache && cache.cursor && cache.etag && validPoint(cache.center) && now - cache.at >= 0 && now - cache.at < MAX_CACHE_AGE_MS &&
    cache.center.latitude === area.latitude && cache.center.longitude === area.longitude;
  var headers = { Accept: 'application/json', Authorization: 'Bearer ' + config.token, 'Accept-Language': config.locale };
  if (incremental) headers['If-None-Match'] = cache.etag;

  var timeout;
  try {
    // Finish within the OS background budget, even when Wi-Fi has no internet.
    var response = await Promise.race([
      fetch(config.apiBase + '/incidents/sync?latitude=' + area.latitude + '&longitude=' + area.longitude + (incremental ? '&since=' + cache.cursor : ''), {
        method: 'GET',
        headers: headers,
      }).then(async function (response) {
        // Include the body in the deadline, not just receipt of the headers.
        var etag = response.headers && typeof response.headers.get === 'function' ? response.headers.get('ETag') : null;
        if (response.status === 304) return { status: 304, ok: true, etag: etag };
        if (!response.ok) return response;
        var data = await response.json();
        return { status: response.status, ok: true, etag: etag, json: function () { return Promise.resolve(data); } };
      }),
      new Promise(function (_, reject) { timeout = setTimeout(function () { reject(new Error('sync_timeout')); }, 8000); }),
    ]);
    if (response.status === 401 || response.status === 402) {
      write('config', null);
      CapacitorKV.remove('cache');
      return null;
    }
    if (!response.ok) return usableCache(cache, point, true);
    if (response.status === 304) {
      // Niets veranderd: de bewaarde lijst is weer actueel, zonder dat er data over de lijn ging.
      cache.at = now;
      write('cache', cache);
      return usableCache(cache, point, false);
    }
    var body = await response.json();
    if (!body || (body.mode === 'delta' ? !Array.isArray(body.changes) || !incremental : !Array.isArray(body.data))) return usableCache(cache, point, true);
    cache = applySync(cache, body, response.etag, now, point);
    write('cache', cache);
    return usableCache(cache, point, false);
  } catch (error) {
    return usableCache(cache, point, true);
  } finally {
    clearTimeout(timeout);
  }
}

function notification(id, title, body, level, incidentId) {
  return {
    id: id,
    title: title,
    body: body,
    channelId: level === 'danger' ? 'vb_danger' : level === 'warn' ? 'vb_warn' : 'vb_info',
    // iOS speelt alleen geluid met een 'sound'; een onbekende naam geeft het standaardgeluid.
    sound: level === 'info' ? undefined : 'default',
    threadIdentifier: 'veiligbuiten',
    group: 'veiligbuiten',
    autoCancel: true,
    extra: incidentId ? { incident_id: String(incidentId) } : {},
  };
}

async function check(force, providedPosition, localOnly, offlineHint) {
  var config = read('config', null);
  if (!config || !config.token) return { checked: false, reason: 'not_configured' };

  var now = Date.now();
  if (config.validUntil && (!isFinite(Date.parse(config.validUntil)) || Date.parse(config.validUntil) <= now)) return { checked: false, reason: 'subscription_expired' };
  var last = read('lastCheck', 0);
  if (!force && now - last < (config.interval || 30) * 60000 - 60000) return { checked: false, reason: 'interval' };
  write('lastCheck', now);

  var point = await position(providedPosition);
  if (!point) return { checked: false, reason: 'no_location' };
  var cache = await cacheFor(config, point, localOnly, offlineHint);
  if (!cache) return { checked: false, reason: 'no_data' };

  var minRank = RANK[config.minLevel] || RANK.warn;
  var quiet = isQuiet(config.quiet);
  var categories = config.categories || null;
  var places = (config.places || []).filter(function (place) { return place.alerts_enabled; });
  var notified = read('notified', {});
  var hour = read('warnHour', { start: 0, count: 0 });
  if (now - hour.start > 3600000) hour = { start: now, count: 0 };

  var fresh = [];
  cache.items.forEach(function (item) {
    if (!validPoint(item) || !isFinite(item.alertRadius) || item.alertRadius <= 0) return;
    if (item.status === 'opgelost' || item.status === 'verlopen') return;
    if (item.expiresAt && (!isFinite(Date.parse(item.expiresAt)) || Date.parse(item.expiresAt) <= now)) return;
    var rank = RANK[item.level] || 1;
    if (rank < minRank) return;
    if (quiet && item.level !== 'danger') return;
    if (categories && categories.indexOf(item.category) === -1) return;
    var away = distance(point, item);
    if (away > item.alertRadius) return;
    var coveredByPlace = places.some(function (place) { return distance(place, item) <= place.radius_m + item.alertRadius; });
    if (coveredByPlace && !cache.offline) return;
    // Alleen opnieuw melden als iets zwaarder wordt.
    if ((notified[item.id] || 0) >= rank) return;
    fresh.push({ item: item, rank: rank, away: away });
  });

  fresh.sort(function (a, b) { return b.rank - a.rank || a.away - b.away; });
  var strings = config.strings || {};
  var messages = [];
  var delivered = [];
  var dangers = fresh.filter(function (entry) { return entry.item.level === 'danger'; }).slice(0, MAX_DANGER_PER_CHECK);
  var rest = fresh.filter(function (entry) { return entry.item.level !== 'danger'; });

  dangers.forEach(function (entry) {
    delivered.push(entry);
    messages.push(notification(entry.item.id, entry.item.title, fill((cache.offline ? strings.offlineNear : strings.near) || '{d}', { d: distanceText(entry.away, config.locale) }), 'danger', entry.item.id));
  });

  var warnings = rest.filter(function (entry) { return entry.item.level === 'warn'; });
  if (hour.count + Math.min(1, warnings.length) > WARN_PER_HOUR) {
    // Boven het uurplafond: waarschuwingen gaan stil mee in de bundel.
    warnings = [];
  }
  if (rest.length === 1) {
    var single = rest[0];
    messages.push(notification(single.item.id, single.item.title, fill((cache.offline ? strings.offlineNear : strings.near) || '{d}', { d: distanceText(single.away, config.locale) }), warnings.length ? single.item.level : 'info', single.item.id));
  } else if (rest.length > 1) {
    var titles = rest.slice(0, 4).map(function (entry) { return entry.item.title; }).join(' · ');
    messages.push(notification(BUNDLE_ID_BASE + (Math.floor(now / 60000) % 100000), fill(strings.bundle || '{n}', { n: rest.length }), titles, warnings.length ? 'warn' : 'info', null));
  }
  if (rest.length) delivered = delivered.concat(rest);
  if (cache.offline && rest.length > 1) messages[messages.length - 1].body = (strings.offlineLabel || 'Saved earlier') + ' · ' + messages[messages.length - 1].body;
  if (warnings.length) hour.count += 1;

  // Bekende gevaarlijke plek: hoogstens één per controle, per plek eens per week, nooit in stille uren.
  var hotspotNotified = read('hotspotsNotified', {});
  var hotspot = null;
  if (!quiet && minRank <= RANK.warn && strings.hotspotTitles) {
    (cache.hotspots || []).forEach(function (spot) {
      if (!validPoint(spot) || !strings.hotspotTitles[spot.category]) return;
      if (categories && categories.indexOf(spot.category) === -1) return;
      if (hotspotNotified[spot.key] && now - hotspotNotified[spot.key] < HOTSPOT_REPEAT_MS) return;
      var away = distance(point, spot);
      if (away <= spot.radius && (!hotspot || away < hotspot.away)) hotspot = { spot: spot, away: away };
    });
  }
  if (hotspot) {
    var spotId = 0;
    for (var i = 0; i < hotspot.spot.key.length; i++) spotId = (spotId * 31 + hotspot.spot.key.charCodeAt(i)) % 90000000;
    messages.push(notification(HOTSPOT_ID_BASE + spotId, strings.hotspotTitles[hotspot.spot.category], fill(strings.hotspotBody || '{n}', { n: hotspot.spot.count, days: hotspot.spot.days }), 'info', null));
  }

  if (messages.length) await CapacitorNotifications.schedule(messages);
  if (hotspot) {
    hotspotNotified[hotspot.spot.key] = now;
    Object.keys(hotspotNotified).forEach(function (key) { if (now - hotspotNotified[key] > HOTSPOT_REPEAT_MS) delete hotspotNotified[key]; });
    write('hotspotsNotified', hotspotNotified);
  }

  delivered.forEach(function (entry) { notified[entry.item.id] = entry.rank; });
  // Opruimen: alleen bijhouden wat nog in de cache zit.
  var keep = {};
  cache.items.forEach(function (item) { if (notified[item.id]) keep[item.id] = notified[item.id]; });
  write('notified', keep);
  write('warnHour', hour);

  return { checked: true, notified: messages.length, offline: !!cache.offline };
}

addEventListener('configure', function (resolve, reject, args) {
  try {
    var previous = read('config', null);
    if (previous && args && args.token && previous.token !== args.token) {
      CapacitorKV.remove('cache');
      CapacitorKV.remove('notified');
      CapacitorKV.remove('lastPosition');
    }
    write('config', args && args.token ? args : null);
    if (!args || !args.token) {
      CapacitorKV.remove('cache');
      CapacitorKV.remove('notified');
      CapacitorKV.remove('lastPosition');
      CapacitorKV.remove('lastCheck');
      CapacitorKV.remove('warnHour');
      CapacitorKV.remove('hotspotsNotified');
    }
    resolve({ ok: true });
  } catch (error) {
    reject(String(error));
  }
});

/** De app geeft na een eigen sync de verse gegevens door; dat scheelt de runner een aanvraag. */
addEventListener('sync', function (resolve, reject, args) {
  try {
    if (args && args.items) {
      write('cache', {
        at: args.at || Date.now(), center: args.center, items: args.items.map(slim),
        hotspots: (args.hotspots || []).map(slimHotspot), cursor: args.cursor || null, etag: args.etag || null, minSyncAfter: args.minSyncAfter || null,
      });
    }
    resolve({ ok: true });
  } catch (error) {
    reject(String(error));
  }
});

addEventListener('checkIn', async function (resolve, reject, args) {
  try {
    resolve(await check(!!(args && args.force), args && args.position, !!(args && args.localOnly), !!(args && args.offline)));
  } catch (error) {
    console.error('VeiligBuiten check failed', String(error));
    resolve({ checked: false, reason: 'error' });
  }
});
