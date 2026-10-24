/*
 * VeiligBuiten achtergrondcontrole.
 *
 * Draait buiten de WebView (Capacitor Background Runner), zonder DOM en
 * zonder imports. Het besturingssysteem start 'checkIn' ongeveer elke 15
 * minuten; wij houden de door de gebruiker gekozen frequentie aan.
 *
 * Zuinig met de server: de telefoon haalt hooguit eens per uur (of na een
 * verplaatsing van meer dan 10 km) alles binnen 30 km op en vergelijkt
 * daarna lokaal. De locatie verlaat de telefoon alleen als middelpunt van
 * die ene sync-aanvraag en wordt niet opgeslagen.
 *
 * Plekken worden door de server bewaakt (push). Wat binnen het bereik van
 * een plek valt, slaan we hier over, zodat je niets dubbel krijgt.
 */

var SYNC_EVERY_MS = 60 * 60 * 1000;
var RESYNC_DISTANCE_M = 10000;
var WARN_PER_HOUR = 4;
var MAX_DANGER_PER_CHECK = 3;
var BUNDLE_ID_BASE = 2000000000;
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
  return { id: item.id, title: item.title, level: item.level, category: item.category, latitude: item.latitude, longitude: item.longitude, alertRadius: item.alertRadius, confirmations: item.confirmations, official: item.official };
}

async function position(config) {
  try {
    var current = CapacitorGeolocation.getCurrentPosition();
    if (current && typeof current.then === 'function') current = await current;
    if (current && isFinite(current.latitude) && isFinite(current.longitude)) {
      var point = { latitude: current.latitude, longitude: current.longitude };
      write('lastPosition', point);
      return point;
    }
  } catch (error) {
    console.warn('VeiligBuiten: no background location', String(error));
  }
  return read('lastPosition', null);
}

async function cacheFor(config, point) {
  var cache = read('cache', null);
  var now = Date.now();
  var fresh = cache && now - cache.at < SYNC_EVERY_MS && distance(point, cache.center) < RESYNC_DISTANCE_M;
  if (fresh) return cache;

  var response = await fetch(config.apiBase + '/incidents/sync?latitude=' + point.latitude.toFixed(3) + '&longitude=' + point.longitude.toFixed(3), {
    method: 'GET',
    headers: { Accept: 'application/json', Authorization: 'Bearer ' + config.token, 'Accept-Language': config.locale },
  });
  if (response.status === 401 || response.status === 402) {
    // Uitgelogd of geen abonnement meer: stoppen tot de app opnieuw configureert.
    write('config', null);
    return null;
  }
  if (!response.ok) return cache;
  var body = await response.json();
  cache = { at: now, center: point, items: (body.data || []).map(slim) };
  write('cache', cache);
  return cache;
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

async function check(force) {
  var config = read('config', null);
  if (!config || !config.token) return { checked: false, reason: 'not_configured' };

  var now = Date.now();
  var last = read('lastCheck', 0);
  if (!force && now - last < (config.interval || 30) * 60000 - 60000) return { checked: false, reason: 'interval' };
  write('lastCheck', now);

  var point = await position(config);
  if (!point) return { checked: false, reason: 'no_location' };
  var cache = await cacheFor(config, point);
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
    var rank = RANK[item.level] || 1;
    if (rank < minRank) return;
    if (quiet && item.level !== 'danger') return;
    if (categories && categories.indexOf(item.category) === -1) return;
    var away = distance(point, item);
    if (away > item.alertRadius) return;
    var coveredByPlace = places.some(function (place) { return distance(place, item) <= place.radius_m + item.alertRadius; });
    if (coveredByPlace) return;
    // Alleen opnieuw melden als iets zwaarder wordt.
    if ((notified[item.id] || 0) >= rank) return;
    fresh.push({ item: item, rank: rank, away: away });
  });

  fresh.sort(function (a, b) { return b.rank - a.rank || a.away - b.away; });
  var strings = config.strings || {};
  var messages = [];
  var dangers = fresh.filter(function (entry) { return entry.item.level === 'danger'; }).slice(0, MAX_DANGER_PER_CHECK);
  var rest = fresh.filter(function (entry) { return entry.item.level !== 'danger'; });

  dangers.forEach(function (entry) {
    messages.push(notification(entry.item.id, entry.item.title, fill(strings.near || '{d}', { d: distanceText(entry.away, config.locale) }), 'danger', entry.item.id));
  });

  var warnings = rest.filter(function (entry) { return entry.item.level === 'warn'; });
  if (hour.count + Math.min(1, warnings.length) > WARN_PER_HOUR) {
    // Boven het uurplafond: waarschuwingen gaan stil mee in de bundel.
    warnings = [];
  }
  if (rest.length === 1) {
    var single = rest[0];
    messages.push(notification(single.item.id, single.item.title, fill(strings.near || '{d}', { d: distanceText(single.away, config.locale) }), warnings.length ? single.item.level : 'info', single.item.id));
  } else if (rest.length > 1) {
    var titles = rest.slice(0, 4).map(function (entry) { return entry.item.title; }).join(' · ');
    messages.push(notification(BUNDLE_ID_BASE + (Math.floor(now / 60000) % 100000), fill(strings.bundle || '{n}', { n: rest.length }), titles, warnings.length ? 'warn' : 'info', null));
  }
  if (warnings.length) hour.count += 1;

  if (messages.length) CapacitorNotifications.schedule(messages);

  fresh.forEach(function (entry) { notified[entry.item.id] = entry.rank; });
  // Opruimen: alleen bijhouden wat nog in de cache zit.
  var keep = {};
  cache.items.forEach(function (item) { if (notified[item.id]) keep[item.id] = notified[item.id]; });
  write('notified', keep);
  write('warnHour', hour);

  return { checked: true, notified: messages.length };
}

addEventListener('configure', function (resolve, reject, args) {
  try {
    write('config', args && args.token ? args : null);
    if (!args || !args.token) {
      CapacitorKV.remove('cache');
      CapacitorKV.remove('notified');
    }
    resolve({ ok: true });
  } catch (error) {
    reject(String(error));
  }
});

/** De app geeft na een eigen sync de verse gegevens door; dat scheelt de runner een aanvraag. */
addEventListener('sync', function (resolve, reject, args) {
  try {
    if (args && args.items) write('cache', { at: args.at || Date.now(), center: args.center, items: args.items.map(slim) });
    // Terugval als de achtergrond geen locatie krijgt: waar de app het laatst synchroniseerde.
    if (args && args.center) write('lastPosition', args.center);
    resolve({ ok: true });
  } catch (error) {
    reject(String(error));
  }
});

addEventListener('checkIn', async function (resolve, reject, args) {
  try {
    resolve(await check(!!(args && args.force)));
  } catch (error) {
    console.error('VeiligBuiten check failed', String(error));
    resolve({ checked: false, reason: 'error' });
  }
});
